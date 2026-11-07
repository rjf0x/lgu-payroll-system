<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if (! ($driver === 'sqlite' && $database === ':memory:')
            && ! ($driver === 'mysql' && is_string($database) && str_starts_with($database, 'lgu_payroll_qa_'))) {
            throw new RuntimeException('Tests require in-memory SQLite or an isolated lgu_payroll_qa_ MySQL database.');
        }

        return $app;
    }
}
