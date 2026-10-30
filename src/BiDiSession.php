<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Rnscommerce\WebDriver\BiDi\BiDi;
use Rnscommerce\WebDriver\BiDi\BiDiException;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\BrowsingContext;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\ContextCreatedParameters;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\CreateParameters;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\CreateType;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\NavigationInfo;
use Rnscommerce\WebDriver\BiDi\ErrorResponseException;
use Rnscommerce\WebDriver\BiDi\Module\Browser;
use Rnscommerce\WebDriver\BiDi\Module\BrowsingContextInspector;
use Rnscommerce\WebDriver\BiDi\Module\Network;
use Rnscommerce\WebDriver\BiDi\Module\Script;
use Rnscommerce\WebDriver\BiDi\Network\BeforeRequestSentParameters;
use Rnscommerce\WebDriver\BiDi\Network\FetchErrorParameters;
use Rnscommerce\WebDriver\BiDi\Network\ResponseCompletedParameters;
use Rnscommerce\WebDriver\BiDi\Script\ChannelProperties;
use Rnscommerce\WebDriver\BiDi\Script\ChannelValue;
use Rnscommerce\WebDriver\BiDi\Script\EvaluationResultSuccess;
use Rnscommerce\WebDriver\BiDi\Script\MessageParameters;
use Rnscommerce\WebDriver\BiDi\Script\NumberValue;

/**
 * The BiDi connection to one WebDriver session, following the activity of its pages.
 */
final class BiDiSession
{
    private const DOM_CHANNEL = 'codeception-webdriver-bidi-dom';

    /** How often at most the preload script reports that the DOM changed. */
    private const DOM_THROTTLE_MS = 50;

    /** How long one wait for running animations lasts at most. */
    private const ANIMATION_CAP_MS = 5000;

    /** How many times quiet is checked again, after animations ended or activity was heard while checking them. */
    private const ROUNDS = 5;

    private const OBSERVE_DOM = <<<'JS'
        (send) => {
            let last = 0;
            let pending = false;
            const notify = () => {
                const now = Date.now();
                if (now - last >= %1$d) { last = now; send(now); return; }
                if (pending) return;
                pending = true;
                setTimeout(() => { pending = false; last = Date.now(); send(last); }, %1$d);
            };
            new MutationObserver(notify).observe(document, {
                subtree: true, childList: true, attributes: true, characterData: true,
            });
            // What changes the page without a mutation: loads, the document's state, CSS animations and transitions.
            for (const type of [
                'load', 'readystatechange', 'pageshow',
                'animationstart', 'animationend', 'animationcancel',
                'transitionstart', 'transitionend', 'transitioncancel',
            ]) {
                addEventListener(type, notify, true);
            }
        }
        JS;

    private const AWAIT_ANIMATIONS = <<<'JS'
        () => {
            const running = document.getAnimations().filter((animation) =>
                animation.playState === 'running'
                && Number.isFinite(animation.effect?.getComputedTiming().endTime));
            const finished = Promise.all(running.map((animation) => animation.finished.catch(() => null)));
            const cap = new Promise((resolve) => setTimeout(resolve, %d));
            return Promise.race([finished, cap]).then(() => running.length);
        }
        JS;

    /** Seconds the browser's clock is ahead of this process's, which its events' times are converted by. */
    private float $clockOffset = 0.0;

    private function __construct(
        private readonly BiDi $bidi,
        private readonly Script $script,
        private readonly PageActivity $activity,
    ) {
    }

    /**
     * @param string $window any of the session's windows, to read the browser's clock in
     */
    public static function connect(string $webSocketUrl, string $window): self
    {
        $bidi = BiDi::connect($webSocketUrl);
        $session = new self($bidi, new Script($bidi), new PageActivity());
        $session->clockOffset = $session->readClockOffset($window);
        $session->watch(new Network($bidi), new BrowsingContextInspector($bidi));
        return $session;
    }

    /**
     * Waits until the window's network and DOM are quiet and its finite animations have ended, checking quiet
     * again after any animation it waited for. Returns false if the page is still busy after $timeout.
     *
     * Checking the animations is a round trip through the page, which delivers whatever the page did before it, so
     * quiet is checked again after it too.
     */
    public function waitUntilReady(string $window, float $timeout): bool
    {
        $deadline = microtime(true) + $timeout;
        $since = $this->now();
        $round = 0;
        do {
            if (!$this->waitUntilQuiet($window, $since, $deadline)) {
                return false;
            }
            $animated = $this->awaitAnimations($window, $deadline);
        } while (($animated || !$this->isQuiet($window, $since)) && ++$round < self::ROUNDS);
        return true;
    }

    /**
     * Waits until any page changes: a navigation starts, a request is sent or ends, a DOM changes, something loads, a
     * document's ready state changes, or a CSS animation or transition starts or ends. Returns false if nothing
     * changed in $timeout.
     */
    public function waitForChange(float $timeout): bool
    {
        $recorded = $this->activity->recorded();
        return $this->bidi->waitFor(fn (): bool => $this->activity->recorded() !== $recorded, $timeout);
    }

    public function drain(): void
    {
        if (!$this->bidi->isClosed()) {
            $this->bidi->drain();
        }
    }

    public function isClosed(): bool
    {
        return $this->bidi->isClosed();
    }

    public function close(): void
    {
        $this->bidi->close();
    }

    /**
     * @return string a user context, which keeps cookies, storage and a cache of its own
     */
    public function createUserContext(): string
    {
        return (new Browser($this->bidi))->createUserContext();
    }

    /**
     * Removes the user context and closes its windows. The session ends if they were its last.
     */
    public function removeUserContext(string $userContext): void
    {
        (new Browser($this->bidi))->removeUserContext($userContext);
    }

    /**
     * @return string the tab's window handle
     */
    public function openTab(string $userContext): string
    {
        $parameters = new CreateParameters(CreateType::TAB, userContext: $userContext);
        return BrowsingContext::create($this->bidi, $parameters)->getId();
    }

    public function closeWindow(string $window): void
    {
        (new BrowsingContext($this->bidi, $window))->close();
    }

    /**
     * The browser's clock against this process's, read once: the two may be on different machines.
     */
    private function readClockOffset(string $window): float
    {
        $sent = microtime(true);
        $result = $this->script->callFunctionInBrowsingContext($window, '() => Date.now()', false);
        $received = microtime(true);
        $milliseconds = $result instanceof EvaluationResultSuccess && $result->result instanceof NumberValue
            ? $result->result->value
            : null;
        if (!is_int($milliseconds) && !is_float($milliseconds)) {
            throw new BiDiException('The browser did not tell the time');
        }
        return $milliseconds / 1000 - ($sent + $received) / 2;
    }

    /**
     * Now, in seconds on the browser's clock, which the times of the page's activity are in.
     */
    private function now(): float
    {
        return microtime(true) + $this->clockOffset;
    }

    private function watch(Network $network, BrowsingContextInspector $contexts): void
    {
        $activity = $this->activity;
        $now = $this->now(...);
        $contexts->onBrowsingContextCreated(static function (ContextCreatedParameters $created) use ($activity): void {
            $activity->contextCreated($created->context, $created->parent);
        });
        $contexts->onNavigationStarted(static function (NavigationInfo $started) use ($activity): void {
            $activity->navigationStarted($started->context, $started->navigation);
        });
        $network->onBeforeRequestSent(static function (BeforeRequestSentParameters $sent) use ($activity): void {
            if ($sent->context !== null) {
                $at = $sent->timestamp / 1000;
                $activity->requestSent($sent->request->request, $sent->context, $at, $sent->navigation);
            }
        });
        $network->onResponseCompleted(static function (ResponseCompletedParameters $ended) use ($activity): void {
            // Chrome stamps the event with the time the request was sent, and its timings with when it ended.
            $timings = $ended->request->timings;
            if ($ended->context !== null) {
                $at = max($ended->timestamp, $timings->timeOrigin + $timings->responseEnd) / 1000;
                $activity->requestEnded($ended->request->request, $ended->context, $at);
            }
        });
        $network->onFetchError(static function (FetchErrorParameters $failed) use ($activity, $now): void {
            // Chrome tells no time a request failed at but the time it was sent, so it is taken as heard of.
            if ($failed->context !== null) {
                $activity->requestEnded($failed->request->request, $failed->context, $now());
            }
        });
        $this->script->onMessage(static function (MessageParameters $message) use ($activity, $now): void {
            if ($message->channel === self::DOM_CHANNEL && $message->source->context !== null) {
                $sent = $message->data instanceof NumberValue ? $message->data->value : null;
                $at = is_int($sent) || is_float($sent) ? $sent / 1000 : $now();
                $activity->domChanged($message->source->context, $at);
            }
        });
        $this->script->addPreloadScript(
            sprintf(self::OBSERVE_DOM, self::DOM_THROTTLE_MS),
            [new ChannelValue(new ChannelProperties(self::DOM_CHANNEL))],
        );
    }

    private function isQuiet(string $window, float $since): bool
    {
        return $this->activity->quietIn($window, $since, $this->now()) <= 0.0;
    }

    private function waitUntilQuiet(string $window, float $since, float $deadline): bool
    {
        while (($wait = $this->activity->quietIn($window, $since, $this->now())) > 0.0) {
            $left = $deadline - microtime(true);
            if ($left <= 0.0) {
                return false;
            }
            $recorded = $this->activity->recorded();
            $this->bidi->waitFor(fn (): bool => $this->activity->recorded() !== $recorded, min($wait, $left));
        }
        return true;
    }

    /**
     * Whether any finite animation was running, each awaited to its end or until the deadline.
     */
    private function awaitAnimations(string $window, float $deadline): bool
    {
        $cap = min(self::ANIMATION_CAP_MS, max(0, (int) (1000 * ($deadline - microtime(true)))));
        try {
            $result = $this->script->callFunctionInBrowsingContext(
                $window,
                sprintf(self::AWAIT_ANIMATIONS, $cap),
                true,
            );
        } catch (ErrorResponseException) {
            // The document went away while its animations were awaited, so the new one has to be checked.
            return true;
        }
        return $result instanceof EvaluationResultSuccess
            && $result->result instanceof NumberValue
            && is_int($result->result->value)
            && $result->result->value > 0;
    }
}
