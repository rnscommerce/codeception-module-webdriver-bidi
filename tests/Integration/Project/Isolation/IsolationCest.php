<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Isolation;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\BrowserTester;

/**
 * The tests share one browser and run in order: some leave cookies, storage and windows behind, and the test after
 * each starts without them. One fails on purpose, which CodeceptionTestCase expects.
 *
 * The WebDriver module clears the cookies of the page it is on after a test, so a cookie set on localhost, away
 * from that page, is one only a user context of its own keeps from the next test.
 *
 * It runs on Chrome only: in Firefox, tests are not isolated.
 */
final class IsolationCest
{
    /** @var array<mixed> */
    private static array $windowsLeft = [];

    public function leavesCookiesStorageAndWindowsBehind(BrowserTester $I): void
    {
        self::leaveThemBehind($I);
        $I->saveSessionSnapshot('left');
    }

    public function startsWithoutThem(BrowserTester $I): void
    {
        self::seeNoneOfThem($I);
    }

    public function failsWithThemLeftBehind(BrowserTester $I): void
    {
        self::leaveThemBehind($I);
        Assert::fail('This test fails on purpose');
    }

    public function startsWithoutThemAfterAFailure(BrowserTester $I): void
    {
        self::seeNoneOfThem($I);
    }

    public function waitsInItsOwnTab(BrowserTester $I): void
    {
        $I->amOnPage('/fetch.html');
        $I->click('#load');
        $I->waitForPageReady();

        Assert::assertSame('done', $I->grabTextFrom('#result'));
    }

    public function restoresASessionSnapshot(BrowserTester $I): void
    {
        $I->amOnPage('/quiet.html');
        $I->dontSeeCookie('left');

        $I->loadSessionSnapshot('left');

        $I->seeCookie('left');
    }

    private static function leaveThemBehind(BrowserTester $I): void
    {
        self::onLocalhost($I);
        $I->executeJS("document.cookie = 'left=behind; path=/'");
        $I->amOnPage('/quiet.html');
        $I->setCookie('left', 'behind');
        $I->executeJS("localStorage.setItem('left', 'behind')");
        $I->executeJS("window.open('/quiet.html')");
        $I->executeJS(<<<'JS'
            const link = Object.assign(document.createElement('a'), {id: 'blank', href: '/quiet.html'});
            link.target = '_blank';
            link.textContent = 'Open';
            document.body.append(link);
            JS);
        $I->click('#blank');
        $I->executeInSelenium(static function (RemoteWebDriver $driver): void {
            $driver->wait(5)->until(static fn (): bool => count($driver->getWindowHandles()) === 3);
            self::$windowsLeft = $driver->getWindowHandles();
        });
    }

    private static function seeNoneOfThem(BrowserTester $I): void
    {
        $I->seeNumberOfTabs(1);
        $window = $I->executeInSelenium(static fn (RemoteWebDriver $driver): string => $driver->getWindowHandle());
        Assert::assertNotContains($window, self::$windowsLeft);
        self::onLocalhost($I);
        $I->dontSeeCookie('left');
        Assert::assertNull($I->executeJS("return localStorage.getItem('left')"));
        $I->amOnPage('/quiet.html');
        $I->dontSeeCookie('left');
        Assert::assertNull($I->executeJS("return localStorage.getItem('left')"));
    }

    private static function onLocalhost(BrowserTester $I): void
    {
        $I->amOnPage('/quiet.html');
        $url = $I->executeJS('return location.href');
        Assert::assertIsString($url);
        $I->amOnUrl(str_replace('//127.0.0.1:', '//localhost:', $url));
    }
}
