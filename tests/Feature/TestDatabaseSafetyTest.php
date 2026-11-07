<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TestDatabaseSafetyTest extends TestCase
{
    /**
     * @return list<array{string, string, string|false}>
     */
    public static function unsafeDatabases(): array
    {
        return [
            ['mysql', 'lgu_payroll_system_dev', false],
            ['sqlite', 'database/database.sqlite', false],
            ['mysql', 'lgu_payroll_qa_stage12', 'mysql://example:example@127.0.0.1/lgu_payroll_system_dev'],
        ];
    }

    #[DataProvider('unsafeDatabases')]
    public function test_bootstrap_rejects_unsafe_databases_before_migrations(string $driver, string $database, string|false $url): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$probe = new class('test_probe') extends Tests\TestCase {
    public function test_probe(): void {}
};
try {
    $probe->createApplication();
    echo 'UNSAFE';
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Tests require in-memory SQLite or an isolated lgu_payroll_qa_ MySQL database.') {
        throw $exception;
    }
    echo 'BLOCKED';
}
PHP;
        $process = new Process([PHP_BINARY, '-r', $code], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => $driver,
            'DB_DATABASE' => $database, 'DB_URL' => $url,
        ], timeout: 15);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('BLOCKED', $process->getOutput());
    }
}
