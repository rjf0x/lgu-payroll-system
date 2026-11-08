<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class ProductionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createAdmin((array) config('deployment.bootstrap_admin'));
    }

    /**
     * @param  array{username?: string|null, email?: string|null, password?: string|null}  $credentials
     */
    public function createAdmin(array $credentials): void
    {
        Validator::make($credentials, [
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(16)->mixedCase()->numbers()->symbols()],
        ])->validate();

        if (User::query()->exists()) {
            throw new RuntimeException('Initial production setup requires an empty users table. Existing accounts were not changed.');
        }

        DB::transaction(function () use ($credentials): void {
            $this->call([RolePermissionSeeder::class, SystemSettingSeeder::class, EmploymentTypeSeeder::class]);
            $user = User::create([
                'username' => $credentials['username'],
                'email' => $credentials['email'],
                'password_hash' => $credentials['password'],
                'account_status' => User::STATUS_ACTIVE,
            ]);
            $user->roles()->attach(Role::where('role_name', Role::ADMIN)->firstOrFail()->getKey(), ['assigned_at' => now()]);
            app(AuditLogger::class)->log('PRODUCTION_BOOTSTRAPPED', 'Deployment', $user->getKey(), 'users', (string) $user->getKey());
        });
    }
}
