<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Actionability;

use Facebook\WebDriver\Exception\TimeoutException;
use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\BrowserTester;

final class ActionabilityCest
{
    public function aClickWaitsForTheOverlayCoveringItsElementToGo(BrowserTester $I): void
    {
        $I->amOnPage('/covered.html');

        $elapsed = self::time(static fn () => $I->click('#save'));

        Assert::assertSame('saved', $I->grabTextFrom('#result'));
        Assert::assertGreaterThan(1.2, $elapsed);
    }

    public function aClickWaitsForItsElementToBeEnabled(BrowserTester $I): void
    {
        $I->amOnPage('/disabled.html');

        $I->click('#save');

        Assert::assertSame('saved', $I->grabTextFrom('#result'));
    }

    public function aClickWaitsForItsElementToStopMoving(BrowserTester $I): void
    {
        $I->amOnPage('/moving.html');

        $I->click('#save');

        Assert::assertSame('saved', $I->grabTextFrom('#result'));
    }

    public function aClickFindsItsElementAgainWhenARenderReplacesIt(BrowserTester $I): void
    {
        $I->amOnPage('/replaced.html');

        $elapsed = self::time(static fn () => $I->click('#save'));

        Assert::assertSame('saved by render 2', $I->grabTextFrom('#result'));
        Assert::assertLessThan(2.0, $elapsed);
    }

    public function aFillWaitsForItsFieldToBeEditable(BrowserTester $I): void
    {
        $I->amOnPage('/readonly.html');

        $I->fillField('#name', 'Ada');

        Assert::assertSame('Ada', $I->grabValueFrom('#name'));
    }

    public function aClickInAFrameWaitsForTheOverlayCoveringItsElementToGo(BrowserTester $I): void
    {
        $I->amOnPage('/framed.html');
        $I->switchToIFrame('#frame');

        $I->click('#save');

        Assert::assertSame('saved', $I->grabTextFrom('#result'));
    }

    public function aClickOnAnElementThatIsNeverReadyFailsSayingWhy(BrowserTester $I): void
    {
        $I->amOnPage('/covered.html?forever');

        $started = microtime(true);
        try {
            $I->click('#save');
            Assert::fail('#save was clicked through the overlay');
        } catch (TimeoutException $e) {
            $elapsed = microtime(true) - $started;
            Assert::assertSame(
                "click waited 3 s for '#save', which is covered by <div class=\"modal-overlay\"></div>",
                $e->getMessage(),
            );
            Assert::assertGreaterThan(2.9, $elapsed);
            Assert::assertLessThan(4.0, $elapsed);
        }
    }

    public function anActionOnAnElementAlreadyActionableDoesNotWait(BrowserTester $I): void
    {
        $I->amOnPage('/perpetual.html');

        $elapsed = self::time(static fn () => $I->click('#go'));

        Assert::assertSame('clicked', $I->grabTextFrom('#result'));
        Assert::assertLessThan(0.2, $elapsed);
    }

    public function aClickInATabLeftInTheBackgroundDoesNotWaitForFrames(BrowserTester $I): void
    {
        $I->amOnPage('/perpetual.html');
        $I->executeJS("window.open('/quiet.html')");

        $elapsed = self::time(static fn () => $I->click('#go'));

        Assert::assertSame('clicked', $I->grabTextFrom('#result'));
        Assert::assertLessThan(1.0, $elapsed);
    }

    private static function time(callable $action): float
    {
        $started = microtime(true);
        $action();
        $elapsed = microtime(true) - $started;
        codecept_debug(sprintf('Acted after %.3f s', $elapsed));
        return $elapsed;
    }
}
