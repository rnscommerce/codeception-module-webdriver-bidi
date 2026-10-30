<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Module;
use Codeception\Module\WebDriver;

/**
 * Runs the WebDriver module's own waits, which WebDriverBiDi's replace in the actor.
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
