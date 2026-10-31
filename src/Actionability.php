<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use InvalidArgumentException;

/**
 * The elements each action acts on, and the checks each has to pass first: Playwright's, where WebDriver would
 * refuse the action or take it to no effect without them.
 */
final class Actionability
{
    public const VISIBLE = 'visible';
    public const STABLE = 'stable';
    public const ENABLED = 'enabled';
    public const EDITABLE = 'editable';
    public const RECEIVES_EVENTS = 'receivesEvents';

    /** What an element click needs, which WebDriver refuses or clicks to no effect without. */
    private const CLICK = [self::VISIBLE, self::STABLE, self::RECEIVES_EVENTS, self::ENABLED];

    /** A pointer acting through actions may show what it acts on as it arrives, so visibility is not asked for. */
    private const POINTER_CLICK = [self::STABLE, self::RECEIVES_EVENTS, self::ENABLED];
    private const HOVER = [self::STABLE, self::RECEIVES_EVENTS];
    private const TYPING = [self::VISIBLE, self::ENABLED, self::EDITABLE];

    /** WebDriver selects an option of a select it cannot see. */
    private const CHOOSING = [self::ENABLED];
    private const ATTACHED = [];

    /**
     * Each action's finder, its checks and the arguments that hold its elements.
     *
     * @var array<string, array{'clickable'|'field'|'checkable'|'element', list<string>, list<int>}>
     */
    private const ACTIONS = [
        'click' => ['clickable', self::CLICK, [0]],
        'doubleClick' => ['element', self::POINTER_CLICK, [0]],
        'clickWithLeftButton' => ['element', self::POINTER_CLICK, [0]],
        // Chrome opens a context menu on a disabled control.
        'clickWithRightButton' => ['element', self::HOVER, [0]],
        'checkOption' => ['checkable', self::CLICK, [0]],
        'uncheckOption' => ['checkable', self::CLICK, [0]],
        'moveMouseOver' => ['element', self::HOVER, [0]],
        'fillField' => ['field', self::TYPING, [0]],
        'appendField' => ['field', self::TYPING, [0]],
        'clearField' => ['field', self::TYPING, [0]],
        'selectOption' => ['field', self::CHOOSING, [0]],
        'unselectOption' => ['field', self::CHOOSING, [0]],
        'attachFile' => ['field', self::ATTACHED, [0]],
        'submitForm' => ['element', self::ATTACHED, [0]],
        'pressKey' => ['element', self::ATTACHED, [0]],
        'dragAndDrop' => ['element', self::HOVER, [0, 1]],
    ];

    /**
     * @param array<string, array{'clickable'|'field'|'checkable'|'element', list<string>, list<int>}> $actions
     */
    private function __construct(private readonly array $actions)
    {
    }

    /**
     * @param mixed $configured each of a suite's own actions, with the action it acts like and the argument that
     *     holds its element, 0 when not given
     * @throws InvalidArgumentException when the configuration does not have that shape
     */
    public static function configure(mixed $configured): self
    {
        if (!is_array($configured)) {
            throw new InvalidArgumentException('actions has to map action names to how they act');
        }
        $actions = self::ACTIONS;
        foreach ($configured as $name => $action) {
            $like = is_array($action) ? $action['like'] ?? null : null;
            $argument = is_array($action) ? $action['argument'] ?? 0 : null;
            if (!is_string($like) || !isset(self::ACTIONS[$like]) || !is_int($argument) || $argument < 0) {
                throw new InvalidArgumentException(sprintf(
                    'actions: %s needs like, naming one of %s, and may have argument, a position from 0',
                    $name,
                    implode(', ', array_keys(self::ACTIONS)),
                ));
            }
            [$finder, $checks] = self::ACTIONS[$like];
            $actions[(string) $name] = [$finder, $checks, [$argument]];
        }
        return new self($actions);
    }

    /**
     * @param array<mixed> $arguments
     * @return list<ActionTarget> what the action acts on, none when it acts on no element
     */
    public function targets(string $action, array $arguments): array
    {
        if (!isset($this->actions[$action])) {
            return [];
        }
        [$finder, $checks, $positions] = $this->actions[$action];
        // Only the WebDriver module's own click takes a context, which a suite's click-like action may not.
        $context = $action === 'click' ? self::locator($arguments[1] ?? null) : null;
        $context = $context instanceof WebDriverElement ? null : $context;
        $targets = [];
        foreach ($positions as $position) {
            $locator = self::locator($arguments[$position] ?? null);
            if ($locator !== null) {
                $targets[] = new ActionTarget($finder, $locator, $context, $checks);
            }
        }
        return $targets;
    }

    /**
     * @return string|array<string, string>|WebDriverBy|WebDriverElement|null
     */
    private static function locator(mixed $value): string|array|WebDriverBy|WebDriverElement|null
    {
        if (is_array($value) && count($value) === 1) {
            $mechanism = key($value);
            $locator = reset($value);
            return is_string($mechanism) && is_string($locator) ? [$mechanism => $locator] : null;
        }
        if ($value instanceof WebDriverBy || $value instanceof WebDriverElement) {
            return $value;
        }
        return is_string($value) && $value !== '' ? $value : null;
    }
}
