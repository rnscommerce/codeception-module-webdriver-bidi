<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Rnscommerce\WebDriver\BiDi\BiDi;

/**
 * The BiDi connection to one WebDriver session.
 */
final class BiDiSession
{
    private function __construct(private readonly BiDi $bidi)
    {
    }

    public static function connect(string $webSocketUrl): self
    {
        return new self(BiDi::connect($webSocketUrl));
    }

    public function drain(): void
    {
        if (!$this->bidi->isClosed()) {
            $this->bidi->drain();
        }
    }

    public function close(): void
    {
        $this->bidi->close();
    }
}
