<?php

namespace Tests\Feature;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, LeaveTypeSeeder::class]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    public function test_attendance_is_unique_per_employee_and_date(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $employee = Employee::factory()->create();

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->employee_id,
            'attendance_date' => '2025-01-10',
        ]);

        $this->actingAs($officer)
            ->post(route('attendance-records.store'), [
                'employee_id' => $employee->employee_id,
                'attendance_date' => '2025-01-10',
                'attendance_status' => AttendanceRecord::STATUS_PRESENT,
                'credited_days' => 1,
            ])
            ->assertSessionHasErrors('attendance_date');
    }

    public function test_approving_an_attendance_adjustment_applies_it(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $employee = Employee::factory()->create();
        $record = AttendanceRecord::factory()->create([
            'employee_id' => $employee->employee_id,
            'attendance_date' => '2025-01-10',
            'attendance_status' => AttendanceRecord::STATUS_ABSENT,
            'credited_days' => 0,
        ]);

        $this->actingAs($officer)->post(route('attendance-adjustments.store'), [
            'attendance_id' => $record->attendance_id,
            'requested_attendance_status' => AttendanceRecord::STATUS_HALF_DAY,
            'requested_credited_days' => 0.5,
            'adjustment_reason' => 'Employee reported half day.',
        ])->assertRedirect(route('attendance-adjustments.index'));

        $adjustment = AttendanceAdjustment::firstOrFail();
        $this->assertSame('PENDING', $adjustment->approval_status);

        $this->actingAs($officer)
            ->post(route('attendance-adjustments.approve', $adjustment))
            ->assertRedirect(route('attendance-adjustments.show', $adjustment));

        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_HALF_DAY, $record->attendance_status);
        $this->assertSame('0.50', (string) $record->credited_days);
        $this->assertSame('APPROVED', $adjustment->fresh()->approval_status);
    }

    public function test_approving_a_leave_application_updates_the_balance_and_ledger(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::where('leave_name', 'Vacation Leave')->firstOrFail();

        $this->actingAs($officer)->post(route('leave-applications.store'), [
            'employee_id' => $employee->employee_id,
            'leave_type_id' => $leaveType->leave_type_id,
            'start_date' => '2025-03-10',
            'end_date' => '2025-03-11',
            'total_days' => 2,
            'reason' => 'Family matters',
        ])->assertRedirect();

        $application = LeaveApplication::firstOrFail();
        $this->assertSame('PENDING', $application->approval_status);

        $this->actingAs($officer)
            ->post(route('leave-applications.approve', $application))
            ->assertRedirect(route('leave-applications.show', $application));

        $balance = LeaveBalance::where('employee_id', $employee->employee_id)
            ->where('leave_type_id', $leaveType->leave_type_id)
            ->where('leave_year', 2025)
            ->firstOrFail();

        $this->assertSame('2.00', (string) $balance->total_used);
        $this->assertSame('-2.00', (string) $balance->available_balance);
        $this->assertDatabaseHas('leave_transactions', [
            'leave_application_id' => $application->leave_application_id,
            'transaction_type' => 'USED',
        ]);
        $this->assertSame('APPROVED', $application->fresh()->approval_status);
    }

    public function test_a_reviewed_leave_application_cannot_be_approved_twice(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $employee = Employee::factory()->create();
        $leaveType = LeaveType::where('leave_name', 'Sick Leave')->firstOrFail();

        $application = LeaveApplication::create([
            'employee_id' => $employee->employee_id,
            'leave_type_id' => $leaveType->leave_type_id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-01',
            'total_days' => 1,
            'approval_status' => LeaveApplication::STATUS_APPROVED,
        ]);

        $this->actingAs($officer)
            ->post(route('leave-applications.approve', $application))
            ->assertRedirect();

        $this->assertDatabaseCount('leave_transactions', 0);
    }

    public function test_viewer_cannot_access_attendance_management(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);

        $this->actingAs($viewer)->get(route('attendance-records.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('leave-applications.index'))->assertForbidden();
    }
}
