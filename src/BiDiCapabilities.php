<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use ArrayAccess;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use InvalidArgumentException;

/**
 * Capabilities that ask a new session for webSocketUrl, which php-webdriver's W3C conversion otherwise leaves out.
 * They read and write as an array does at their top level, as a helper's _capabilities function may expect.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class BiDiCapabilities extends DesiredCapabilities implements ArrayAccess
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

    public function offsetExists(mixed $offset): bool
    {
        return $this->getCapability($offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getCapability($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setCapability($offset ?? throw new InvalidArgumentException('A capability is set by its name'), $value);
    }

    /**
     * Sets the capability to null, which a W3C session leaves out as if it were not there.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->setCapability($offset, null);
    }
}
