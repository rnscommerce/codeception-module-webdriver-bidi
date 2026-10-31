<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Module;
use Codeception\Module\WebDriver;

/**
 * Two actions a suite's own module adds, which click alike; only listedClick is in wait_before_actions.
 */
final class CustomActions extends Module
{
    public function listedClick(string $selector): void
    {
        $this->click($selector);
    }

    public function unlistedClick(string $selector): void
    {
        $this->click($selector);
    }

    private function click(string $selector): void
    {
        $webDriver = $this->getModule('WebDriver');
        assert($webDriver instanceof WebDriver);
        $webDriver->click($selector);
    }
}
