<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Actions;

use Facebook\WebDriver\Exception\ElementClickInterceptedException;
use PHPUnit\Framework\Assert;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\ActionsTester;

final class ActionsCest
{
    public function anActionNamedInActionsWaitsForTheTransitionInItsWay(ActionsTester $I): void
    {
        $I->amOnPage('/transition.html?duration=1.5s');
        $I->click('#open');

        $I->listedClick('#confirm');

        Assert::assertSame('confirmed', $I->grabTextFrom('#result'));
    }

    public function anActionNamedInActionsFindsItsElementInTheArgumentNamed(ActionsTester $I): void
    {
        $I->amOnPage('/transition.html?duration=1.5s');
        $I->click('#open');

        $I->listedClickOn('the curtain has to open first', '#confirm');

        Assert::assertSame('confirmed', $I->grabTextFrom('#result'));
    }

    public function anActionNotInItActsAtOnce(ActionsTester $I): void
    {
        $I->amOnPage('/transition.html?duration=1.5s');
        $I->click('#open');

        try {
            $I->unlistedClick('#confirm');
            Assert::fail('#confirm was clicked before the curtain had opened');
        } catch (ElementClickInterceptedException) {
        }
    }
}
