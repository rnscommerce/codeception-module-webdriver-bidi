<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Assertions;

use Closure;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\AssertionsTester;

final class AssertionsCest
{
    public function seeWaitsForTextThatAppearsLate(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $I->see('Saved', '#notice');
    }

    public function seeElementWaitsForAnElementAScriptBuildsAfterLoad(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $I->seeElement('//span[contains(., "US-CA-*-Rate 1")]');
    }

    public function seeInFieldWaitsForAValueSetLate(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $I->seeInField('#name', 'Ada');
    }

    public function dontSeeWaitsForAnElementToBeRemoved(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $I->dontSee('Loading');
        $I->dontSeeElement('#spinner');
    }

    public function seeNumberOfElementsWaitsForTheCountToBeReached(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $I->seeNumberOfElements('#items li', 3);
    }

    public function seeInCurrentUrlWaitsForAScriptToChangeTheUrl(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $I->seeInCurrentUrl('saved=1');
    }

    public function anAssertionASuiteAddsRetriesThroughTheModule(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $I->seeInSecretField('#name', 'Ada');
    }

    public function anAssertionThatNeverHoldsFailsWithTheWebDriverModulesMessage(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $started = microtime(true);
        $retried = self::failure(static fn () => $I->see('Never', '#notice'));
        $elapsed = microtime(true) - $started;
        $classic = self::failure(static fn () => $I->classicAssert('see', 'Never', '#notice'));

        Assert::assertSame($classic->getMessage(), $retried->getMessage());
        Assert::assertGreaterThanOrEqual(2.0, $elapsed);
        Assert::assertLessThan(3.0, $elapsed);
    }

    public function anAssertionThatHoldsAtOnceDoesNotWait(AssertionsTester $I): void
    {
        $I->amOnPage('/late.html');

        $started = microtime(true);
        $I->see('Ready', '#ready');

        Assert::assertLessThan(0.2, microtime(true) - $started);
    }

    private static function failure(Closure $assertion): AssertionFailedError
    {
        try {
            $assertion();
        } catch (AssertionFailedError $e) {
            return $e;
        }
        Assert::fail('The assertion held');
    }
}
