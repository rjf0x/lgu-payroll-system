<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Controllers\EmployeeController;
use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function signIn(Role $role = Role::PayrollOfficer): User
    {
        $user = User::factory()->create(['role' => $role]);
        $expected = session('url.intended', route('dashboard'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($expected);

        return $user;
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'employee_number' => 'demo-001', 'first_name' => 'Fictional Maria', 'middle_name' => 'Example',
            'last_name' => 'Santos', 'suffix' => null, 'position' => 'Demonstration Clerk',
            'office' => 'Fictional Accounting Office', 'personnel_classification' => 'permanent',
            'employment_start_date' => '2024-02-29', 'is_active' => '1',
        ], $overrides);
    }

    public static function classifications(): array
    {
        return array_map(fn ($classification) => [$classification], PersonnelClassification::cases());
    }

    #[DataProvider('classifications')]
    public function test_officer_can_create_retrieve_and_edit_each_classification(PersonnelClassification $classification): void
    {
        $officer = $this->signIn();
        $this->get('/employees/create')->assertOk()->assertSee('Create employee');
        $this->post('/employees', $this->data([
            'employee_number' => ' demo-001 ', 'personnel_classification' => $classification->value,
            'record_version' => 999, 'basic_salary' => 90000, 'role' => 'administrator',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $employee = Employee::sole();
        $this->assertSame('DEMO-001', $employee->employee_number);
        $this->assertSame($classification, $employee->personnel_classification);
        $this->assertSame('2024-02-29', $employee->employment_start_date->format('Y-m-d'));
        $this->assertSame(1, $employee->record_version);
        $this->assertArrayNotHasKey('basic_salary', $employee->getAttributes());
        $this->assertSame(Role::PayrollOfficer, $officer->fresh()->role);
        $this->get('/employees/'.$employee->id)->assertOk()->assertSee('Fictional Maria Example Santos')->assertSee($classification->label());
        $this->get('/employees/'.$employee->id.'/edit')->assertOk()->assertSee('DEMO-001');
        $this->put('/employees/'.$employee->id, $this->data([
            'employee_number' => 'DEMO-001', 'middle_name' => '', 'suffix' => 'Jr.',
            'position' => 'Updated Demonstration Clerk', 'personnel_classification' => $classification->value,
            'is_active' => '0', 'record_version' => 1,
        ]))->assertRedirect('/employees/'.$employee->id)->assertSessionHasNoErrors();
        $employee->refresh();
        $this->assertNull($employee->middle_name);
        $this->assertFalse($employee->is_active);
        $this->assertSame(2, $employee->record_version);
        $this->get('/employees/'.$employee->id)->assertOk()->assertSee('Updated Demonstration Clerk')->assertSee('Inactive')->assertSee('Fictional Maria Santos Jr.');
        $this->get('/employees')->assertOk()->assertSee('DEMO-001');
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_employee_permissions_and_navigation_for_every_role(Role $role): void
    {
        $this->signIn($role);
        $employee = Employee::factory()->create();
        $dashboard = $this->get('/dashboard')->assertOk();
        if ($role === Role::Administrator) {
            $dashboard->assertDontSee(route('employees.index'));
            $this->get('/employees')->assertForbidden();
            $this->get('/employees/'.$employee->id)->assertForbidden();
        } else {
            $dashboard->assertSee(route('employees.index'));
            $index = $this->get('/employees')->assertOk()->assertSee($employee->employee_number);
            $detail = $this->get('/employees/'.$employee->id)->assertOk();
            if ($role !== Role::PayrollOfficer) {
                $index->assertDontSee('Create employee');
                $detail->assertDontSee('Edit employee');
            }
        }
        if ($role !== Role::PayrollOfficer) {
            $this->get('/employees/create')->assertForbidden();
            $this->get('/employees/'.$employee->id.'/edit')->assertForbidden();
            $this->post('/employees', $this->data())->assertForbidden();
            $this->put('/employees/'.$employee->id, $this->data(['record_version' => 1]))->assertForbidden();
            $this->patch('/employees/'.$employee->id, $this->data(['record_version' => 1]))->assertForbidden();
            $this->assertSame(1, $employee->fresh()->record_version);
            $this->assertDatabaseCount('employees', 1);
        }
    }

    public function test_guests_inactive_and_stale_sessions_cannot_access_employee_records(): void
    {
        $employee = Employee::factory()->create();
        foreach (['/employees', '/employees/create', '/employees/'.$employee->id, '/employees/'.$employee->id.'/edit'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->post('/employees', $this->data())->assertRedirect('/login');
        $this->put('/employees/'.$employee->id, $this->data(['record_version' => 1]))->assertRedirect('/login');
        $actor = $this->signIn();
        $actor->forceFill(['is_active' => false])->save();
        Auth::forgetGuards();
        $this->get('/employees')->assertRedirect('/login');
        $actor->forceFill(['is_active' => true, 'auth_version' => 2])->save();
        $this->actingAs($actor)->withSession(['auth_version' => 1]);
        $this->post('/employees', $this->data())->assertRedirect('/login');
        $this->assertDatabaseCount('employees', 1);
    }

    public static function invalidInputs(): array
    {
        return [
            ['employee_number', 'bad number!'], ['employee_number', str_repeat('X', 51)],
            ['first_name', ''], ['first_name', str_repeat('X', 101)], ['middle_name', ['invalid']],
            ['last_name', ''], ['suffix', str_repeat('X', 21)], ['position', ''],
            ['office', str_repeat('X', 151)], ['personnel_classification', 'contractual'],
            ['employment_start_date', '2025-02-30'], ['employment_start_date', '02/28/2025'],
            ['employment_start_date', '0999-12-31'], ['is_active', 'yes'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_create_and_update_do_not_change_records(string $field, mixed $value): void
    {
        $this->signIn();
        $employee = Employee::factory()->create();
        $original = $employee->fresh()->getAttributes();
        $this->from('/employees/create')->post('/employees', $this->data([$field => $value]))
            ->assertRedirect('/employees/create')->assertSessionHasErrors($field);
        $this->withCookie(config('session.cookie'), session()->getId())->get('/employees/create')->assertOk()->assertSee('Please check the following:');
        $this->put('/employees/'.$employee->id, $this->data([$field => $value, 'record_version' => 1]))->assertSessionHasErrors($field);
        $this->assertSame($original, $employee->fresh()->getAttributes());
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_array_shaped_inputs_render_safely_after_validation_errors(): void
    {
        $this->signIn();
        $data = array_fill_keys(array_keys($this->data()), ['invalid']);
        $this->from('/employees/create')->post('/employees', $data)->assertRedirect('/employees/create')->assertSessionHasErrors(array_keys($data));
        $this->withCookie(config('session.cookie'), session()->getId())->get('/employees/create')->assertOk()->assertSee('Please check the following:');
        $employee = Employee::factory()->create();
        $this->from('/employees/'.$employee->id.'/edit')->put('/employees/'.$employee->id, $data + ['record_version' => ['invalid']])->assertSessionHasErrors('record_version');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/employees/'.$employee->id.'/edit')->assertOk();
        $this->assertSame(1, $employee->fresh()->record_version);
    }

    public function test_duplicate_numbers_and_repeated_create_are_rejected(): void
    {
        $this->signIn();
        $this->post('/employees', $this->data())->assertSessionHasNoErrors();
        $this->post('/employees', $this->data(['employee_number' => ' demo-001 ']))->assertSessionHasErrors('employee_number');
        $other = Employee::factory()->create();
        $this->put('/employees/'.$other->id, $this->data(['record_version' => 1]))->assertSessionHasErrors('employee_number');
        $this->assertDatabaseCount('employees', 2);
        $this->assertSame(1, $other->fresh()->record_version);
    }

    public function test_database_unique_conflicts_return_errors_and_roll_back_edits(): void
    {
        $this->signIn();
        $inserted = false;
        Employee::creating(function ($employee) use (&$inserted) {
            if (! $inserted && $employee->employee_number === 'DEMO-001') {
                $inserted = true;
                Employee::factory()->create(['employee_number' => $employee->employee_number]);
            }
        });
        $this->post('/employees', $this->data())->assertSessionHasErrors('employee_number');
        $this->assertDatabaseCount('employees', 0);
        $target = Employee::factory()->create();
        $original = $target->fresh()->getAttributes();
        Employee::updating(fn ($employee) => Employee::factory()->create(['employee_number' => $employee->employee_number]));
        $this->put('/employees/'.$target->id, $this->data(['record_version' => 1]))->assertSessionHasErrors('employee_number');
        $this->assertSame($original, $target->fresh()->getAttributes());
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_stale_edits_cannot_silently_overwrite_a_saved_record(): void
    {
        $this->signIn();
        $employee = Employee::factory()->create();
        $data = $this->data(['record_version' => 1]);
        $this->patch('/employees/'.$employee->id, $data)->assertSessionHasNoErrors();
        $saved = $employee->fresh()->getAttributes();
        $this->from('/employees/'.$employee->id.'/edit')->put('/employees/'.$employee->id, array_replace($data, ['position' => 'Stale overwrite']))->assertSessionHasErrors('record_version');
        $this->assertSame($saved, $employee->fresh()->getAttributes());
        $this->withCookie(config('session.cookie'), session()->getId())->get('/employees/'.$employee->id.'/edit')->assertOk()->assertSee('Reload saved record');
        $this->put('/employees/'.$employee->id, array_diff_key($data, ['record_version' => 1]))->assertSessionHasErrors('record_version');
    }

    public function test_search_is_literal_bound_paginated_and_includes_inactive_records(): void
    {
        $this->signIn(Role::Viewer);
        $target = Employee::factory()->create(['employee_number' => 'FIND-001', 'first_name' => 'Fictional Maria', 'last_name' => 'ExactName', 'middle_name' => 'UniqueMiddle', 'office' => 'Percent %_! Office', 'position' => 'Specialist-Unique', 'is_active' => false]);
        Employee::factory()->count(21)->create(['last_name' => 'Other']);
        foreach (['find-001', 'maria', 'exactname', 'uniquemiddle', 'Fictional Maria UniqueMiddle ExactName', 'specialist-unique', '%_!'] as $term) {
            $response = $this->get('/employees?'.http_build_query(['q' => $term]))->assertOk();
            $this->assertSame([$target->id], $response->viewData('employees')->pluck('id')->all());
        }
        $this->get('/employees?q=not-found')->assertOk()->assertSee('No employees match your search.');
        $this->get('/employees?'.http_build_query(['q' => "' OR 1=1 --"]))->assertOk()->assertViewHas('employees', fn ($items) => $items->total() === 0);
        $this->get('/employees?q=Other')->assertOk()->assertViewHas('employees', fn ($items) => $items->total() === 21 && $items->count() === 20)->assertSee('q=Other', false);
        $this->get('/employees?page=2&q=Other')->assertOk()->assertViewHas('employees', fn ($items) => $items->count() === 1);
        $this->get('/employees?q[]=bad')->assertStatus(422);
        $this->get('/employees?q='.str_repeat('x', 101))->assertStatus(422);
    }

    public static function revocations(): array
    {
        return [['deactivate'], ['demote'], ['reset']];
    }

    #[DataProvider('revocations')]
    public function test_inflight_employee_writes_recheck_the_current_actor(string $change): void
    {
        $actor = $this->signIn();
        $employee = Employee::factory()->create();
        $original = $employee->fresh()->getAttributes();
        $requests = [];
        foreach ([false, true] as $updating) {
            $request = SaveEmployeeRequest::create($updating ? '/employees/'.$employee->id : '/employees', $updating ? 'PUT' : 'POST', $this->data(['record_version' => 1]));
            $request->setContainer($this->app)->setRedirector($this->app['redirect'])->setUserResolver(fn () => $actor);
            $session = new Store('inflight-employee', new ArraySessionHandler(120));
            $session->start();
            $session->put('auth_version', 1);
            $request->setLaravelSession($session);
            $route = new Route($request->method(), $updating ? '/employees/{employee}' : '/employees', fn () => null);
            $route->bind($request);
            if ($updating) {
                $route->setParameter('employee', $employee);
            }
            $request->setRouteResolver(fn () => $route);
            $request->validateResolved();
            $requests[] = $request;
        }
        $revoked = $actor->fresh();
        $revoked->forceFill(match ($change) {
            'deactivate' => ['is_active' => false], 'demote' => ['role' => Role::Viewer], 'reset' => ['auth_version' => 2],
        })->save();
        foreach ($requests as $index => $request) {
            try {
                $controller = $this->app->make(EmployeeController::class);
                $index === 0 ? $controller->store($request) : $controller->update($request, $employee);
                $this->fail('Revoked employee writer completed a write.');
            } catch (AuthorizationException $exception) {
                $this->assertSame(403, $exception->status() ?? 403);
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame($original, $employee->fresh()->getAttributes());
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_employee_forms_require_csrf_and_records_are_not_deleted(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $actor = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actingAs($actor)->withSession(['auth_version' => 1]);
        $employee = Employee::factory()->create();
        $this->get('/employees/create')->assertOk()->assertSee('name="_token"', false);
        $this->post('/employees', $this->data())->assertStatus(419);
        $this->put('/employees/'.$employee->id, $this->data(['record_version' => 1]))->assertStatus(419);
        $this->post('/employees', $this->data() + ['_token' => session()->token()])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/employees/9999999')->assertNotFound();
        $this->delete('/employees/'.$employee->id)->assertStatus(405);
        $this->assertDatabaseCount('employees', 2);
    }
}
