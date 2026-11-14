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
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UiFoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_buttons_show_decorative_icons_and_text_and_keep_accessible_labels_links_and_submission_attributes(): void
    {
        $html = Blade::render('<x-button icon="edit" href="/example" aria-label="Edit employee">Edit</x-button><x-button icon="delete" type="submit" formaction="/delete" disabled>Delete</x-button><x-button type="submit">Save changes</x-button>');
        $document = $this->document($html);
        $this->assertSame(1, $document->query('//a[@href="/example" and @title="Edit" and @aria-label="Edit employee"]')->length);
        $this->assertSame(1, $document->query('//a/svg[@aria-hidden="true" and @focusable="false"]')->length);
        $this->assertSame(0, $document->query('//a/span[@class="sr-only"]')->length);
        $this->assertSame(1, $document->query('//a[@data-action-button]/span[@data-button-label and text()="Edit"]')->length);
        $this->assertSame(1, $document->query('//button[@type="submit" and @formaction="/delete" and @disabled and @title="Delete"]')->length);
        $this->assertSame(1, $document->query('//button[@formaction="/delete"]/svg[@aria-hidden="true"]')->length);
        $this->assertSame(1, $document->query('//button[not(@data-icon-action)]/span[@data-button-label and text()="Save changes"]')->length);
    }

    public function test_row_actions_have_one_labeled_trigger_and_preserve_links_and_confirmation_forms(): void
    {
        $html = Blade::render('<x-action-menu label="Actions for Alex"><x-button icon="view" href="/employee">View employee</x-button><form method="POST" action="/close" data-confirm data-submit-feedback><x-button icon="lock" type="submit">Close period</x-button></form></x-action-menu><x-action-menu label="Other actions"><x-button icon="edit" href="/edit">Edit</x-button></x-action-menu>');

        $document = $this->document($html);

        $triggers = $document->query('//button[@data-action-menu-trigger]');
        $this->assertSame(2, $triggers->length);
        $trigger = $triggers->item(0);
        $menuId = $trigger->getAttribute('popovertarget');
        $this->assertSame('Actions for Alex', $trigger->getAttribute('aria-label'));
        $this->assertSame('button', $trigger->getAttribute('type'));
        $this->assertSame($menuId, $trigger->getAttribute('aria-controls'));
        $this->assertNotSame($menuId, $triggers->item(1)->getAttribute('popovertarget'));
        $this->assertSame(3, $document->query('svg[@aria-hidden="true"]/circle', $trigger)->length);
        $this->assertSame(1, $document->query('//div[@id="'.$menuId.'" and @popover="auto" and @role="group"]//a[@href="/employee"]')->length);
        $this->assertSame(1, $document->query('//div[@id="'.$menuId.'"]//form[@action="/close" and @method="POST" and @data-confirm and @data-submit-feedback]//button[@type="submit"]')->length);
    }

    public function test_row_actions_do_not_show_an_empty_dropdown(): void
    {
        $html = Blade::render('<x-action-menu> </x-action-menu>');

        $this->assertSame('', trim($html));
    }

    public function test_payroll_period_filters_show_three_primary_fields_and_expand_active_extra_filters(): void
    {
        $user = $this->userWithRole(Role::VIEWER);

        $response = $this->actingAs($user)->get(route('payroll-periods.index'))->assertOk();

        $document = $this->document($response->getContent());
        $this->assertSame(3, $document->query('//form[@aria-label="Payroll period filters"]//*[self::input or self::select][not(ancestor::details)][@name="q" or @name="year" or @name="status"]')->length);
        $this->assertSame(3, $document->query('//details[@id="payroll-period-extra-filters"]//*[self::input or self::select]')->length);
        $this->assertSame(0, $document->query('//details[@id="payroll-period-extra-filters" and @open]')->length);
        $this->assertSame(0, $document->query('//details[@id="payroll-period-coverage-help" and @open]')->length);
        $this->assertSame(1, $document->query('//details[@id="payroll-period-coverage-help"]/p[@id="payroll-period-filter-help"]')->length);

        $response = $this->get(route('payroll-periods.index', ['payroll_type' => 'MONTHLY', 'from' => '2026-01-01']))->assertOk();

        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//details[@id="payroll-period-extra-filters" and @open]')->length);
        $this->assertSame(1, $document->query('//details[@id="payroll-period-extra-filters"]//input[@name="from" and @value="2026-01-01"]')->length);
        $this->assertSame(1, $document->query('//details[@id="payroll-period-extra-filters"]//select[@name="payroll_type"]/option[@value="MONTHLY" and @selected]')->length);
    }

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
        $employee = Employee::factory()->create(['employee_number' => 'FIND-001', 'first_name' => 'Alex', 'middle_name' => null, 'last_name' => 'Santos', 'suffix' => null]);
        Employee::factory()->create(['employee_number' => 'OTHER-001', 'first_name' => 'Jamie', 'last_name' => 'Reyes']);

        $response = $this->actingAs($user)->get(route('employees.index', ['q' => 'FIND-001']))
            ->assertOk()->assertSee('1 employee matching your search')->assertSee('Alex')
            ->assertDontSee('Jamie')->assertDontSee('Add Employee');

        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//form[@role="search"]//label[@for="q"]')->length);
        $this->assertSame(1, $document->query('//div[@role="region" and @aria-label="Employee directory" and @tabindex="0"]//caption')->length);
        $this->assertSame(1, $document->query('//form[@role="search"]//a[@href="'.route('employees.index').'"]')->length);
        $this->assertSame(1, $document->query('//button[@data-action-menu-trigger and @aria-label="Actions for Alex Santos"]')->length);
        $this->assertSame(1, $document->query('//div[@data-action-menu-panel]//a[starts-with(@href, "'.route('employees.show', $employee).'")]')->length);
        $this->assertSame(0, $document->query('//div[@data-action-menu-panel]//a[contains(@href, "/edit")]')->length);
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

    public static function payrollRecordNavigationCases(): array
    {
        return [
            'approver pending review' => [Role::APPROVER, PayrollRun::STATUS_PENDING_APPROVAL, 'Pending Approvals'],
            'viewer pending record' => [Role::VIEWER, PayrollRun::STATUS_PENDING_APPROVAL, 'Monthly Payroll'],
            'approver finalized record' => [Role::APPROVER, PayrollRun::STATUS_FINALIZED, 'Monthly Payroll'],
        ];
    }

    #[DataProvider('payrollRecordNavigationCases')]
    public function test_payroll_record_navigation_identifies_the_relevant_work_queue(string $role, string $status, string $label): void
    {
        $user = $this->userWithRole($role);
        $run = PayrollRun::factory()->create(['status' => $status]);

        $response = $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertOk();

        $document = $this->document($response->getContent());
        $current = $document->query('//nav[@aria-label="Main navigation"]//a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertSame($label, trim($current->item(0)->textContent));
    }

    public function test_administration_links_share_one_group_and_retain_their_permissions(): void
    {
        $user = $this->userWithRole(Role::ADMIN);

        $response = $this->actingAs($user)->get(route('system-settings.edit'))->assertOk();

        $document = $this->document($response->getContent());
        foreach (['users.index', 'audit-logs.index', 'system-settings.edit'] as $route) {
            $this->assertSame(1, $document->query('//details[@data-navigation-group="administration"]//a[@href="'.route($route).'"]')->length);
        }

        $viewer = $this->userWithRole(Role::VIEWER);
        $viewerPage = $this->actingAs($viewer)->get(route('dashboard'))->assertOk();
        $viewerDocument = $this->document($viewerPage->getContent());
        $this->assertSame(0, $viewerDocument->query('//details[@data-navigation-group="administration"]')->length);
    }
}
