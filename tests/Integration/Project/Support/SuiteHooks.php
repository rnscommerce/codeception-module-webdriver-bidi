<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Event\TestEvent;
use Codeception\GroupObject;
use Codeception\Module\WebDriver;

/**
 * What the class MFTF generates for a suite with before and after hooks does around the suite's tests.
 */
final class SuiteHooks extends GroupObject
{
    /** @var string */
    public static $group = 'suite';

    private const TESTS = 2;

    private int $run = 0;

    public function _before(TestEvent $event): void
    {
        if (++$this->run !== 1) {
            return;
        }
        $webDriver = $this->startSession();
        $webDriver->_resetConfig();
        $webDriver->webDriver?->close();
        $webDriver->webDriver = null;
    }

    public function _after(TestEvent $event): void
    {
        if ($this->run !== self::TESTS) {
            return;
        }
        $this->startSession()->_closeSession();
    }

    private function startSession(): WebDriver
    {
        $webDriver = $this->getModule('WebDriver');
        assert($webDriver instanceof WebDriver);
        if ($webDriver->webDriver !== null) {
            $webDriver->_restart();
        } else {
            $webDriver->_initializeSession();
        }
        $webDriver->amOnPage('/quiet.html');
        return $webDriver;
    }
}
