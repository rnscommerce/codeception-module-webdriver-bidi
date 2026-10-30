<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Module;
use Codeception\Module\WebDriver;
use Codeception\Step;
use Codeception\TestInterface;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\RemoteWebDriver;

/**
 * Adds WebDriver BiDi to the sessions of the enabled WebDriver module, or of any module extending it.
 * It has to be listed after that module.
 */
class WebDriverBiDi extends Module
{
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
        $this->follow();
        if ($this->userContext === null && $this->isolatesTests()) {
            $this->isolate();
        }
    }

    public function _afterStep(Step $step): void
    {
        $this->follow();
        $this->session?->drain();
    }

    public function _after(TestInterface $test): void
    {
        $this->follow();
        $this->renewUserContext();
        $this->askForBiDi();
    }

    public function _afterSuite(): void
    {
        $this->session?->close();
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
        $this->follow();
        $driver = $this->webDriver->webDriver;
        if ($this->session === null || $driver === null) {
            throw new ModuleException($this, 'There is no browser session to wait in');
        }
        $timeout ??= $this->configuredWait();
        if (!$this->session->waitUntilReady($driver->getWindowHandle(), (float) $timeout)) {
            throw new TimeoutException(sprintf('The page was still busy after %s seconds', $timeout));
        }
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
        if ($finished === null || $this->session === null || $driver === null) {
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

    private function configuredWait(): float
    {
        $wait = $this->webDriver->_getConfig('wait');
        return is_numeric($wait) ? (float) $wait : 0.0;
    }
}
