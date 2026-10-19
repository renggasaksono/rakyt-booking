<?php

namespace Tests\Support\Concurrency;

use Symfony\Component\Process\Process;

/**
 * Runs a helper script from this directory in a second PHP process with its
 * own database connection (ADR-0006 concurrency tests). Rows must be
 * committed: classes using it return [] from connectionsToTransact() and
 * rebuild the schema after each test.
 */
trait RunsSecondProcess
{
    /**
     * @param  list<int|string>  $arguments
     */
    protected function startProcess(string $script, array $arguments): Process
    {
        $connection = config('database.connections.pgsql');

        $process = new Process(
            [PHP_BINARY, base_path("tests/Support/Concurrency/{$script}"), ...array_map('strval', $arguments)],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_URL' => '',
                'DB_HOST' => (string) $connection['host'],
                'DB_PORT' => (string) $connection['port'],
                'DB_DATABASE' => (string) $connection['database'],
                'DB_USERNAME' => (string) $connection['username'],
                'DB_PASSWORD' => (string) $connection['password'],
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'LOG_CHANNEL' => 'null',
            ],
            timeout: 30,
        );
        $process->start();

        return $process;
    }

    protected function waitForOutput(Process $process, string $line): void
    {
        $deadline = microtime(true) + 15;

        while (! str_contains($process->getOutput(), $line)) {
            if (! $process->isRunning() || microtime(true) > $deadline) {
                $process->stop();
                $this->fail("Second process did not print [{$line}]. Output: {$process->getOutput()} Errors: {$process->getErrorOutput()}");
            }

            usleep(20_000);
        }
    }
}
