<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rnscommerce\Codeception\WebDriverBiDi\Actionability;
use Rnscommerce\Codeception\WebDriverBiDi\ActionTarget;

final class ActionabilityTest extends TestCase
{
    private const POINTER = ['visible', 'stable', 'receivesEvents', 'enabled'];

    public function testAClickChecksItsLinkOrButtonWithinItsContext(): void
    {
        $targets = Actionability::configure([])->targets('click', ['Save', '#form']);

        self::assertEquals([new ActionTarget('clickable', 'Save', '#form', self::POINTER)], $targets);
    }

    public function testAFillChecksItsFieldIsEditable(): void
    {
        $targets = Actionability::configure([])->targets('fillField', [['name' => 'email'], 'ada@example.com']);

        self::assertEquals(
            [new ActionTarget('field', ['name' => 'email'], null, ['visible', 'enabled', 'editable'])],
            $targets,
        );
    }

    public function testADragChecksBothItsSourceAndItsTarget(): void
    {
        $targets = Actionability::configure([])->targets('dragAndDrop', ['#from', '#to']);

        self::assertSame(['#from', '#to'], array_map(static fn (ActionTarget $target) => $target->locator, $targets));
    }

    /**
     * @return iterable<string, array{string, list<mixed>}>
     */
    public static function noTargets(): iterable
    {
        yield 'typing, which acts on no element' => ['type', ['Hello']];
        yield 'a hover over no element' => ['moveMouseOver', [null, 20, 50]];
        yield 'an action that is not listed' => ['conditionalClick', ['#save', '#dialog', true]];
        yield 'an assertion' => ['see', ['Saved', '#result']];
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider('noTargets')]
    public function testNothingIsCheckedForAnActionWithoutAnElement(string $action, array $arguments): void
    {
        self::assertSame([], Actionability::configure([])->targets($action, $arguments));
    }

    public function testASuitesActionIsCheckedAsTheActionItIsLike(): void
    {
        $actionability = Actionability::configure([
            'fillSecretField' => ['like' => 'fillField'],
            'rapidClick' => ['like' => 'click'],
            'clickIn' => ['like' => 'click', 'argument' => 1],
        ]);

        self::assertEquals(
            [new ActionTarget('field', '#password', null, ['visible', 'enabled', 'editable'])],
            $actionability->targets('fillSecretField', ['#password', '{{secret}}']),
        );
        self::assertEquals(
            [new ActionTarget('clickable', '#add', null, self::POINTER)],
            $actionability->targets('rapidClick', ['#add', '3']),
        );
        self::assertEquals(
            [new ActionTarget('clickable', '#save', null, self::POINTER)],
            $actionability->targets('clickIn', ['#dialog', '#save']),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function misconfigured(): iterable
    {
        yield 'a list' => [['fillSecretField']];
        yield 'an action like no action' => [['rapidClick' => ['like' => 'tap']]];
        yield 'an argument that is no position' => [['rapidClick' => ['like' => 'click', 'argument' => '0']]];
    }

    #[DataProvider('misconfigured')]
    public function testAMisconfiguredActionIsRefused(mixed $configured): void
    {
        $this->expectException(InvalidArgumentException::class);

        Actionability::configure($configured);
    }
}
