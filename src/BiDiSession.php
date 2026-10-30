<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Rnscommerce\WebDriver\BiDi\BiDi;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\BrowsingContext;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\ContextCreatedParameters;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\CreateParameters;
use Rnscommerce\WebDriver\BiDi\BrowsingContext\CreateType;
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

    /** How many times quiet is checked again after animations ended. */
    private const ROUNDS = 5;

    private const OBSERVE_DOM = <<<'JS'
        (send) => {
            let last = 0;
            let pending = false;
            const notify = () => {
                const now = Date.now();
                if (now - last >= %1$d) { last = now; send(1); return; }
                if (pending) return;
                pending = true;
                setTimeout(() => { pending = false; last = Date.now(); send(1); }, %1$d);
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

    private function __construct(
        private readonly BiDi $bidi,
        private readonly Script $script,
        private readonly PageActivity $activity,
    ) {
    }

    public static function connect(string $webSocketUrl): self
    {
        $bidi = BiDi::connect($webSocketUrl);
        $session = new self($bidi, new Script($bidi), new PageActivity());
        $session->watch(new Network($bidi), new BrowsingContextInspector($bidi));
        return $session;
    }

    /**
     * Waits until the window's network and DOM are quiet and its finite animations have ended, checking quiet
     * again after any animation it waited for. Returns false if the page is still busy after $timeout.
     */
    public function waitUntilReady(string $window, float $timeout): bool
    {
        $deadline = microtime(true) + $timeout;
        $round = 0;
        do {
            if (!$this->waitUntilQuiet($window, $deadline)) {
                return false;
            }
        } while ($this->awaitAnimations($window, $deadline) && ++$round < self::ROUNDS);
        return true;
    }

    /**
     * Waits until any page changes: a request is sent or ends, a DOM changes, something loads, a document's ready
     * state changes, or a CSS animation or transition starts or ends. Returns false if nothing changed in $timeout.
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

    private function watch(Network $network, BrowsingContextInspector $contexts): void
    {
        $activity = $this->activity;
        $contexts->onBrowsingContextCreated(static function (ContextCreatedParameters $created) use ($activity): void {
            $activity->contextCreated($created->context, $created->parent);
        });
        $network->onBeforeRequestSent(static function (BeforeRequestSentParameters $sent) use ($activity): void {
            if ($sent->context !== null) {
                $activity->requestSent($sent->request->request, $sent->context, microtime(true));
            }
        });
        $ended = static function (ResponseCompletedParameters|FetchErrorParameters $ended) use ($activity): void {
            if ($ended->context !== null) {
                $activity->requestEnded($ended->request->request, $ended->context, microtime(true));
            }
        };
        $network->onResponseCompleted($ended);
        $network->onFetchError($ended);
        $this->script->onMessage(static function (MessageParameters $message) use ($activity): void {
            if ($message->channel === self::DOM_CHANNEL && $message->source->context !== null) {
                $activity->domChanged($message->source->context, microtime(true));
            }
        });
        $this->script->addPreloadScript(
            sprintf(self::OBSERVE_DOM, self::DOM_THROTTLE_MS),
            [new ChannelValue(new ChannelProperties(self::DOM_CHANNEL))],
        );
    }

    private function waitUntilQuiet(string $window, float $deadline): bool
    {
        $since = microtime(true);
        while (($wait = $this->activity->quietIn($window, $since, microtime(true))) > 0.0) {
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
