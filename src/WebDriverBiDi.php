<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Amp\Http\Client\HttpException;
use Closure;
use Codeception\Exception\ElementNotFound;
use Codeception\Exception\MalformedLocatorException;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Module;
use Codeception\Module\WebDriver;
use Codeception\Step;
use Codeception\TestInterface;
use Codeception\Util\Locator;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\PhpWebDriverExceptionInterface;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;
use Rnscommerce\WebDriver\BiDi\BiDiException;

/**
 * Adds WebDriver BiDi to the sessions of the enabled WebDriver module, or of any module extending it.
 * It has to be listed after that module, which also makes its waits the ones Codeception runs.
 */
class WebDriverBiDi extends Module
{
    use RetriedAssertions;

    /** How soon a wait checks again after the page changed, for what the page's own code does next. */
    private const AFTER_CHANGE = 0.1;

    /** How long a wait goes without checking while nothing is seen to change on the page. */
    private const SILENCE = 0.5;

    /** How long the page waits for an element to become actionable before it is found again. */
    private const RECHECK = 1.0;

    /** @var array<string, mixed> */
    protected array $config = ['action_timeout' => 10, 'actions' => [], 'assertion_timeout' => 5];

    private Actionability $actionability;

    private WebDriver $webDriver;

    private ?BiDiSession $session = null;

    private ?string $sessionId = null;

    /** The user context the WebDriver module's windows are in, replaced by a new one after each test. */
    private ?string $userContext = null;

    public function _initialize(): void
    {
        $this->webDriver = $this->findWebDriver();
        $this->askForBiDi();
    }

    public function _before(TestInterface $test): void
    {
        $this->tolerate(function (): void {
            $this->follow();
            if ($this->userContext === null && $this->isolatesTests()) {
                $this->isolate();
            }
        });
    }

    public function _beforeStep(Step $step): void
    {
        $driver = $this->webDriver->webDriver;
        $session = $this->session;
        if ($session === null || $session->isClosed() || $driver === null) {
            return;
        }
        $targets = $this->actionability->targets($step->getAction(), $step->getArguments());
        if ($targets === []) {
            return;
        }
        $timeout = $this->seconds('action_timeout');
        $deadline = microtime(true) + $timeout;
        $unmet = null;
        $this->tolerate(function () use ($session, $driver, $targets, $deadline, &$unmet): void {
            $window = $driver->getWindowHandle();
            foreach ($targets as $target) {
                $unmet ??= $this->waitUntilActionable($session, $window, $target, $deadline);
            }
        });
        if ($unmet !== null) {
            throw new TimeoutException(sprintf('%s waited %s s for %s', $step->getAction(), $timeout, $unmet));
        }
    }

    public function _afterStep(Step $step): void
    {
        $this->tolerate(function (): void {
            $this->follow();
            $this->session?->drain();
        });
    }

    public function _after(TestInterface $test): void
    {
        $this->tolerate($this->renewUserContext(...));
        // _reconfigure, as amOnSubdomain does, gives the WebDriver module its configured capabilities again.
        $this->askForBiDi();
    }

    public function _afterSuite(): void
    {
        $this->tolerate(fn () => $this->session?->close());
        $this->session = null;
        $this->sessionId = null;
    }

    /**
     * Whether the WebDriver module's browser session has BiDi, so that waitForPageReady and the waits listen to the
     * page rather than falling back to the WebDriver module's.
     */
    public function _hasBiDi(): bool
    {
        $this->follow();
        return $this->session !== null && !$this->session->isClosed();
    }

    /**
     * Runs an assertion, and runs it again whenever the page changes while it fails, until it holds or
     * assertion_timeout ends, then throws its last failure. Without BiDi, it runs it once.
     *
     * A suite's own module retries its assertions by passing them here.
     *
     * @param Closure(): mixed $assertion
     */
    public function _retryAssertion(Closure $assertion): void
    {
        $this->follow();
        $session = $this->session;
        $timeout = $this->seconds('assertion_timeout');
        if ($session === null || $session->isClosed() || $timeout <= 0.0) {
            $assertion();
            return;
        }
        $failure = null;
        $held = $this->poll($session, static function () use ($assertion, &$failure): bool {
            try {
                $assertion();
                return true;
            } catch (AssertionFailedError | StaleElementReferenceException $e) {
                $failure = $e;
                return false;
            }
        }, $timeout);
        if (!$held && $failure !== null) {
            throw $failure;
        }
    }

    /**
     * Waits until the current window has no request in flight, its DOM has stopped changing and its animations
     * have ended. A request older than ten seconds, such as a long poll, is not waited for.
     *
     * ```php
     * <?php
     * $I->click('Save');
     * $I->waitForPageReady();
     * ```
     *
     * @param int|float|null $timeout seconds, the WebDriver module's wait when not given
     */
    public function waitForPageReady(int|float|null $timeout = null): void
    {
        $browser = $this->browser();
        if ($browser === null) {
            return;
        }
        [$driver, $session] = $browser;
        $timeout ??= $this->configuredWait();
        if (!$session->waitUntilReady($driver->getWindowHandle(), (float) $timeout)) {
            throw new TimeoutException(sprintf('The page was still busy after %s seconds', $timeout));
        }
    }

    /**
     * The WebDriver module's waitForElementChange, checking again when the page changes rather than on an interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElementChange($element, Closure $callback, int $timeout = 30): void
    {
        $found = Closure::bind(
            static fn (WebDriver $module): WebDriverElement
                => $module->matchFirstOrFail($module->getBaseElement(), $element),
            null,
            WebDriver::class,
        )($this->webDriver);
        $this->until(
            fn () => $this->webDriver->waitForElementChange($element, $callback, $timeout),
            static fn (): mixed => $callback($found),
            $timeout,
        );
    }

    /**
     * The WebDriver module's waitForElement, checking again when the page changes rather than on an interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElement($element, int $timeout = 10): void
    {
        $this->until(
            fn () => $this->webDriver->waitForElement($element, $timeout),
            WebDriverExpectedCondition::presenceOfElementLocated($this->locator($element)),
            $timeout,
        );
    }

    /**
     * The WebDriver module's waitForElementVisible, checking again when the page changes rather than on an interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElementVisible($element, int $timeout = 10): void
    {
        $this->until(
            fn () => $this->webDriver->waitForElementVisible($element, $timeout),
            WebDriverExpectedCondition::visibilityOfElementLocated($this->locator($element)),
            $timeout,
        );
    }

    /**
     * The WebDriver module's waitForElementNotVisible, checking again when the page changes rather than on an
     * interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElementNotVisible($element, int $timeout = 10): void
    {
        $this->until(
            fn () => $this->webDriver->waitForElementNotVisible($element, $timeout),
            WebDriverExpectedCondition::invisibilityOfElementLocated($this->locator($element)),
            $timeout,
        );
    }

    /**
     * The WebDriver module's waitForElementClickable, checking again when the page changes rather than on an
     * interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElementClickable($element, int $timeout = 10): void
    {
        $this->until(
            fn () => $this->webDriver->waitForElementClickable($element, $timeout),
            WebDriverExpectedCondition::elementToBeClickable($this->locator($element)),
            $timeout,
        );
    }

    /**
     * The WebDriver module's waitForText, checking again when the page changes rather than on an interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy|null $selector
     */
    public function waitForText(string $text, int $timeout = 10, $selector = null): void
    {
        $this->until(
            fn () => $this->webDriver->waitForText($text, $timeout, $selector),
            WebDriverExpectedCondition::elementTextContains(
                $selector ? $this->locator($selector) : WebDriverBy::xpath('//body'),
                $text,
            ),
            $timeout,
            sprintf('Waited for %d secs but text %s still not found', $timeout, Locator::humanReadableString($text)),
        );
    }

    /**
     * The WebDriver module's waitForJS, checking again when the page changes rather than on an interval.
     */
    public function waitForJS(string $script, int $timeout = 5): void
    {
        $this->until(
            fn () => $this->webDriver->waitForJS($script, $timeout),
            static fn (RemoteWebDriver $driver): mixed => $driver->executeScript($script),
            $timeout,
            sprintf(
                "Waited for %d secs but script %s still doesn't evaluate to true",
                $timeout,
                Locator::humanReadableString($script),
            ),
        );
    }

    protected function validateConfig(): void
    {
        parent::validateConfig();
        foreach (['action_timeout', 'assertion_timeout'] as $timeout) {
            if (!is_numeric($this->config[$timeout]) || $this->config[$timeout] < 0) {
                throw new ModuleConfigException($this, "{$timeout} has to be a number of seconds");
            }
        }
        try {
            $this->actionability = Actionability::configure($this->config['actions']);
        } catch (InvalidArgumentException $e) {
            throw new ModuleConfigException($this, $e->getMessage());
        }
    }

    /**
     * Finds the target again whenever it leaves the page or the page waits RECHECK, until it passes its checks.
     * One never found by the deadline is left to the action, to fail as it does without BiDi.
     *
     * @return string|null the target and the check it still fails at the deadline, or null to act
     */
    private function waitUntilActionable(
        BiDiSession $session,
        string $window,
        ActionTarget $target,
        float $deadline,
    ): ?string {
        while (true) {
            $element = $this->find($target);
            $left = $deadline - microtime(true);
            if ($element === null) {
                if ($left <= 0.0) {
                    return null;
                }
                $session->waitForChange(min($left, self::SILENCE));
                continue;
            }
            if ($target->checks === []) {
                return null;
            }
            $state = $session->awaitActionable(
                $window,
                $element->getID(),
                $target->checks,
                max(0.0, min($left, self::RECHECK)),
            );
            if ($state === BiDiSession::READY) {
                return null;
            }
            if (microtime(true) >= $deadline) {
                return $state === BiDiSession::DETACHED
                    ? null
                    : sprintf('%s, which %s', $this->describe($target->locator), $state);
            }
        }
    }

    /**
     * Finds the target as its action does, without waiting for it.
     */
    private function find(ActionTarget $target): ?WebDriverElement
    {
        return Closure::bind(
            static function (WebDriver $module) use ($target): ?WebDriverElement {
                $locator = $target->locator;
                if ($locator instanceof WebDriverElement) {
                    return $locator;
                }
                // The WebDriver module waits implicitly for what it finds when its wait is set; this waits itself.
                $wait = $module->config['wait'];
                $module->config['wait'] = 0;
                try {
                    $driver = $module->webDriver;
                    if ($target->finder === 'clickable' && $driver !== null) {
                        $page = $target->context === null
                            ? $driver
                            : $module->matchFirstOrFail($driver, $target->context);
                        return $module->_findClickable($page, $locator) ?? $module->match($page, $locator)[0] ?? null;
                    }
                    return match ($target->finder) {
                        'field' => $module->findField($locator),
                        'checkable' => $module->findCheckable($module->getBaseElement(), $locator),
                        default => $module->matchFirstOrFail($module->getBaseElement(), $locator),
                    };
                } catch (ElementNotFound | MalformedLocatorException | NoSuchElementException) {
                    return null;
                } finally {
                    $module->config['wait'] = $wait;
                }
            },
            null,
            WebDriver::class,
        )($this->webDriver);
    }

    /**
     * @param string|array<string, string>|WebDriverBy|WebDriverElement $locator
     */
    private function describe(string|array|WebDriverBy|WebDriverElement $locator): string
    {
        return $locator instanceof WebDriverElement ? 'the element' : Locator::humanReadableString($locator);
    }

    /**
     * @param list<mixed> $arguments
     */
    private function retryAssertion(string $assertion, array $arguments): void
    {
        $webDriver = $this->webDriver;
        $this->_retryAssertion(static fn (): mixed => $webDriver->{$assertion}(...$arguments));
    }

    private function seconds(string $timeout): float
    {
        $seconds = $this->config[$timeout];
        return is_numeric($seconds) ? (float) $seconds : 0.0;
    }

    private function findWebDriver(): WebDriver
    {
        $found = null;
        foreach ($this->getModules() as $module) {
            if ($module === $this) {
                break;
            }
            if ($module instanceof WebDriver) {
                $found = $module;
            }
        }
        return $found ?? throw new ModuleConfigException(
            $this,
            'It needs the WebDriver module, or one extending it, enabled before it',
        );
    }

    private function askForBiDi(): void
    {
        $this->webDriver->_capabilities(BiDiCapabilities::from(...));
    }

    /**
     * Whether tests share a browser whose cookies the WebDriver module clears between them, which a user context
     * per test does for storage, the cache and windows as well. Not in Firefox, whose WebDriver cookie commands
     * reach only the default user context's cookies, nor where Chrome is told how to download, which it saves
     * nothing of in a user context.
     */
    private function isolatesTests(): bool
    {
        return !$this->webDriver->_getConfig('restart')
            && $this->webDriver->_getConfig('clear_cookies')
            && $this->webDriver->webDriver?->getCapabilities()?->getBrowserName() !== 'firefox'
            && !$this->configuresDownloads();
    }

    private function configuresDownloads(): bool
    {
        $capabilities = $this->webDriver->_getConfig('capabilities');
        $options = is_array($capabilities) ? $capabilities['goog:chromeOptions'] ?? null : null;
        $prefs = is_array($options) ? $options['prefs'] ?? null : null;
        $names = is_array($prefs) ? array_keys($prefs) : [];
        return array_filter($names, static fn (int|string $name): bool => str_starts_with((string) $name, 'download'))
            !== [];
    }

    /**
     * Moves the WebDriver module from its window in the default user context to a tab in a user context of its own.
     */
    private function isolate(): void
    {
        $driver = $this->webDriver->webDriver;
        if ($this->session === null || $driver === null) {
            return;
        }
        $window = $driver->getWindowHandle();
        $this->userContext = $this->openUserContextTab($this->session, $driver);
        $this->session->closeWindow($window);
    }

    /**
     * Moves the WebDriver module to a tab in a new user context, ready for the next test, and removes the finished
     * test's user context with its windows.
     */
    private function renewUserContext(): void
    {
        $driver = $this->webDriver->webDriver;
        $finished = $this->userContext;
        if ($finished === null || $this->session === null || $this->session->isClosed() || $driver === null) {
            return;
        }
        if ($driver->getSessionID() !== $this->sessionId) {
            return;
        }
        $this->userContext = $this->openUserContextTab($this->session, $driver);
        $this->session->removeUserContext($finished);
    }

    /**
     * @return string the user context the WebDriver module is now in
     */
    private function openUserContextTab(BiDiSession $session, RemoteWebDriver $driver): string
    {
        $userContext = $session->createUserContext();
        $driver->switchTo()->window($session->openTab($userContext));
        return $userContext;
    }

    /**
     * Runs a hook's work, debugging what the browser or the BiDi connection fails with rather than throwing it, which
     * would hide a step's own error, or end the run when thrown after a test.
     */
    private function tolerate(Closure $work): void
    {
        try {
            $work();
        } catch (BiDiException | PhpWebDriverExceptionInterface $e) {
            $this->debugSection('BiDi', sprintf('%s: %s', $e::class, $e->getMessage()));
        }
    }

    /**
     * Connects to the WebDriver module's session when it has started a new one.
     */
    private function follow(): void
    {
        $driver = $this->webDriver->webDriver;
        $sessionId = $driver?->getSessionID();
        if ($sessionId === $this->sessionId) {
            return;
        }
        $this->session?->close();
        $this->session = null;
        $this->sessionId = $sessionId;
        $this->userContext = null;
        if ($driver === null) {
            return;
        }
        $webSocketUrl = $driver->getCapabilities()?->getCapability('webSocketUrl');
        if (!is_string($webSocketUrl)) {
            $this->debugSection('BiDi', 'The browser session has no webSocketUrl, so it goes on without BiDi');
            return;
        }
        try {
            $this->session = BiDiSession::connect($webSocketUrl, $driver->getWindowHandle());
        } catch (HttpException | BiDiException $e) {
            $this->debugSection('BiDi', 'Going on without BiDi, which failed to connect: ' . $e->getMessage());
        }
    }

    /**
     * @return array{RemoteWebDriver, BiDiSession}|null null when the browser session goes on without BiDi
     */
    private function browser(): ?array
    {
        $this->follow();
        $driver = $this->webDriver->webDriver;
        if ($driver === null) {
            throw new ModuleException($this, 'There is no browser session to wait in');
        }
        if ($this->session === null || $this->session->isClosed()) {
            return null;
        }
        return [$driver, $this->session];
    }

    /**
     * Checks the condition as WebDriverWait::until does, failing the same way, but checks it again only when the
     * page changes, once shortly after it stops changing, and otherwise after a silence, rather than every 250 ms.
     * Without BiDi, it runs the WebDriver module's own wait instead.
     *
     * @param Closure(): void $classic
     * @param WebDriverExpectedCondition|Closure(RemoteWebDriver): mixed $condition
     */
    private function until(
        Closure $classic,
        WebDriverExpectedCondition|Closure $condition,
        int $timeout,
        string $message = '',
    ): void {
        $browser = $this->browser();
        if ($browser === null) {
            $classic();
            return;
        }
        [$driver, $session] = $browser;
        if ($timeout <= 0) {
            throw new TimeoutException($message);
        }
        $check = $condition instanceof WebDriverExpectedCondition ? $condition->getApply() : $condition;
        $notFound = null;
        $held = $this->poll($session, static function () use ($check, $driver, &$notFound): bool {
            try {
                return (bool) $check($driver);
            } catch (NoSuchElementException $e) {
                $notFound = $e;
                return false;
            }
        }, $timeout);
        if (!$held) {
            throw $notFound ?? new TimeoutException($message);
        }
    }

    /**
     * Checks once, then again only when the page changes, once shortly after it stops changing, and otherwise after
     * a silence, until the check holds or the timeout ends.
     *
     * @param Closure(): bool $check
     */
    private function poll(BiDiSession $session, Closure $check, float $timeout): bool
    {
        $deadline = microtime(true) + $timeout;
        $changed = true;
        while (!$check()) {
            $left = $deadline - microtime(true);
            if ($left <= 0.0) {
                return false;
            }
            $changed = $session->waitForChange(min($left, $changed ? self::AFTER_CHANGE : self::SILENCE));
        }
        return true;
    }

    /**
     * @param string|array<string, string>|WebDriverBy $selector
     */
    private function locator($selector): WebDriverBy
    {
        return Closure::bind(
            static fn (WebDriver $module): WebDriverBy => $module->getLocator($selector),
            null,
            WebDriver::class,
        )($this->webDriver);
    }

    private function configuredWait(): float
    {
        $wait = $this->webDriver->_getConfig('wait');
        return is_numeric($wait) ? (float) $wait : 0.0;
    }
}
