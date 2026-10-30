<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Restart;

use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\BrowserTester;

/**
 * Each test has a browser session of its own, so each wait proves the module connected to that session.
 */
final class RestartCest
{
    public function waitsInTheFirstSession(BrowserTester $I): void
    {
        self::waitForAFetch($I);
    }

    public function waitsInTheNextSession(BrowserTester $I): void
    {
        self::waitForAFetch($I);
    }

    private static function waitForAFetch(BrowserTester $I): void
    {
        $I->amOnPage('/fetch.html');
        $I->click('#load');
        $I->waitForPageReady();

        Assert::assertSame('done', $I->grabTextFrom('#result'));
    }
}
