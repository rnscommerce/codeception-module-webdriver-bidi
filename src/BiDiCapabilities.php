<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Facebook\WebDriver\Remote\DesiredCapabilities;

/**
 * Capabilities that ask a new session for webSocketUrl, which php-webdriver's W3C conversion otherwise leaves out.
 */
final class BiDiCapabilities extends DesiredCapabilities
{
    /**
     * @param mixed $capabilities what the WebDriver module holds: its config's array, or capabilities a helper set
     */
    public static function from(mixed $capabilities): self
    {
        if ($capabilities instanceof self) {
            return $capabilities;
        }
        if ($capabilities instanceof DesiredCapabilities) {
            return new self($capabilities->toArray());
        }
        return new self(is_array($capabilities) ? $capabilities : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toW3cCompatibleArray(): array
    {
        /** @var array<string, mixed> $capabilities */
        $capabilities = parent::toW3cCompatibleArray();
        return $capabilities + ['webSocketUrl' => true];
    }
}
