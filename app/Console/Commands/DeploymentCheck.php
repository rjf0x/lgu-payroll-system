<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

#[Signature('deployment:check {--production : Also enforce production configuration requirements}')]
#[Description('Inspect release prerequisites without changing data or contacting the blockchain')]
class DeploymentCheck extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [
            'PHP 8.5 or later' => PHP_VERSION_ID >= 80500,
            'Required PHP extensions' => collect(['intl', 'gd', config('database.default') === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite', 'mbstring', 'openssl'])->every(fn (string $extension): bool => extension_loaded($extension)),
            'Application key configured' => filled(config('app.key')),
            'Frontend production manifest' => is_file(public_path('build/manifest.json')),
            'Vite development marker absent' => ! is_file(public_path('hot')),
            'Writable storage and cache' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
        ];

        try {
            DB::connection()->getPdo();
            $migrator = app('migrator');
            $checks['Database reachable and migrations current'] = $migrator->repositoryExists()
                && array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) === [];
        } catch (Throwable) {
            $checks['Database reachable and migrations current'] = false;
        }

        if ($this->option('production') || app()->isProduction()) {
            $checks += [
                'Production environment selected' => app()->isProduction(),
                'Debug output disabled' => config('app.debug') === false,
                'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
                'Secure session cookies' => config('session.secure') === true,
                'Persistent database, sessions, cache and queue' => config('database.default') === 'mysql'
                    && config('session.driver') === 'database' && config('cache.default') === 'database' && config('queue.default') === 'database',
                'Besu driver and public addresses configured' => config('blockchain.driver') === 'besu'
                    && preg_match('/^0x[0-9a-f]{40}$/i', (string) config('blockchain.contract_address'))
                    && preg_match('/^0x[0-9a-f]{40}$/i', (string) config('blockchain.besu.from_address')),
                'Bootstrap password removed from runtime config' => blank(config('deployment.bootstrap_admin.password')),
            ];

            try {
                $checks['An active administrator exists'] = User::query()->where('account_status', User::STATUS_ACTIVE)->whereHas('roles', fn ($roles) => $roles->where('role_name', Role::ADMIN))->exists();
                $checks['Known demo passwords absent'] = ! User::query()->whereIn('username', ['admin', 'officer', 'approver', 'viewer'])->get()
                    ->contains(fn (User $user): bool => Hash::check('password', $user->password_hash) || Hash::check('ChangeMe123!', $user->password_hash));
            } catch (Throwable) {
                $checks['Production accounts reviewed'] = false;
            }
        }

        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS ' : 'FAIL ').$label);
        }

        $this->comment('Manual acceptance remains: HTTPS smoke test, backup restore drill, worker/scheduler monitoring, Besu connectivity and approved payroll policies.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
