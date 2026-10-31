<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Module;
use Codeception\Module\WebDriver;

/**
 * Runs the WebDriver module's own assertions as a step of another name, so they are checked once.
 */
final class ClassicAssertions extends Module
{
    public function classicAssert(string $assertion, mixed ...$arguments): void
    {
        $webDriver = $this->getModule('WebDriver');
        assert($webDriver instanceof WebDriver);
        $webDriver->{$assertion}(...$arguments);
    }
}
