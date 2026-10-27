<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollRuleVersion;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Policies\PayrollBatchPolicy;
use App\Policies\PayrollRuleVersionPolicy;
use App\Policies\SalarySchedulePolicy;
use App\Services\Blockchain\BlockchainSubmitter;
use App\Services\Blockchain\UnavailableBlockchainSubmitter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BlockchainSubmitter::class, UnavailableBlockchainSubmitter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(PayrollBatch::class, PayrollBatchPolicy::class);
        Gate::policy(PayrollRuleVersion::class, PayrollRuleVersionPolicy::class);
        Gate::policy(SalarySchedule::class, SalarySchedulePolicy::class);
        Gate::define('manage-users', fn (User $user) => $user->is_active && $user->role === Role::Administrator);

        foreach (Role::cases() as $role) {
            Gate::define('access-'.$role->workspace(), fn (User $user) => $user->is_active && $user->role === $role);
        }
    }
}
