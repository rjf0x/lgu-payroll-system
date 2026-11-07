<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UiFoundationsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    private function document(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    public static function navigationCases(): array
    {
        return [
            'pending queue' => ['payroll-runs.index', ['status' => 'PENDING_APPROVAL'], 'Pending Approvals'],
            'monthly payroll' => ['payroll-runs.index', [], 'Monthly Payroll'],
            'unrelated status query' => ['employees.index', ['status' => 'PENDING_APPROVAL'], 'Employees'],
        ];
    }

    #[DataProvider('navigationCases')]
    public function test_only_the_current_navigation_link_is_indicated(string $route, array $query, string $label): void
    {
        $user = $this->userWithRole(Role::APPROVER);

        $response = $this->actingAs($user)->get(route($route, $query))->assertOk();

        $document = $this->document($response->getContent());
        $current = $document->query('//nav[@aria-label="Main navigation"]//a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertSame($label, trim($current->item(0)->textContent));
        $this->assertSame(1, $document->query('//nav[@aria-label="Main navigation"]')->length);
        $this->assertSame(1, $document->query('//details[@class="ui-nav-group" and @open]')->length);
    }

    public function test_employee_directory_has_labeled_search_results_and_keyboard_accessible_table(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        Employee::factory()->create(['employee_number' => 'FIND-001', 'first_name' => 'Alex', 'last_name' => 'Santos']);
        Employee::factory()->create(['employee_number' => 'OTHER-001', 'first_name' => 'Jamie', 'last_name' => 'Reyes']);

        $response = $this->actingAs($user)->get(route('employees.index', ['q' => 'FIND-001']))
            ->assertOk()->assertSee('1 employee matching your search')->assertSee('Alex')
            ->assertDontSee('Jamie')->assertDontSee('Add Employee');

        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//form[@role="search"]//label[@for="q"]')->length);
        $this->assertSame(1, $document->query('//div[@role="region" and @aria-label="Employee directory" and @tabindex="0"]//caption')->length);
        $this->assertSame(1, $document->query('//form[@role="search"]//a[@href="'.route('employees.index').'"]')->length);
    }

    public function test_failed_employee_submission_links_errors_to_input_select_and_textarea_and_keeps_values(): void
    {
        $user = $this->userWithRole(Role::ADMIN);

        $response = $this->actingAs($user)->from(route('employees.create'))->followingRedirects()->post(route('employees.store'), [
            'employee_number' => 'KEEP-001', 'first_name' => '', 'last_name' => 'Retained',
            'birth_date' => '1990-01-01', 'sex' => '', 'address' => '',
        ])->assertOk()
            ->assertSee('The first name field is required.')->assertSee('KEEP-001')->assertSee('Retained');

        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//*[@id="error-summary" and @role="alert" and @tabindex="-1"]')->length);
        foreach (['first_name' => 'input', 'sex' => 'select', 'address' => 'textarea'] as $field => $tag) {
            $this->assertSame(1, $document->query('//'.$tag.'[@id="'.$field.'" and @aria-invalid="true" and @aria-describedby="'.$field.'-error"]')->length);
            $this->assertSame(1, $document->query('//*[@id="'.$field.'-error"]')->length);
            $this->assertSame(1, $document->query('//*[@id="error-summary"]//a[@href="#'.$field.'"]')->length);
        }
        $this->assertSame(1, $document->query('//input[@name="last_name" and @value="Retained"]')->length);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'KEEP-001']);
    }

    public function test_employee_record_has_a_breadcrumb_back_to_the_directory(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.show', $employee))->assertOk();

        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//nav[@aria-label="Breadcrumb"]//a[@href="'.route('employees.index').'"]')->length);
    }

    public function test_pending_payroll_review_has_a_readable_status_and_labeled_remarks(): void
    {
        $user = $this->userWithRole(Role::APPROVER);
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_PENDING_APPROVAL]);

        $response = $this->actingAs($user)->get(route('payroll-runs.show', $run))
            ->assertOk()->assertSee('Pending Approval')->assertSee('Approval remarks (optional)');

        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//label[@for="approval-remarks"]')->length);
        $this->assertSame(1, $document->query('//form[@action="'.route('payroll-runs.approve', $run).'"][@data-submit-feedback]')->length);
        $this->assertSame(1, $document->query('//div[@role="region" and @aria-label="Payroll approval history" and @tabindex="0"]')->length);
    }

    public function test_a_preparer_cannot_see_review_actions_on_their_own_pending_run(): void
    {
        $user = $this->userWithRole(Role::APPROVER);
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_PENDING_APPROVAL, 'created_by' => $user->user_id]);

        $response = $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertOk();

        $document = $this->document($response->getContent());
        $this->assertSame(0, $document->query('//form[@action="'.route('payroll-runs.approve', $run).'"]')->length);
        $this->assertSame(0, $document->query('//form[@action="'.route('payroll-runs.reject', $run).'"]')->length);
    }

    public function test_unknown_status_text_is_escaped_and_employee_search_handles_empty_results(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        $run = PayrollRun::factory()->create(['status' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertOk()
            ->assertDontSee('<script>', false)->assertSee('&lt;Script&gt;', false);
        $this->get(route('employees.index', ['q' => '<script>']))->assertOk()
            ->assertSee('No employees match your search.')->assertDontSee('<script>', false);
    }
}
