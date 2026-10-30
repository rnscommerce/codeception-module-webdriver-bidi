<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Quit;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support\BrowserTester;

/**
 * The tests share one browser, which the first quits. Its next step fails on purpose, with the WebDriver module's
 * error, which CodeceptionTestCase expects; the test after it still runs.
 */
final class QuitCest
{
    public function failsWithItsOwnErrorOnceTheBrowserHasQuit(BrowserTester $I): void
    {
        $I->amOnPage('/quiet.html');
        $I->executeInSelenium(static fn (RemoteWebDriver $driver) => $driver->close());

        $I->click('body');
    }

    public function runsAfterTheBrowserHasQuit(BrowserTester $I): void
    {
        codecept_debug('Ran after the browser had quit');
    }
}
