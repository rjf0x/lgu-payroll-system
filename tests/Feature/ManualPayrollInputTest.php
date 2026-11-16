<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\EmploymentRecord;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollComputationService;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualPayrollInputTest extends TestCase
{
    use RefreshDatabase;

    private function preparedRun(string $basis = 'MONTHLY'): PayrollRun
    {
        $this->seed(RolePermissionSeeder::class);
        $officer = User::factory()->create();
        $officer->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($officer);
        $record = EmploymentRecord::factory()->create();
        CompensationProfile::factory()->create([
            'employment_id' => $record->getKey(), 'grade_step_id' => null,
            'pay_basis' => $basis, 'basic_rate' => $basis === 'DAILY' ? 800 : 22000,
            'service_fraction' => 1, 'effective_from' => '2024-01-01',
        ]);
        $run = PayrollRun::factory()->create(['created_by' => $officer->getKey()]);
        app(PayrollComputationService::class)->computeRun($run);

        return $run;
    }

    private function payload(): array
    {
        return ['payable_days' => 20, 'input_reference' => 'Approved summary HR-123', 'inputs_confirmed' => 1, 'earnings' => [], 'deductions' => [], 'lbp_loan_deduction' => 0];
    }

    private function editorDocument(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    public function test_editor_prioritizes_configured_entries_and_keeps_additional_fields_submittable(): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $earning = EarningType::factory()->create(['earning_name' => 'Configured allowance', 'earning_category' => 'ALLOWANCE']);
        $additional = EarningType::factory()->create(['earning_name' => 'Additional benefit', 'earning_category' => 'BENEFIT']);
        $gsis = DeductionType::factory()->create(['deduction_name' => 'MPL', 'deduction_category' => 'GSIS_LOAN', 'is_statutory' => false]);
        $pagibig = DeductionType::factory()->create(['deduction_name' => 'MPL', 'deduction_category' => 'PAGIBIG_LOAN', 'is_statutory' => false]);
        $statutory = DeductionType::factory()->create(['is_statutory' => true]);
        $inactive = EarningType::factory()->create(['status' => 'INACTIVE']);
        $this->put(route('payroll-details.update', [$run, $detail]), array_replace($this->payload(), [
            'earnings' => [$earning->getKey() => 500],
            'deductions' => [$gsis->getKey() => 100, $pagibig->getKey() => 50],
            'lbp_loan_deduction' => 25,
        ]))->assertRedirect();

        $response = $this->get(route('payroll-details.edit', [$run, $detail]))->assertOk();

        $response->assertSee('Last computed')->assertSee('Save &amp; Recompute', false);
        $response->assertSee('Approval reference / reason (optional)');
        $xpath = $this->editorDocument($response->getContent());
        $this->assertSame(0, $xpath->query('//input[@name="input_reference" and @required]')->length);
        $this->assertSame('500.00', $xpath->evaluate('string(//input[@id="earning-'.$earning->getKey().'"]/@value)'));
        $this->assertSame(0, $xpath->query('//input[@id="earning-'.$earning->getKey().'"]/ancestor::details')->length);
        $this->assertSame(1, $xpath->query('//input[@id="earning-'.$additional->getKey().'"]/ancestor::details[not(@open)]')->length);
        $this->assertSame('25.00', $xpath->evaluate('string(//input[@name="lbp_loan_deduction"]/@value)'));
        $this->assertSame('GSIS loans', $xpath->evaluate('string(//input[@id="deduction-'.$gsis->getKey().'"]/ancestor::fieldset/legend)'));
        $this->assertSame('Pag-IBIG loans & savings', $xpath->evaluate('string(//input[@id="deduction-'.$pagibig->getKey().'"]/ancestor::fieldset/legend)'));
        $this->assertSame(0, $xpath->query('//input[@id="deduction-'.$statutory->getKey().'" or @id="earning-'.$inactive->getKey().'"]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-payroll-detail-form]//input[@disabled]')->length);

        foreach ($xpath->query('//form[@data-payroll-detail-form]//input[not(@type="hidden")]') as $field) {
            $this->assertSame(1, $xpath->query('//label[@for="'.$field->getAttribute('id').'"]')->length);
        }
    }

    public function test_editor_retains_cleared_zero_and_invalid_amounts_after_validation_failure(): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $zeroed = EarningType::factory()->create();
        $cleared = EarningType::factory()->create();
        $deduction = DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'OTHER']);
        $this->put(route('payroll-details.update', [$run, $detail]), array_replace($this->payload(), [
            'earnings' => [$zeroed->getKey() => 500, $cleared->getKey() => 250],
            'lbp_loan_deduction' => 25,
        ]))->assertRedirect();
        $editUrl = route('payroll-details.edit', [$run, $detail]);
        $response = $this->from($editUrl)->followingRedirects()->put(route('payroll-details.update', [$run, $detail]), array_replace($this->payload(), [
            'payable_days' => 0,
            'input_reference' => 'Revised approval HR-124',
            'earnings' => [$zeroed->getKey() => 0, $cleared->getKey() => ''],
            'deductions' => [$deduction->getKey() => -10],
        ]))->assertOk();

        $response->assertSee('Unsaved changes. Totals update when you save.');
        $xpath = $this->editorDocument($response->getContent());
        $this->assertSame('0', $xpath->evaluate('string(//input[@id="earning-'.$zeroed->getKey().'"]/@value)'));
        $this->assertSame('', $xpath->evaluate('string(//input[@id="earning-'.$cleared->getKey().'"]/@value)'));
        $this->assertSame('-10', $xpath->evaluate('string(//input[@id="deduction-'.$deduction->getKey().'"]/@value)'));
        $this->assertSame('true', $xpath->evaluate('string(//input[@id="deduction-'.$deduction->getKey().'"]/@aria-invalid)'));
        $this->assertSame(0, $xpath->query('//input[@id="deduction-'.$deduction->getKey().'"]/ancestor::details')->length);
        $this->assertSame('0', $xpath->evaluate('string(//input[@name="lbp_loan_deduction"]/@value)'));
        $this->assertSame('0', $xpath->evaluate('string(//input[@name="payable_days"]/@value)'));
        $this->assertSame('Revised approval HR-124', $xpath->evaluate('string(//input[@name="input_reference"]/@value)'));
        $this->assertSame(1, $xpath->query('//input[@name="inputs_confirmed" and @checked]')->length);
    }

    public function test_editor_makes_initial_entries_available_without_configured_adjustments(): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $earning = EarningType::factory()->create(['earning_name' => 'Allowance <script>alert(1)</script>']);

        $response = $this->get(route('payroll-details.edit', [$run, $detail]))->assertOk();

        $response->assertSee($earning->earning_name)->assertDontSee($earning->earning_name, false);
        $xpath = $this->editorDocument($response->getContent());
        $this->assertSame(1, $xpath->query('//input[@id="earning-'.$earning->getKey().'"]/ancestor::details[@open]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="lbp_loan_deduction"]')->length);
    }

    public function test_removed_modules_have_no_routes_permissions_tables_or_navigation(): void
    {
        $run = $this->preparedRun();
        foreach (['attendance-records', 'attendance-adjustments', 'leave-applications', 'leave-balances', 'leave-types', 'leave-transactions'] as $resource) {
            $this->get('/'.$resource)->assertNotFound();
            $this->post('/'.$resource)->assertNotFound();
            $this->assertFalse(Schema::hasTable(str_replace('-', '_', $resource)));
        }
        $this->assertFalse(Permission::whereIn('permission_name', ['attendance.manage', 'leave.manage'])->exists());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Record attendance')->assertDontSee('Leave applications');
        $this->get(route('employees.show', $run->details()->firstOrFail()->employee_id))->assertOk()->assertDontSee('id="attendance"', false)->assertDontSee('id="leave"', false);
    }

    public function test_missing_inputs_do_not_assume_full_pay_and_prevent_submission(): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $this->assertNull($detail->payable_days);
        $this->assertSame('0.00', $detail->basic_pay);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Payroll inputs are incomplete');
        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable();
        $this->assertSame('DRAFT', $run->fresh()->status);
    }

    public function test_approved_inputs_and_manual_adjustments_survive_recomputation(): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $earning = EarningType::factory()->create(['is_taxable' => false]);
        $deduction = DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'OTHER']);
        $payload = array_replace($this->payload(), ['earnings' => [$earning->getKey() => 500], 'deductions' => [$deduction->getKey() => 100], 'lbp_loan_deduction' => 25]);

        $this->put(route('payroll-details.update', [$run, $detail]), $payload)->assertRedirect(route('payroll-runs.show', $run));
        $this->post(route('payroll-runs.compute', $run))->assertRedirect();

        $detail->refresh();
        $this->assertSame('20000.00', $detail->basic_pay);
        $this->assertSame('20500.00', $detail->gross_pay);
        $this->assertSame('20375.00', $detail->net_pay);
        $this->assertSame('20.00', $detail->payable_days);
        $this->assertSame($payload['input_reference'], $detail->input_reference);
        $this->assertSame(1, $run->details()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_DETAIL_UPDATED', 'record_id' => (string) $detail->getKey()]);
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $run->fresh()->status);
    }

    public function test_daily_pay_uses_explicit_fractional_and_zero_payable_days(): void
    {
        $run = $this->preparedRun('DAILY');
        $detail = $run->details()->firstOrFail();
        $this->put(route('payroll-details.update', [$run, $detail]), array_replace($this->payload(), ['payable_days' => 1.5]))->assertRedirect();
        $this->assertSame('1200.00', $detail->fresh()->basic_pay);
        $this->put(route('payroll-details.update', [$run, $detail]), array_replace($this->payload(), ['payable_days' => 0]))->assertRedirect();
        $this->assertSame('0.00', $detail->fresh()->basic_pay);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function optionalReferences(): array
    {
        return [
            'omitted' => [[]],
            'null' => [['input_reference' => null]],
            'empty' => [['input_reference' => '']],
            'whitespace' => [['input_reference' => '   ']],
        ];
    }

    /** @param array<string, mixed> $reference */
    #[DataProvider('optionalReferences')]
    public function test_confirmed_payroll_can_be_saved_and_submitted_without_a_reference(array $reference): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $payload = $this->payload();
        unset($payload['input_reference']);

        $this->put(route('payroll-details.update', [$run, $detail]), [...$payload, ...$reference])
            ->assertRedirect(route('payroll-runs.show', $run));

        $this->assertNull($detail->fresh()->input_reference);
        $this->assertNotNull($detail->fresh()->input_confirmed_at);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertViewHas('canSubmit', true);
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $run->fresh()->status);
    }

    public static function invalidInputs(): array
    {
        return [
            'missing days' => [['payable_days' => null], 'payable_days'],
            'negative days' => [['payable_days' => -1], 'payable_days'],
            'too many monthly days' => [['payable_days' => 23], 'payable_days'],
            'excess precision' => [['payable_days' => 1.234], 'payable_days'],
            'reference too long' => [['input_reference' => str_repeat('a', 256)], 'input_reference'],
            'reference is not text' => [['input_reference' => ['invalid']], 'input_reference'],
            'unconfirmed inputs' => [['inputs_confirmed' => 0], 'inputs_confirmed'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_cannot_change_payroll(array $overrides, string $field): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $this->putJson(route('payroll-details.update', [$run, $detail]), array_replace($this->payload(), $overrides))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertNull($detail->fresh()->input_confirmed_at);
    }

    public function test_viewers_and_finalized_runs_cannot_change_inputs(): void
    {
        $run = $this->preparedRun();
        $detail = $run->details()->firstOrFail();
        $run->update(['status' => 'FINALIZED']);
        $this->putJson(route('payroll-details.update', [$run, $detail]), $this->payload())->assertForbidden();
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('role_name', Role::VIEWER)->value('role_id'), ['assigned_at' => now()]);
        $run->update(['status' => 'DRAFT']);
        $this->actingAs($viewer)->putJson(route('payroll-details.update', [$run, $detail]), $this->payload())->assertForbidden();
    }
}
