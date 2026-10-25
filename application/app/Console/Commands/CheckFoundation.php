<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckFoundation extends Command
{
    protected $signature = 'app:check-foundation';

    protected $description = 'Verify database connectivity and the base application tables';

    public function handle(): int
    {
        try {
            DB::connection()->select('SELECT 1');

            foreach (['migrations', 'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
                if (! Schema::hasTable($table)) {
                    $this->error('Base tables are missing. Run php artisan migrate.');

                    return self::FAILURE;
                }
            }
        } catch (Throwable) {
            $this->error('Database unavailable. Check the server and DB_* configuration.');

            return self::FAILURE;
        }

        $this->info('Database connection and base tables are ready.');

        return self::SUCCESS;
    }
}
