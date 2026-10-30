<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rnscommerce\Codeception\WebDriverBiDi\BiDiCapabilities;

final class BiDiCapabilitiesTest extends TestCase
{
    public function testTheyAskForWebSocketUrl(): void
    {
        $capabilities = BiDiCapabilities::from(['browserName' => 'chrome']);

        self::assertSame(['browserName' => 'chrome', 'webSocketUrl' => true], $capabilities->toW3cCompatibleArray());
    }

    public function testAHelperChangesThemAsItWouldAnArray(): void
    {
        $capabilities = BiDiCapabilities::from(['browserName' => 'chrome', 'acceptInsecureCerts' => true]);

        $capabilities['bstack:options'] = ['sessionName' => 'test'];
        unset($capabilities['acceptInsecureCerts']);

        self::assertSame(['sessionName' => 'test'], $capabilities['bstack:options']);
        self::assertTrue(isset($capabilities['browserName']));
        self::assertFalse(isset($capabilities['acceptInsecureCerts']));
        self::assertSame(
            ['browserName' => 'chrome', 'acceptInsecureCerts' => null, 'bstack:options' => ['sessionName' => 'test'],
                'webSocketUrl' => true],
            $capabilities->toW3cCompatibleArray(),
        );
    }
}
