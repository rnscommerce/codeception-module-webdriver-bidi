<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Module;
use Codeception\Module\WebDriver;

/**
 * Restarts the browser in the middle of a test, as a helper may.
 */
final class Restarts extends Module
{
    /**
     * @param array<string, mixed> $config the WebDriver module's, which also gives it its configured capabilities
     */
    public function restartTheBrowserWith(array $config): void
    {
        $webDriver = $this->getModule('WebDriver');
        assert($webDriver instanceof WebDriver);
        $webDriver->_restart($config);
    }
}
