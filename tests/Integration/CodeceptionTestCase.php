<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Runs each suite of the Codeception project in Project/ against a browser, with a driver and a fixture server
 * started for the test class.
 */
abstract class CodeceptionTestCase extends TestCase
{
    private static ?Process $driver = null;

    private static ?Process $server = null;

    /**
     * @return string|null why this browser cannot run here, or null when it can
     */
    abstract protected static function unavailable(): ?string;

    /**
     * @return list<string>
     */
    abstract protected static function driverCommand(int $port): array;

    abstract protected static function browser(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected static function capabilities(): array;

    public static function setUpBeforeClass(): void
    {
        $reason = static::unavailable();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$server = Process::start(
            static fn (int $port): array => [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', __DIR__ . '/fixtures'],
            '/quiet.html',
            // Fixture requests that take their time must not hold up the others.
            ['PHP_CLI_SERVER_WORKERS' => '8'],
        );
        self::$driver = Process::start(static::driverCommand(...), '/status');
    }

    public static function tearDownAfterClass(): void
    {
        self::$driver?->stop();
        self::$server?->stop();
        self::$driver = self::$server = null;
    }

    public function testReadiness(): void
    {
        $this->runSuite('Readiness');
    }

    public function testRestartingTheBrowserForEachTest(): void
    {
        $this->runSuite('Restart');
    }

    private function runSuite(string $suite): void
    {
        $server = self::$server ?? throw new \LogicException('No server');
        $driver = self::$driver ?? throw new \LogicException('No driver');
        $webDriver = json_encode([
            'url' => "http://127.0.0.1:{$server->port}/",
            'host' => '127.0.0.1',
            'port' => $driver->port,
            'browser' => static::browser(),
            'capabilities' => static::capabilities(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $output = tmpfile();
        self::assertIsResource($output);
        $process = proc_open(
            [
                PHP_BINARY,
                dirname(__DIR__, 2) . '/vendor/bin/codecept',
                'run',
                $suite,
                '--config',
                __DIR__ . '/Project',
                '--debug',
                '--no-colors',
                '--override',
                "modules: config: WebDriver: $webDriver",
            ],
            [0 => ['file', '/dev/null', 'r'], 1 => $output, 2 => $output],
            $pipes,
        );
        self::assertIsResource($process);
        $exitCode = proc_close($process);
        rewind($output);

        self::assertSame(0, $exitCode, (string) stream_get_contents($output));
    }
}
