<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Facebook\WebDriver\WebDriverBy;

/**
 * The WebDriver module's assertions about the page, run by the WebDriver module, or by the module extending it, and
 * run again while they fail.
 *
 * Parameters are left untyped, as some modules extending the WebDriver module leave them.
 */
trait RetriedAssertions
{
    /**
     * @param list<mixed> $arguments
     */
    abstract private function retryAssertion(string $assertion, array $arguments): void;

    /**
     * The WebDriver module's see, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $text
     * @param string|array<string, string>|WebDriverBy|null $selector
     */
    public function see($text, $selector = null): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSee, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $text
     * @param string|array<string, string>|WebDriverBy|null $selector
     */
    public function dontSee($text, $selector = null): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeInSource, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $raw
     */
    public function seeInSource($raw): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeInSource, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $raw
     */
    public function dontSeeInSource($raw): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeInPageSource, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $text
     */
    public function seeInPageSource($text): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeInPageSource, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $text
     */
    public function dontSeeInPageSource($text): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeLink, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $text
     * @param string|null $url
     */
    public function seeLink($text, $url = null): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeLink, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $text
     * @param string $url
     */
    public function dontSeeLink($text, $url = ''): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeInCurrentUrl, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $uri
     */
    public function seeInCurrentUrl($uri): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeCurrentUrlEquals, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $uri
     */
    public function seeCurrentUrlEquals($uri): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeCurrentUrlMatches, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $uri
     */
    public function seeCurrentUrlMatches($uri): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeInCurrentUrl, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $uri
     */
    public function dontSeeInCurrentUrl($uri): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeCurrentUrlEquals, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $uri
     */
    public function dontSeeCurrentUrlEquals($uri): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeCurrentUrlMatches, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $uri
     */
    public function dontSeeCurrentUrlMatches($uri): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeInTitle, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $title
     */
    public function seeInTitle($title): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeInTitle, checked again while it fails, until assertion_timeout ends.
     *
     * @param string $title
     */
    public function dontSeeInTitle($title): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeCheckboxIsChecked, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $checkbox
     */
    public function seeCheckboxIsChecked($checkbox): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeCheckboxIsChecked, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $checkbox
     */
    public function dontSeeCheckboxIsChecked($checkbox): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeInField, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $field
     * @param mixed $value
     */
    public function seeInField($field, $value): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeInField, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $field
     * @param mixed $value
     */
    public function dontSeeInField($field, $value): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeInFormFields, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $formSelector
     * @param array<string, mixed> $params
     */
    public function seeInFormFields($formSelector, $params): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeInFormFields, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $formSelector
     * @param array<string, mixed> $params
     */
    public function dontSeeInFormFields($formSelector, $params): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeOptionIsSelected, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param string $optionText
     */
    public function seeOptionIsSelected($selector, $optionText): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeOptionIsSelected, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param string $optionText
     */
    public function dontSeeOptionIsSelected($selector, $optionText): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeElement, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param array<string, string> $attributes
     */
    public function seeElement($selector, $attributes = []): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeElement, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param array<string, string> $attributes
     */
    public function dontSeeElement($selector, $attributes = []): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeElementInDOM, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param array<string, string> $attributes
     */
    public function seeElementInDOM($selector, $attributes = []): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's dontSeeElementInDOM, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param array<string, string> $attributes
     */
    public function dontSeeElementInDOM($selector, $attributes = []): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeNumberOfElements, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param int|array{int, int} $expected
     */
    public function seeNumberOfElements($selector, $expected): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }

    /**
     * The WebDriver module's seeNumberOfElementsInDOM, checked again while it fails, until assertion_timeout ends.
     *
     * @param string|array<string, string>|WebDriverBy $selector
     * @param int|array{int, int} $expected
     */
    public function seeNumberOfElementsInDOM($selector, $expected): void
    {
        $this->retryAssertion(__FUNCTION__, func_get_args());
    }
}
