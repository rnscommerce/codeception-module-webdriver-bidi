<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Closure;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Module;
use Codeception\Module\WebDriver;
use Codeception\Step;
use Codeception\TestInterface;
use Codeception\Util\Locator;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\PhpWebDriverExceptionInterface;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Rnscommerce\WebDriver\BiDi\BiDiException;

/**
 * Adds WebDriver BiDi to the sessions of the enabled WebDriver module, or of any module extending it.
 * It has to be listed after that module, which also makes its waits the ones Codeception runs.
 */
class WebDriverBiDi extends Module
{
    /** How soon a wait checks again after the page changed, for what the page's own code does next. */
    private const AFTER_CHANGE = 0.1;

    /** How long a wait goes without checking while nothing is seen to change on the page. */
    private const SILENCE = 0.5;

    /** The actions that act on the page, each taken only once the page is ready, or once READY_BEFORE_ACTING ends. */
    private const ACTIONS = [
        'appendField', 'attachFile', 'checkOption', 'clearField', 'click', 'clickWithLeftButton',
        'clickWithRightButton', 'doubleClick', 'dragAndDrop', 'fillField', 'moveMouseOver', 'pressKey',
        'selectOption', 'submitForm', 'type', 'uncheckOption', 'unselectOption',
    ];

    /** How long an action waits for the page to be ready before it acts on a page still busy. */
    private const READY_BEFORE_ACTING = 2.0;

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
        if (!in_array($step->getAction(), self::ACTIONS, true)) {
            return;
        }
        $this->tolerate(function () use ($session, $driver): void {
            if (!$session->waitUntilReady($driver->getWindowHandle(), self::READY_BEFORE_ACTING)) {
                $this->debugSection(
                    'BiDi',
                    sprintf('Acting on a page still busy after %s s', self::READY_BEFORE_ACTING),
                );
            }
        });
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
        [$driver, $session] = $this->browser();
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
        $this->until(static fn (): mixed => $callback($found), $timeout);
    }

    /**
     * The WebDriver module's waitForElement, checking again when the page changes rather than on an interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElement($element, int $timeout = 10): void
    {
        $this->until(WebDriverExpectedCondition::presenceOfElementLocated($this->locator($element)), $timeout);
    }

    /**
     * The WebDriver module's waitForElementVisible, checking again when the page changes rather than on an interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElementVisible($element, int $timeout = 10): void
    {
        $this->until(WebDriverExpectedCondition::visibilityOfElementLocated($this->locator($element)), $timeout);
    }

    /**
     * The WebDriver module's waitForElementNotVisible, checking again when the page changes rather than on an
     * interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElementNotVisible($element, int $timeout = 10): void
    {
        $this->until(WebDriverExpectedCondition::invisibilityOfElementLocated($this->locator($element)), $timeout);
    }

    /**
     * The WebDriver module's waitForElementClickable, checking again when the page changes rather than on an
     * interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy $element
     */
    public function waitForElementClickable($element, int $timeout = 10): void
    {
        $this->until(WebDriverExpectedCondition::elementToBeClickable($this->locator($element)), $timeout);
    }

    /**
     * The WebDriver module's waitForText, checking again when the page changes rather than on an interval.
     *
     * @param string|array<string, string>|\Facebook\WebDriver\WebDriverBy|null $selector
     */
    public function waitForText(string $text, int $timeout = 10, $selector = null): void
    {
        $this->until(
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
            static fn (RemoteWebDriver $driver): mixed => $driver->executeScript($script),
            $timeout,
            sprintf(
                "Waited for %d secs but script %s still doesn't evaluate to true",
                $timeout,
                Locator::humanReadableString($script),
            ),
        );
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
     * reach only the default user context's cookies.
     */
    private function isolatesTests(): bool
    {
        return !$this->webDriver->_getConfig('restart')
            && $this->webDriver->_getConfig('clear_cookies')
            && $this->webDriver->webDriver?->getCapabilities()?->getBrowserName() !== 'firefox';
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
            throw new ModuleException($this, 'The browser session has no webSocketUrl, so its driver lacks BiDi');
        }
        $this->session = BiDiSession::connect($webSocketUrl);
    }

    /**
     * @return array{RemoteWebDriver, BiDiSession}
     */
    private function browser(): array
    {
        $this->follow();
        $driver = $this->webDriver->webDriver;
        if ($this->session === null || $driver === null) {
            throw new ModuleException($this, 'There is no browser session to wait in');
        }
        return [$driver, $this->session];
    }

    /**
     * Checks the condition as WebDriverWait::until does, failing the same way, but checks it again only when the
     * page changes, once shortly after it stops changing, and otherwise after a silence, rather than every 250 ms.
     *
     * @param WebDriverExpectedCondition|Closure(RemoteWebDriver): mixed $condition
     */
    private function until(WebDriverExpectedCondition|Closure $condition, int $timeout, string $message = ''): void
    {
        [$driver, $session] = $this->browser();
        if ($timeout <= 0) {
            throw new TimeoutException($message);
        }
        $check = $condition instanceof WebDriverExpectedCondition ? $condition->getApply() : $condition;
        $deadline = microtime(true) + $timeout;
        $changed = true;
        $notFound = null;
        while (true) {
            try {
                if ($check($driver)) {
                    return;
                }
            } catch (NoSuchElementException $e) {
                $notFound = $e;
            }
            $left = $deadline - microtime(true);
            if ($left <= 0.0) {
                throw $notFound ?? new TimeoutException($message);
            }
            $changed = $session->waitForChange(min($left, $changed ? self::AFTER_CHANGE : self::SILENCE));
        }
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
