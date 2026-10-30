<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Restart;

use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\RestartTester;

/**
 * Each test has a browser session of its own, so each wait proves the module connected to that session.
 */
final class RestartCest
{
    public function waitsInTheFirstSession(RestartTester $I): void
    {
        self::waitForAFetch($I);
    }

    public function goesOnWithoutBiDiInASessionThatLacksIt(RestartTester $I): void
    {
        $I->restartTheBrowserWith(['window_size' => '1024x768']);
        $I->amOnPage('/waits.html?change=appear');

        $I->waitForPageReady();
        $I->click('#button');
        $I->waitForElement('#appeared');
    }

    public function waitsInTheNextSession(RestartTester $I): void
    {
        self::waitForAFetch($I);
    }

    private static function waitForAFetch(RestartTester $I): void
    {
        $I->amOnPage('/fetch.html');
        $I->click('#load');
        $I->waitForPageReady();

        Assert::assertSame('done', $I->grabTextFrom('#result'));
    }
}
