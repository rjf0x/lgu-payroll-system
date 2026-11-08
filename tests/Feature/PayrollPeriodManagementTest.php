<?php

namespace Tests\Feature;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->firstOrFail(), ['assigned_at' => now()]);

        return $user;
    }

    public function test_filters_find_overlapping_periods_and_statistics_include_all_records(): void
    {
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $target = PayrollPeriod::factory()->create(['period_name' => 'January schedule', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        PayrollRun::factory()->create(['payroll_period_id' => $target->getKey(), 'status' => 'PROCESSING']);
        PayrollPeriod::factory()->create(['status' => 'CLOSED', 'period_name' => 'Other schedule']);

        $this->actingAs($user)->get(route('payroll-periods.index', ['q' => 'January', 'year' => 2026, 'from' => '2026-01-15', 'to' => '2026-02-01', 'payroll_type' => 'MONTHLY', 'status' => 'OPEN']))
            ->assertOk()->assertSee('January schedule')->assertDontSee('Other schedule')
            ->assertSee('Processing')->assertSee('Period status')->assertSee('Create Payroll Period')
            ->assertViewHas('statistics', ['Total payroll periods' => 2, 'Open periods' => 1, 'Periods with payroll runs' => 1])
            ->assertViewHas('periods', fn ($periods) => $periods->total() === 1);
    }

    public function test_periods_are_paginated_in_newest_coverage_order_with_filters_preserved(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        for ($month = 1; $month <= 16; $month++) {
            $start = now()->setDate(2026, 1, 1)->startOfDay()->addMonths($month - 1);
            PayrollPeriod::factory()->create(['start_date' => $start, 'end_date' => $start->copy()->endOfMonth()]);
        }

        $this->actingAs($user)->get(route('payroll-periods.index', ['status' => 'OPEN']))
            ->assertOk()->assertDontSee('Create Payroll Period')->assertDontSee('Close Period')
            ->assertViewHas('periods', fn ($periods) => $periods->total() === 16 && $periods->count() === 15
                && $periods->first()->start_date->toDateString() === '2027-04-01'
                && str_contains($periods->nextPageUrl(), 'status=OPEN'));
    }

    public function test_duplicate_create_and_update_are_rejected_without_changing_payroll_records(): void
    {
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $period = PayrollPeriod::factory()->create();
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey()]);
        $other = PayrollPeriod::factory()->create();
        $payload = $period->only(['period_name', 'payroll_type', 'status']);
        foreach (['start_date', 'end_date', 'pay_date'] as $field) {
            $payload[$field] = $period->{$field}->toDateString();
        }

        $this->actingAs($user)->postJson(route('payroll-periods.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->putJson(route('payroll-periods.update', $other), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->put(route('payroll-periods.update', $period), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('payroll_periods', 2);
        $this->assertSame($period->getKey(), $run->fresh()->payroll_period_id);
        $this->assertSame($other->start_date->toDateString(), $other->fresh()->start_date->toDateString());
    }

    public function test_database_rejects_duplicate_coverage_even_when_validation_is_bypassed(): void
    {
        $period = PayrollPeriod::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);
        PayrollPeriod::factory()->create($period->only(['payroll_type', 'start_date', 'end_date']));
    }

    public function test_details_and_run_links_only_display_the_selected_periods_runs(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        $period = PayrollPeriod::factory()->create(['period_name' => 'Selected schedule']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'status' => 'FINALIZED']);
        PayrollRun::factory()->create();

        $this->actingAs($user)->get(route('payroll-periods.show', $period))->assertOk()
            ->assertSee('Selected schedule')->assertSee('Finalized')->assertSee('Run status')->assertDontSee('Close Period');
        $this->get(route('payroll-runs.index', ['payroll_period_id' => $period->getKey()]))->assertOk()
            ->assertViewHas('runs', fn ($runs) => $runs->total() === 1 && $runs->first()->is($run));
    }

    public function test_closing_preserves_runs_audits_change_and_blocks_new_runs(): void
    {
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $period = PayrollPeriod::factory()->create();
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'status' => 'FINALIZED']);

        $this->actingAs($user)->post(route('payroll-periods.close', $period))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('CLOSED', $period->fresh()->status);
        $this->assertSame('FINALIZED', $run->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PERIOD_CLOSED', 'record_id' => (string) $period->getKey()]);
        $this->postJson(route('payroll-runs.store'), ['payroll_period_id' => $period->getKey(), 'run_type' => 'REGULAR'])
            ->assertUnprocessable()->assertJsonValidationErrors('payroll_period_id');
        $this->postJson(route('payroll-periods.close', $period))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('payroll_runs', 1);
    }

    public function test_viewers_cannot_close_periods_and_guests_cannot_view_them(): void
    {
        $period = PayrollPeriod::factory()->create();
        $this->get(route('payroll-periods.index'))->assertRedirect(route('login'));
        $this->get(route('payroll-periods.show', $period))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole(Role::VIEWER))->post(route('payroll-periods.close', $period))->assertForbidden();
        $this->assertSame('OPEN', $period->fresh()->status);
    }

    public function test_invalid_filters_are_rejected_and_no_matches_display_an_empty_state(): void
    {
        $user = $this->userWithRole(Role::VIEWER);

        $this->actingAs($user)->getJson(route('payroll-periods.index', ['year' => 'invalid', 'from' => '2026-02-01', 'to' => '2026-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors(['year', 'to']);
        $this->get(route('payroll-periods.index', ['q' => 'Missing schedule']))->assertOk()->assertSee('No payroll periods match your filters.');
    }

    public function test_coverage_filter_accepts_a_single_end_date_and_includes_the_boundary_day(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);

        $this->actingAs($user)->get(route('payroll-periods.index', ['to' => '2026-01-01']))->assertOk()
            ->assertViewHas('periods', fn ($periods) => $periods->total() === 1 && $periods->first()->is($period));
    }

    public function test_authorized_user_can_create_a_distinct_payroll_schedule(): void
    {
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $payload = ['period_name' => 'January schedule', 'payroll_type' => 'MONTHLY', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'pay_date' => '2026-01-31', 'status' => 'OPEN'];

        $this->actingAs($user)->post(route('payroll-periods.store'), $payload)
            ->assertRedirect(route('payroll-periods.index'))->assertSessionHasNoErrors();

        $period = PayrollPeriod::sole();
        $this->assertSame('January schedule', $period->period_name);
        $this->assertSame('2026-01-01', $period->start_date->toDateString());
        $this->assertSame('2026-01-31', $period->end_date->toDateString());
        $this->assertSame('OPEN', $period->status);
    }
}
