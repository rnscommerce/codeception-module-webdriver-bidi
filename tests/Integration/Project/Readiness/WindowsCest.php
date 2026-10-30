<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Readiness;

use Facebook\WebDriver\Exception\TimeoutException;
use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\BrowserTester;

final class WindowsCest
{
    public function waitsInTheWindowSwitchedTo(BrowserTester $I): void
    {
        $I->amOnPage('/busy.html');
        try {
            $I->waitForPageReady(1);
            Assert::fail('A page that never stops fetching was ready');
        } catch (TimeoutException) {
        }

        $I->openNewTab();
        $I->amOnPage('/quiet.html');
        $started = microtime(true);
        $I->waitForPageReady();
        $elapsed = microtime(true) - $started;
        codecept_debug(sprintf('Ready after %.3f s', $elapsed));

        Assert::assertLessThan(0.5, $elapsed);
        $I->closeTab();
    }
}
