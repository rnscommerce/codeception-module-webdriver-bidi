<?php

declare(strict_types=1);

namespace Rnscommerce\Codeception\WebDriverBiDi\Tests\Integration;

/**
 * A child process listening on a free port, stopped with stop().
 */
final class Process
{
    /** @var resource */
    private $process;

    /**
     * @param resource $process
     */
    private function __construct($process, public readonly int $port)
    {
        $this->process = $process;
    }

    /**
     * @param callable(int): list<string> $command the command line for the port
     * @param array<string, string> $environment added to this process's own
     */
    public static function start(callable $command, string $readyPath, array $environment = []): self
    {
        $port = self::freePort();
        $process = proc_open(
            $command($port),
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            $environment + getenv(),
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start a process');
        }
        $started = new self($process, $port);
        try {
            $started->waitUntilReady($readyPath);
        } catch (\Throwable $e) {
            $started->stop();
            throw $e;
        }
        return $started;
    }

    public static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('No free port');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }

    private function waitUntilReady(string $path): void
    {
        $deadline = microtime(true) + 20;
        while (microtime(true) < $deadline) {
            $curl = curl_init("http://127.0.0.1:{$this->port}$path");
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2]);
            $answered = curl_exec($curl) !== false;
            curl_close($curl);
            if ($answered) {
                return;
            }
            usleep(100000);
        }
        throw new \RuntimeException("Nothing answered on port {$this->port}");
    }
}
