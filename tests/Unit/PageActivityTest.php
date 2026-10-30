<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rnscommerce\Codeception\WebDriverBiDi\PageActivity;

final class PageActivityTest extends TestCase
{
    private PageActivity $activity;

    protected function setUp(): void
    {
        $this->activity = new PageActivity();
    }

    public function testAQuietPageIsReadyOnceTheQuietPeriodHasPassed(): void
    {
        self::assertEqualsWithDelta(0.1, $this->activity->quietIn('w', 5.0, 5.0), 1e-9);
        self::assertEqualsWithDelta(0.04, $this->activity->quietIn('w', 5.0, 5.06), 1e-9);
        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 5.0, 5.1), 1e-9);
    }

    public function testARequestInFlightIsWaitedForUntilItEnds(): void
    {
        $this->activity->requestSent('r', 'w', 1.0);

        self::assertEqualsWithDelta(9.0, $this->activity->quietIn('w', 1.0, 2.0), 1e-9);

        $this->activity->requestEnded('r', 'w', 2.5);

        self::assertEqualsWithDelta(0.1, $this->activity->quietIn('w', 1.0, 2.5), 1e-9);
        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 1.0, 2.6), 1e-9);
    }

    public function testARequestStopsCountingOnceItIsStale(): void
    {
        $this->activity->requestSent('r', 'w', 0.0);

        self::assertEqualsWithDelta(0.5, $this->activity->quietIn('w', 9.0, 9.5), 1e-9);
        self::assertEqualsWithDelta(0.1, $this->activity->quietIn('w', 10.0, 10.0), 1e-9);
        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 10.0, 10.1), 1e-9);
    }

    public function testTheDomHasToBeQuietToo(): void
    {
        $this->activity->domChanged('w', 0.15);

        self::assertEqualsWithDelta(0.05, $this->activity->quietIn('w', 0.0, 0.2), 1e-9);
        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 0.0, 0.25), 1e-9);
    }

    public function testADomThatNeverStopsChangingIsNoLongerWaitedForOnceTheNetworkHasBeenQuietLongEnough(): void
    {
        for ($at = 0.0; $at < 0.9; $at += 0.05) {
            $this->activity->domChanged('w', $at);
        }
        $this->activity->domChanged('w', 0.95);

        self::assertEqualsWithDelta(0.05, $this->activity->quietIn('w', 0.0, 0.95), 1e-9);

        $this->activity->domChanged('w', 0.99);

        self::assertEqualsWithDelta(0.01, $this->activity->quietIn('w', 0.0, 0.99), 1e-9);
        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 0.0, 1.0), 1e-9);
    }

    public function testAnotherWindowsActivityDoesNotCount(): void
    {
        $this->activity->requestSent('r', 'other', 0.0);
        $this->activity->domChanged('other', 0.0);

        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 0.0, 0.1), 1e-9);
        self::assertGreaterThan(0.0, $this->activity->quietIn('other', 0.0, 0.1));
    }

    public function testEveryRequestAndDomChangeIsCounted(): void
    {
        $this->activity->contextCreated('frame', 'w');
        $this->activity->requestSent('r', 'w', 0.0);
        $this->activity->requestEnded('r', 'w', 0.1);
        $this->activity->domChanged('frame', 0.2);

        self::assertSame(3, $this->activity->recorded());
    }

    public function testAnIframesActivityCountsForItsWindow(): void
    {
        $this->activity->contextCreated('frame', 'w');
        $this->activity->contextCreated('nested', 'frame');
        $this->activity->requestSent('r', 'nested', 0.0);

        self::assertGreaterThan(0.0, $this->activity->quietIn('w', 0.0, 0.5));

        $this->activity->requestEnded('r', 'nested', 0.5);
        $this->activity->domChanged('frame', 0.55);

        self::assertEqualsWithDelta(0.05, $this->activity->quietIn('w', 0.0, 0.6), 1e-9);
        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 0.0, 0.65), 1e-9);
    }

    public function testAWindowsNavigationForgetsEveryRequestButItsOwn(): void
    {
        $this->activity->contextCreated('frame', 'w');
        $this->activity->requestSent('r', 'w', 0.0);
        $this->activity->requestSent('f', 'frame', 0.0);
        $this->activity->requestSent('document', 'w', 0.5, 'n');
        $this->activity->navigationStarted('w', 'n');

        self::assertGreaterThan(0.0, $this->activity->quietIn('w', 0.0, 1.0));

        $this->activity->requestEnded('document', 'w', 1.0);

        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 0.0, 1.1), 1e-9);
    }

    public function testAnIframesNavigationForgetsOnlyItsRequests(): void
    {
        $this->activity->contextCreated('frame', 'w');
        $this->activity->requestSent('r', 'w', 0.0);
        $this->activity->requestSent('f', 'frame', 0.0);
        $this->activity->navigationStarted('frame', 'n');
        $this->activity->requestEnded('r', 'w', 1.0);

        self::assertEqualsWithDelta(0.0, $this->activity->quietIn('w', 0.0, 1.1), 1e-9);

        $this->activity->requestSent('r2', 'w', 2.0);
        $this->activity->navigationStarted('frame', 'n2');

        self::assertGreaterThan(1.0, $this->activity->quietIn('w', 0.0, 2.5));
    }
}
