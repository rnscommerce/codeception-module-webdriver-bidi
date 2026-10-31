<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;

/**
 * An element an action acts on: how the WebDriver module finds it, and the checks it has to pass first.
 */
final readonly class ActionTarget
{
    /**
     * @param 'clickable'|'field'|'checkable'|'element' $finder
     * @param string|array<string, string>|WebDriverBy|WebDriverElement $locator
     * @param string|array<string, string>|WebDriverBy|null $context what click searches within
     * @param list<string> $checks
     */
    public function __construct(
        public string $finder,
        public string|array|WebDriverBy|WebDriverElement $locator,
        public string|array|WebDriverBy|null $context,
        public array $checks,
    ) {
    }
}
