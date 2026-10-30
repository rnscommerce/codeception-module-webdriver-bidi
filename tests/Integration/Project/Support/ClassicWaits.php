<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Module;
use Codeception\Module\WebDriver;

/**
 * Runs the WebDriver module's own actions as a step of another name, so WebDriverBiDi neither replaces its waits
 * nor waits for the page before it acts.
 */
final class ClassicWaits extends Module
{
    public function classicWait(string $action, mixed ...$arguments): void
    {
        $webDriver = $this->getModule('WebDriver');
        assert($webDriver instanceof WebDriver);
        $webDriver->{$action}(...$arguments);
    }
}
