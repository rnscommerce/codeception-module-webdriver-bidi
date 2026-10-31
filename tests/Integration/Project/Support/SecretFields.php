<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration\Project\Support;

use Codeception\Module;
use Codeception\Module\WebDriver;
use Rnscommerce\Codeception\WebDriverBiDi\WebDriverBiDi;

/**
 * An assertion a suite adds, retried as the WebDriver module's are.
 */
final class SecretFields extends Module
{
    public function seeInSecretField(string $field, string $value): void
    {
        $webDriver = $this->getModule('WebDriver');
        $bidi = $this->getModule('\\' . WebDriverBiDi::class);
        assert($webDriver instanceof WebDriver && $bidi instanceof WebDriverBiDi);
        $bidi->_retryAssertion(static fn () => $webDriver->seeInField($field, $value));
    }
}
