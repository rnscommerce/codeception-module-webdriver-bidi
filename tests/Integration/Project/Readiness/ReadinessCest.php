<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Readiness;

use Facebook\WebDriver\Exception\ElementClickInterceptedException;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\BrowserTester;

final class ReadinessCest
{
    public function waitsForASlowFetchAndTheDomItUpdates(BrowserTester $I): void
    {
        $I->amOnPage('/fetch.html');
        $I->click('#load');

        $elapsed = self::timeReadiness($I);

        Assert::assertSame('done', $I->grabTextFrom('#result'));
        Assert::assertGreaterThan(0.9, $elapsed);
    }

    public function waitsForATransitionToEnd(BrowserTester $I): void
    {
        $I->amOnPage('/transition.html');
        $I->click('#open');

        $elapsed = self::timeReadiness($I);
        $I->click('#confirm');

        Assert::assertSame('confirmed', $I->grabTextFrom('#result'));
        Assert::assertGreaterThan(1.8, $elapsed);
    }

    public function withoutWaitingTheTransitionIsInTheWay(BrowserTester $I): void
    {
        $I->amOnPage('/transition.html?duration=1.5s');
        $I->click('#open');

        try {
            $I->executeInSelenium(
                static fn (RemoteWebDriver $driver) => $driver->findElement(WebDriverBy::id('confirm'))->click(),
            );
            Assert::fail('#confirm was clicked before the curtain had opened');
        } catch (ElementClickInterceptedException) {
        }
    }

    public function aClickWaitsForTheTransitionInItsWay(BrowserTester $I): void
    {
        $I->amOnPage('/transition.html?duration=1.5s');
        $I->click('#open');

        $I->click('#confirm');

        Assert::assertSame('confirmed', $I->grabTextFrom('#result'));
    }

    public function aClickOnAPageThatNeverSettlesWaitsOnlySoLong(BrowserTester $I): void
    {
        $I->amOnPage('/perpetual.html');

        $started = microtime(true);
        $I->click('#go');
        $elapsed = microtime(true) - $started;
        codecept_debug(sprintf('Clicked after %.3f s', $elapsed));

        Assert::assertSame('clicked', $I->grabTextFrom('#result'));
        Assert::assertGreaterThan(1.9, $elapsed);
        Assert::assertLessThan(2.5, $elapsed);
    }

    public function doesNotWaitForARequestOnceItIsStale(BrowserTester $I): void
    {
        $I->amOnPage('/longpoll.html');

        $elapsed = self::timeReadiness($I, 15);

        Assert::assertSame('polling', $I->grabTextFrom('#result'));
        Assert::assertGreaterThan(8.5, $elapsed);
        Assert::assertLessThan(12.0, $elapsed);
    }

    public function returnsPromptlyOnAQuietPage(BrowserTester $I): void
    {
        $I->amOnPage('/quiet.html');

        Assert::assertLessThan(0.5, self::timeReadiness($I));
    }

    private static function timeReadiness(BrowserTester $I, ?float $timeout = null): float
    {
        $started = microtime(true);
        $I->waitForPageReady($timeout);
        $elapsed = microtime(true) - $started;
        codecept_debug(sprintf('Ready after %.3f s', $elapsed));
        return $elapsed;
    }
}
