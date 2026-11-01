<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Module;
use Codeception\Module\WebDriver;
use Rnscommerce\Codeception\WebDriverBiDi\WebDriverBiDi;

/**
 * Actions a suite's own module adds, which click alike; listedClick and listedClickOn are named in actions, and
 * checkedInnerClick checks its click through the module.
 */
final class CustomActions extends Module
{
    public function listedClick(string $selector): void
    {
        $this->click($selector);
    }

    public function listedClickOn(string $note, string $selector): void
    {
        $this->click($selector);
    }

    public function unlistedClick(string $selector): void
    {
        $this->click($selector);
    }

    public function checkedInnerClick(string $selector): void
    {
        $bidi = $this->getModule('\\' . WebDriverBiDi::class);
        assert($bidi instanceof WebDriverBiDi);
        $bidi->_waitUntilActionable('click', [$selector]);
        $this->click($selector);
    }

    private function click(string $selector): void
    {
        $webDriver = $this->getModule('WebDriver');
        assert($webDriver instanceof WebDriver);
        $webDriver->click($selector);
    }
}
