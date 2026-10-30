<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Hooks;

use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\BrowserTester;

/**
 * The first two tests make a suite whose hooks start and close sessions of their own, as MFTF's do: the last of
 * them leaves the WebDriver module holding a session it has quit. The test after the suite still runs.
 */
final class SuiteHooksCest
{
    /**
     * @group suite
     */
    public function waitsInTheSuitesFirstTest(BrowserTester $I): void
    {
        self::waitForAFetch($I);
    }

    /**
     * @group suite
     */
    public function waitsInTheSuitesLastTest(BrowserTester $I): void
    {
        self::waitForAFetch($I);
    }

    public function waitsAfterTheSuite(BrowserTester $I): void
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
