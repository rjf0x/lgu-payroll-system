<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollFinalization;
use App\Models\PayrollInputRevision;
use App\Models\PayrollRuleVersion;
use App\Models\PayrollSnapshotArchive;
use App\Models\PayrollSubmission;
use App\Models\SalarySchedule;
use App\Models\StatutoryPreview;
use App\Models\User;
use App\Services\PayrollDocumentService;
use App\Services\PayrollWorkflowService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->approver = User::factory()->create(['role' => Role::Approver]);
        $this->actor($this->officer);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function fixture(array $categories = [PersonnelClassification::Permanent], string $period = '2026-10', string $stage = 'finalized', array $employeeOverrides = []): PayrollBatch
    {
        $this->actor($this->officer);
        $this->post('/payroll-batches', ['period' => $period, 'is_demonstration' => 1])->assertSessionHasNoErrors();
        $batch = PayrollBatch::orderByDesc('id')->firstOrFail();
        foreach ($categories as $category) {
            $number = Employee::count() + 1;
            $employee = Employee::factory()->create(array_replace([
                'personnel_classification' => $category, 'employment_start_date' => '2020-01-01',
                'employee_number' => 'DOC-'.$number, 'first_name' => 'Fictional '.$number,
                'last_name' => 'Document Employee', 'middle_name' => null, 'suffix' => null,
            ], $employeeOverrides));
            $schedule = SalarySchedule::where('personnel_classification', $category)->first();
            if (! $schedule) {
                $schedule = SalarySchedule::create(['name' => 'FICTIONAL DOCUMENT SOURCE', 'personnel_classification' => $category, 'is_demonstration' => true, 'effective_from' => '2020-01-01', 'created_by' => $this->officer->id]);
                $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
                $schedule->forceFill(['published_at' => now(), 'authority_reference' => 'FICTIONAL AUTHORITY'])->save();
            }
            EmployeeSalaryRecord::forceCreate([
                'employee_id' => $employee->id, 'salary_rate_id' => $schedule->rates()->sole()->id,
                'effective_from' => '2020-01-01', 'change_type' => 'initial', 'personnel_classification' => $category->value,
                'position' => 'Fictional Clerk', 'monthly_salary' => '30000.00', 'is_demonstration' => true,
                'step_basis_date' => '2020-01-01', 'authority_reference' => 'FICTIONAL APPOINTMENT',
                'reason' => 'Fictional document fixture', 'created_by' => $this->officer->id,
            ]);
            $this->post(route('payroll-employees.store', $batch), ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
            $entry = $batch->entries()->where('employee_id', $employee->id)->sole();
            $this->put(route('payroll-inputs.update', [$batch, $entry]), [
                'record_version' => $batch->fresh()->record_version,
                'allowances' => [['label' => 'Fictional allowance', 'amount' => '100.07', 'is_applicable' => 1], ['label' => 'EXCLUDED FICTIONAL ALLOWANCE', 'amount' => '999.99', 'is_applicable' => 0]],
                'bonuses' => [['label' => 'Fictional supplementary bonus', 'amount' => '500.01']],
                'loans' => [['label' => 'Fictional loan', 'amount' => '200.02']],
                'adjustments' => [
                    ['label' => 'Fictional earning replacement', 'direction' => 'earning', 'original_amount' => '999.99', 'adjusted_amount' => '50.09', 'reason' => 'Fictional authority'],
                    ['label' => 'Fictional deduction replacement', 'direction' => 'deduction', 'original_amount' => '300.03', 'adjusted_amount' => '111.11', 'reason' => 'Fictional authority'],
                ],
                'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
                'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional document amounts', 'authorization_confirmed' => 1,
            ])->assertSessionHasNoErrors();
        }
        $this->post(route('payroll-calculations.store', $batch), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::where('payroll_batch_id', $batch->id)->sole();
        foreach ($run->items as $item) {
            $this->post(route('statutory-previews.store', [$batch, $run, $item]), StatutoryCalculatorTest::data([
                'record_version' => $batch->fresh()->record_version, 'tax_method' => 'cumulative',
                'elapsed_periods' => 1, 'taxable_other' => '650.17',
            ]))->assertSessionHasNoErrors();
        }
        if ($stage === 'draft') {
            return $batch->fresh();
        }
        $service = app(PayrollWorkflowService::class);
        $this->post(route('payroll-reviews.transition', [$batch, 'submit']), [
            'record_version' => $batch->fresh()->record_version, 'review_token' => $service->reviewToken($service->currentSnapshot($batch->fresh())),
            'reason' => 'FICTIONAL reviewed documents', 'review_confirmed' => 1,
        ])->assertSessionHasNoErrors();
        if ($stage === 'submitted') {
            return $batch->fresh();
        }
        $this->actor($this->approver);
        foreach ($stage === 'returned' ? ['return'] : ($stage === 'approved' ? ['approve'] : ['approve', 'finalize']) as $action) {
            $this->post(route('payroll-reviews.transition', [$batch, $action]), [
                'record_version' => $batch->fresh()->record_version, 'submission_id' => $service->latestSubmission($batch)->id,
                'reason' => 'FICTIONAL reviewed '.$action, 'review_confirmed' => 1,
            ])->assertSessionHasNoErrors();
        }

        return $batch->fresh();
    }

    private function urls(PayrollBatch $batch, PayrollFinalization $finalization, ?int $employeeId = null): array
    {
        $employeeId ??= $finalization->snapshot['payroll']['employees'][0]['employee']['employee_id'];

        return [
            route('payroll-documents.index', $batch), route('payroll-documents.register', [$batch, $finalization]),
            route('payroll-documents.register-pdf', [$batch, $finalization]), route('payroll-documents.register-csv', [$batch, $finalization]),
            route('payroll-documents.payslip', [$batch, $finalization, $employeeId]), route('payroll-documents.payslip-pdf', [$batch, $finalization, $employeeId]),
        ];
    }

    private function totals(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        $values = [];
        foreach ((new DOMXPath($dom))->query('//*[@data-total]') as $node) {
            $values[$node->getAttribute('data-total')] = trim($node->textContent);
        }

        return $values;
    }

    private function csvRows(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $headers = fgetcsv($stream, null, ',', '"', '');
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = array_combine($headers, $row);
        }
        fclose($stream);

        return $rows;
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [[$category]], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_register_csv_and_payslip_match_preserved_values_for_each_category(array $categories): void
    {
        $batch = $this->fixture($categories);
        $final = PayrollFinalization::sole();
        $urls = $this->urls($batch, $final);
        $expected = ['basic_pay' => '30000.00', 'other_earnings' => '650.17', 'total_earnings' => '30650.17',
            'manual_deductions' => '311.13', 'gsis' => '2700.00', 'pagibig' => '200.00', 'philhealth' => '750.00',
            'withholding' => '925.08', 'total_deductions' => '4886.21', 'net_pay' => '25763.96'];
        $register = $this->get($urls[1])->assertOk()->assertSee('DEMONSTRATION ONLY - NOT FOR REAL PAYROLL');
        $this->assertSame($expected, $this->totals($register->getContent()));
        $slip = $this->get($urls[4])->assertOk()->assertSee('Fictional allowance')->assertSee('111.11')->assertSee('50.09')
            ->assertDontSee('EXCLUDED FICTIONAL ALLOWANCE')->assertDontSee('999.99')->assertDontSee('300.03');
        $this->assertSame(array_intersect_key($expected, array_flip(['total_earnings', 'total_deductions', 'net_pay'])), $this->totals($slip->getContent()));
        $csv = $this->get($urls[3])->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->getContent());
        $rows = $this->csvRows($csv->getContent());
        $this->assertSame('EMPLOYEE', $rows[0]['Record type']);
        $this->assertSame('TOTAL', $rows[1]['Record type']);
        foreach (PayrollDocumentService::COLUMNS as $field => $label) {
            $this->assertSame($expected[$field], $rows[0][$label]);
            $this->assertSame($expected[$field], $rows[1][$label]);
        }
        $this->get($urls[0])->assertOk()->assertSee($urls[1], false)->assertSee($urls[4], false);
    }

    public function test_register_totals_and_individual_payslips_cover_only_finalized_employees(): void
    {
        $batch = $this->fixture(PersonnelClassification::cases());
        $final = PayrollFinalization::sole();
        $urls = $this->urls($batch, $final);
        $totals = $this->totals($this->get($urls[1])->assertOk()->getContent());
        $this->assertSame('91950.51', $totals['total_earnings']);
        $this->assertSame('14658.63', $totals['total_deductions']);
        $this->assertSame('77291.88', $totals['net_pay']);
        $employees = $final->snapshot['payroll']['employees'];
        foreach ($employees as $saved) {
            $employee = $saved['employee'];
            $slip = $this->get(route('payroll-documents.payslip', [$batch, $final, $employee['employee_id']]))->assertOk()->assertSee($employee['employee_name']);
            $this->assertSame($saved['statutory_result']['net_before_tax_refund'], $this->totals($slip->getContent())['net_pay']);
            foreach ($employees as $other) {
                if ($other['employee']['employee_id'] !== $employee['employee_id']) {
                    $slip->assertDontSee($other['employee']['employee_name']);
                }
            }
        }
        $uncovered = Employee::factory()->create();
        $this->get(route('payroll-documents.payslip', [$batch, $final, $uncovered->id]))->assertNotFound();
    }

    public function test_document_footers_identify_the_batch_or_the_individual_payslip(): void
    {
        $batch = $this->fixture();
        $final = PayrollFinalization::sole();
        $urls = $this->urls($batch, $final);
        $this->get($urls[1])->assertOk()->assertSee('Finalized reference #'.$final->id)->assertDontSee('/ Employee DOC-1');
        $this->get($urls[4])->assertOk()->assertSee('/ Employee DOC-1');
    }

    public function test_paginated_history_uses_snapshot_identities_and_full_register_includes_every_page(): void
    {
        $batch = $this->fixture();
        $final = PayrollFinalization::sole();
        $snapshot = $final->snapshot;
        $template = $snapshot['payroll']['employees'][0];
        $snapshot['payroll']['employees'] = [];
        for ($number = 1; $number <= 21; $number++) {
            $saved = $template;
            $saved['employee']['employee_id'] = 1000 + $number;
            $saved['employee']['employee_name'] = 'Fictional preserved employee '.$number;
            $snapshot['payroll']['employees'][] = $saved;
        }
        foreach ($snapshot['payroll']['totals'] as &$amount) {
            $amount = bcmul($amount, '21', 2);
        }
        unset($amount);
        // Simulate a pre-archive legacy fixture to exercise pagination without rewriting retained bytes.
        PayrollSnapshotArchive::where('payroll_finalization_id', $final->id)->toBase()->delete();
        // Own isolated fixture exercises retained identities without live model binding.
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $url = route('payroll-documents.index', $batch);
        $this->get($url)->assertOk()->assertSee('Fictional preserved employee 20')->assertDontSee('Fictional preserved employee 21');
        $this->get($url.'?page=2')->assertOk()->assertSee('Fictional preserved employee 21');
        $this->get(route('payroll-documents.payslip', [$batch, $final, 1021]))->assertOk()->assertSee('Fictional preserved employee 21')->assertSee('25763.96');
        $csv = $this->get(route('payroll-documents.register-csv', [$batch, $final]))->assertOk();
        $this->assertCount(22, $this->csvRows($csv->getContent()));
        $pdf = $this->get(route('payroll-documents.register-pdf', [$batch, $final]))->assertOk()->getContent();
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    public function test_out_of_range_document_pages_are_not_found_without_changing_finalized_payroll(): void
    {
        $batch = $this->fixture();
        $final = PayrollFinalization::sole();
        $batchBefore = $batch->getAttributes();
        $finalBefore = $final->getAttributes();
        $url = route('payroll-documents.index', $batch);

        foreach ([PHP_INT_MAX, intdiv(PHP_INT_MAX, 20) + 2, 2] as $page) {
            $this->get($url.'?page='.$page)->assertNotFound();
        }

        $this->get($url)->assertOk()->assertSee('Fictional 1');
        $this->assertSame($batchBefore, $batch->fresh()->getAttributes());
        $this->assertSame($finalBefore, $final->fresh()->getAttributes());
        $this->assertDatabaseCount('payroll_workflow_events', 3);
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_all_document_endpoints_enforce_roles_and_private_downloads(Role $role): void
    {
        $batch = $this->fixture();
        $final = PayrollFinalization::sole();
        $this->actor(User::factory()->create(['role' => $role]));
        foreach ($this->urls($batch, $final) as $url) {
            $response = $this->get($url)->assertStatus($role === Role::Administrator ? 403 : 200);
            if ($role === Role::Administrator) {
                continue;
            }
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'no-referrer');
            if (str_ends_with($url, '.pdf')) {
                $response->assertHeader('Content-Type', 'application/pdf');
                $this->assertStringStartsWith('%PDF-', $response->getContent());
                $this->assertStringContainsString('%%EOF', $response->getContent());
                $this->assertStringNotContainsString('/JavaScript', $response->getContent());
                $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
                $this->assertStringNotContainsString('Fictional', $response->headers->get('Content-Disposition'));
            }
        }
    }

    public static function unfinishedStages(): array
    {
        return [['draft'], ['submitted'], ['returned'], ['approved']];
    }

    #[DataProvider('unfinishedStages')]
    public function test_unfinalized_payroll_cannot_generate_documents(string $stage): void
    {
        $batch = $this->fixture(stage: $stage);
        $this->get(route('payroll-documents.index', $batch))->assertNotFound();
        foreach (['register', 'register.pdf', 'register.csv', 'payslips/1', 'payslips/1.pdf'] as $path) {
            $this->get('/payroll-batches/'.$batch->id.'/documents/999/'.$path)->assertNotFound();
        }
        $this->assertDatabaseCount('payroll_finalizations', 0);
    }

    public function test_cross_batch_ids_guest_inactive_and_revoked_sessions_are_rejected(): void
    {
        $first = $this->fixture();
        $final = PayrollFinalization::sole();
        $second = $this->fixture([PersonnelClassification::Coterminous], '2026-11');
        foreach (array_slice($this->urls($second, $final), 1) as $url) {
            $this->get($url)->assertNotFound();
        }
        $urls = $this->urls($first, $final);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $inactive = User::factory()->create(['role' => Role::Viewer, 'is_active' => false]);
        $this->actor($inactive);
        $this->get($urls[0])->assertRedirect('/login');
        $this->actor($this->officer);
        $this->withSession(['auth_version' => $this->officer->auth_version - 1]);
        $this->get($urls[2])->assertRedirect('/login');
    }

    public function test_later_employee_configuration_and_rule_activation_do_not_change_retrieved_documents(): void
    {
        $batch = $this->fixture();
        $final = PayrollFinalization::sole();
        $urls = $this->urls($batch, $final);
        $before = $this->get($urls[3])->assertOk()->getContent();
        $models = [PayrollFinalization::sole(), PayrollSubmission::sole(), PayrollInputRevision::sole(), PayrollCalculationRun::sole(), StatutoryPreview::sole()];
        $attributes = array_map(fn ($model) => $model->getAttributes(), $models);
        $batch->entries()->sole()->employee->update(['first_name' => 'CHANGED LIVE PROFILE', 'office' => 'CHANGED LIVE OFFICE', 'is_active' => false]);
        $this->actor(User::factory()->create(['role' => Role::Administrator]));
        $data = PayrollRuleVersionTest::data();
        $data['parameters']['contributions']['gsis']['rate'] = '0.10';
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $this->post('/payroll-rule-versions/'.PayrollRuleVersion::sole()->id.'/activate', PayrollRuleVersionTest::activation())->assertSessionHasNoErrors();
        config(['payroll_statutory.tax.bonus_exemption' => '0.00']);
        $this->actor(User::factory()->create(['role' => Role::Viewer]));
        $this->assertSame($before, $this->get($urls[3])->assertOk()->getContent());
        $this->get($urls[4])->assertOk()->assertDontSee('CHANGED LIVE')->assertSee('25763.96');
        $this->get($urls[2])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        foreach ($models as $index => $model) {
            $this->assertSame($attributes[$index], $model->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('payroll_workflow_events', 3);
    }

    public static function corruptions(): array
    {
        return [['schema'], ['period'], ['version'], ['identity'], ['string_identity'], ['missing'], ['amount'], ['lines'], ['total'], ['duplicate'], ['real']];
    }

    #[DataProvider('corruptions')]
    public function test_invalid_preserved_snapshots_fail_closed_without_being_repaired(string $fault): void
    {
        $batch = $this->fixture();
        $final = PayrollFinalization::sole();
        $urls = $this->urls($batch, $final);
        $snapshot = $final->snapshot;
        switch ($fault) {
            case 'schema': $snapshot['schema'] = 'unknown';
                break;
            case 'period': $snapshot['payroll']['period'] = '1999-01';
                break;
            case 'version': $snapshot['version'] = 99;
                break;
            case 'identity': $snapshot['payroll']['payroll_batch_id'] = 999;
                break;
            case 'string_identity': $snapshot['payroll']['employees'][0]['employee']['employee_id'] = '1';
                break;
            case 'missing': unset($snapshot['payroll']['employees'][0]['statutory_result']['tax']['withholding']);
                break;
            case 'amount': $snapshot['payroll']['employees'][0]['statutory_result']['net_before_tax_refund'] = '999.999';
                break;
            case 'lines': $snapshot['payroll']['employees'][0]['core_breakdown']['lines'][0]['amount'] = '1.00';
                break;
            case 'total': $snapshot['payroll']['totals']['net_pay'] = '1.00';
                break;
            case 'duplicate': $snapshot['payroll']['employees'][] = $snapshot['payroll']['employees'][0];
                break;
            case 'real': $snapshot['payroll']['production_ready'] = true;
                break;
        }
        // Fault injection touches only this isolated transaction's fixture.
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $before = $final->fresh()->getAttributes();
        foreach ($urls as $url) {
            $this->get($url)->assertStatus(409);
        }
        $this->assertSame($before, $final->fresh()->getAttributes());
    }

    public static function csvText(): array
    {
        return [['=1+1', "'=1+1"], ['+SUM(1,1)', "'+SUM(1,1)"], ['-1+2', "'-1+2"], ['@SUM(1,1)', "'@SUM(1,1)"],
            ["\t=1+1", "'\t=1+1"], [" \r=1+1", "' \r=1+1"], ["Quoted, \"office\"\nsecond line", "Quoted, \"office\"\nsecond line"], ['Fictional Peñaflor Office', 'Fictional Peñaflor Office']];
    }

    #[DataProvider('csvText')]
    public function test_csv_preserves_text_safely_and_never_executes_formula_cells(string $text, string $expected): void
    {
        $batch = $this->fixture(employeeOverrides: ['office' => $text]);
        $final = PayrollFinalization::sole();
        $csv = $this->get(route('payroll-documents.register-csv', [$batch, $final]))->assertOk();
        $this->assertSame($expected, $this->csvRows($csv->getContent())[0]['Office']);
        $this->assertSame($text, $final->snapshot['payroll']['employees'][0]['employee']['office']);
    }

    public function test_html_and_pdf_escape_saved_markup_instead_of_loading_it(): void
    {
        $payload = '<script>alert(1)</script><img src="file:///private.txt">';
        $batch = $this->fixture(employeeOverrides: ['office' => $payload]);
        $final = PayrollFinalization::sole();
        foreach (['payroll-documents.register', 'payroll-documents.payslip'] as $route) {
            $this->get(route($route, [$batch, $final, 1]))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
        }
        $pdf = $this->get(route('payroll-documents.payslip-pdf', [$batch, $final, 1]))->assertOk();
        $this->assertStringNotContainsString('/JavaScript', $pdf->getContent());
        $this->assertStringNotContainsString('/EmbeddedFile', $pdf->getContent());
    }
}
