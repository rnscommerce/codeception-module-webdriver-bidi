<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration;

final class FirefoxTest extends CodeceptionTestCase
{
    protected static function unavailable(): ?string
    {
        $driver = getenv('GECKODRIVER');
        return is_string($driver) && is_executable($driver) ? null : 'GECKODRIVER does not name a geckodriver';
    }

    protected static function driverCommand(int $port): array
    {
        $webSocketPort = (string) Process::freePort();
        return [(string) getenv('GECKODRIVER'), '--port', (string) $port, '--websocket-port', $webSocketPort];
    }

    protected static function browser(): string
    {
        return 'firefox';
    }

    protected static function capabilities(): array
    {
        return ['moz:firefoxOptions' => ['args' => ['-headless']]];
    }

    public function testIsolatingEachTestInAUserContext(): void
    {
        self::markTestSkipped('In Firefox, tests are not isolated');
    }
}
