<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Waits;

use Facebook\WebDriver\WebDriverElement;
use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\WaitsTester;

/**
 * Each change comes 600 ms after the page loads, which a check every 250 ms would see only at 750 ms.
 */
final class WaitsCest
{
    /** How soon a wait returns after a change it hears of. */
    private const PROMPTLY = 0.2;

    /** How soon a wait returns after a change it cannot hear of: WebDriverBiDi's silence, and some slack. */
    private const EVENTUALLY = 0.5 + self::PROMPTLY;

    public function waitForElementReturnsAsTheElementAppears(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=appear');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElement('#appeared'));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function waitForElementVisibleReturnsAsTheElementIsShown(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=show');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElementVisible('#hidden'));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function waitForElementNotVisibleReturnsAsTheElementIsHidden(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=hide');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElementNotVisible('#shown'));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function waitForElementClickableReturnsAsTheButtonIsEnabled(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=enable');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElementClickable('#button'));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function waitForTextReturnsAsTheTextArrives(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=text');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForText('arrived'));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function waitForElementChangeReturnsAsTheElementChanges(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=attribute');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElementChange(
            '#shown',
            static fn (WebDriverElement $element): bool => $element->getAttribute('class') === 'changed',
        ));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function waitForJsReturnsSoonAfterTheRequestThatSetsItsState(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=fetched');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForJS('return window.flag === true;'));

        Assert::assertLessThan(0.1 + self::PROMPTLY, $lag);
    }

    public function hearsACssAnimationShowAnElement(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=animation');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElementVisible('#animated'));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function hearsAHoverShowAnElementAfterADelay(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=hover');
        $I->moveMouseOver('#menu');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElementVisible('#submenu'));

        Assert::assertLessThan(self::PROMPTLY, $lag);
    }

    public function seesAStyleSheetChangeItCannotHear(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=rule');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForElementVisible('#styled'));

        Assert::assertLessThan(self::EVENTUALLY, $lag);
    }

    public function seesJavaScriptStateItCannotHear(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html?change=timer');

        $lag = self::lagBehindThePage($I, static fn () => $I->waitForJS('return window.flag === true;'));

        Assert::assertLessThan(self::EVENTUALLY, $lag);
    }

    public function failsAsTheWebDriverModuleDoes(WaitsTester $I): void
    {
        $I->amOnPage('/waits.html');

        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForElement', '#never', 1);
        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForElementVisible', '#never', 1);
        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForElementNotVisible', '#shown', 1);
        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForElementClickable', '#button', 1);
        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForText', 'never', 1);
        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForText', 'never', 1, '#text');
        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForJS', 'return false;', 1);
        self::assertFailsAsTheWebDriverModuleDoes($I, 'waitForElementChange', '#shown', static fn (): bool => false, 1);
    }

    private static function lagBehindThePage(WaitsTester $I, callable $wait): float
    {
        $wait();
        $returned = microtime(true);
        $changedAt = $I->executeJS('return window.changedAt;');
        Assert::assertIsNumeric($changedAt, 'The wait returned before the page changed');
        $lag = $returned - $changedAt / 1000;
        codecept_debug(sprintf('Returned %.3f s after the page changed', $lag));
        return $lag;
    }

    private static function assertFailsAsTheWebDriverModuleDoes(
        WaitsTester $I,
        string $action,
        mixed ...$arguments,
    ): void {
        $classic = self::failure(static fn () => $I->classicWait($action, ...$arguments));
        $started = microtime(true);
        $failure = self::failure(static fn () => $I->{$action}(...$arguments));
        codecept_debug(sprintf('%s failed after %.3f s', $action, microtime(true) - $started));

        Assert::assertSame(
            [$classic::class, $classic->getMessage()],
            [$failure::class, $failure->getMessage()],
            $action,
        );
    }

    private static function failure(callable $wait): \Throwable
    {
        try {
            $wait();
        } catch (\Throwable $e) {
            return $e;
        }
        Assert::fail('The wait passed');
    }
}
