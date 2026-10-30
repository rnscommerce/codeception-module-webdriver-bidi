<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Module;
use Codeception\Module\WebDriver;
use Codeception\Step;
use Codeception\TestInterface;

/**
 * Adds WebDriver BiDi to the sessions of the enabled WebDriver module, or of any module extending it.
 * It has to be listed after that module.
 */
class WebDriverBiDi extends Module
{
    private WebDriver $webDriver;

    private ?BiDiSession $session = null;

    private ?string $sessionId = null;

    public function _initialize(): void
    {
        $this->webDriver = $this->findWebDriver();
        $this->askForBiDi();
    }

    public function _before(TestInterface $test): void
    {
        $this->follow();
    }

    public function _afterStep(Step $step): void
    {
        $this->follow();
        $this->session?->drain();
    }

    public function _after(TestInterface $test): void
    {
        $this->askForBiDi();
    }

    public function _afterSuite(): void
    {
        $this->session?->close();
        $this->session = null;
        $this->sessionId = null;
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
        if ($driver === null) {
            return;
        }
        $webSocketUrl = $driver->getCapabilities()?->getCapability('webSocketUrl');
        if (!is_string($webSocketUrl)) {
            throw new ModuleException($this, 'The browser session has no webSocketUrl, so its driver lacks BiDi');
        }
        $this->session = BiDiSession::connect($webSocketUrl);
    }
}
