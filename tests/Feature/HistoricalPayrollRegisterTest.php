<?php

namespace Tests\Feature;

use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class HistoricalPayrollRegisterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('configurationChanges')]
    public function test_saved_register_and_export_survive_configuration_changes(string $status): void
    {
        $this->actingAs($this->officer());
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        $detail = PayrollDetail::factory()->create([
            'payroll_run_id' => $run->getKey(), 'basic_pay' => 1000, 'total_earnings' => 120.35,
            'gross_pay' => 1120.35, 'total_deductions' => 30.15, 'nthp_before_lbp' => 1090.20,
            'lbp_loan_deduction' => 20.10, 'net_pay' => 1070.10,
        ]);
        $earning = EarningType::factory()->create(['earning_name' => 'Original allowance']);
        $deduction = DeductionType::factory()->create(['deduction_name' => 'Original loan']);
        $rate = ContributionRate::factory()->create();
        $detail->earnings()->create(['earning_type_id' => $earning->getKey(), 'earning_name_snapshot' => 'Original allowance', 'amount' => 120.35, 'is_taxable' => false]);
        $detail->deductions()->create(['deduction_type_id' => $deduction->getKey(), 'deduction_name_snapshot' => 'Original loan', 'amount' => 30.15]);
        $detail->employerContributions()->create(['contribution_rate_id' => $rate->getKey(), 'contribution_type_snapshot' => 'Original employer contribution', 'amount' => 40.25]);
        $before = $this->get(route('payroll-runs.register', $run))->assertOk();
        $sheetBefore = $this->spreadsheet($run);
        $detailBefore = $detail->fresh()->getAttributes();
        $linesBefore = [$detail->earnings()->get()->toArray(), $detail->deductions()->get()->toArray(), $detail->employerContributions()->get()->toArray()];

        $earning->update(['earning_name' => 'Changed allowance', 'status' => $status]);
        $deduction->update(['deduction_name' => 'Changed loan', 'deduction_category' => 'LBP', 'is_statutory' => true, 'status' => $status]);
        $rate->update(['contribution_type' => 'Changed contribution', 'employer_rate' => 0.9]);
        EarningType::factory()->create(['earning_name' => 'New unrelated earning']);
        DeductionType::factory()->create(['deduction_name' => 'New unrelated deduction']);

        $after = $this->get(route('payroll-runs.register', $run))->assertOk()
            ->assertSee('Original allowance')->assertSee('Original loan')->assertSee('Original employer contribution')
            ->assertDontSee('Changed allowance')->assertDontSee('Changed loan')->assertDontSee('New unrelated');
        $expected = [1000.0, 120.35, 120.35, 1120.35, 30.15, 30.15, 1090.2, 20.1, 1070.1, 40.25];
        $this->assertSame($expected, array_slice($after->viewData('rows')[0], 4));
        foreach (['headers', 'rows', 'totals'] as $key) {
            $this->assertSame($before->viewData($key), $after->viewData($key));
        }
        $sheetAfter = $this->spreadsheet($run);
        $this->assertSame($sheetBefore, $sheetAfter);
        $this->assertSame($after->viewData('headers'), $sheetAfter[0]);
        $this->assertSame($expected, array_slice($sheetAfter[1], 4));
        $this->assertSame($expected, array_slice($sheetAfter[2], 4));
        $this->assertSame($detailBefore, $detail->fresh()->getAttributes());
        $this->assertSame($linesBefore, [$detail->earnings()->get()->toArray(), $detail->deductions()->get()->toArray(), $detail->employerContributions()->get()->toArray()]);
    }

    /** @return array<string, array{string}> */
    public static function configurationChanges(): array
    {
        return ['renamed' => ['ACTIVE'], 'deactivated' => ['INACTIVE'], 'archived' => ['ARCHIVED']];
    }

    public function test_duplicate_and_similar_names_and_description_versions_keep_separate_amounts(): void
    {
        $this->actingAs($this->officer());
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED])->getKey(),
            'basic_pay' => 1000, 'total_earnings' => 100.10, 'gross_pay' => 1100.10,
            'total_deductions' => 60.06, 'nthp_before_lbp' => 1040.04, 'net_pay' => 1040.04]);
        $first = EarningType::factory()->create();
        $second = EarningType::factory()->create();
        foreach ([[$first, 'Allowance', 10.01], [$first, 'Allowance', 20.02], [$second, 'Allowance', 30.03], [$first, 'ALLOWANCE', 40.04]] as [$type, $label, $amount]) {
            $detail->earnings()->create(['earning_type_id' => $type->getKey(), 'earning_name_snapshot' => $label, 'amount' => $amount, 'is_taxable' => false]);
        }
        $loan = DeductionType::factory()->create();
        $tax = DeductionType::factory()->create(['deduction_category' => 'LBP', 'is_statutory' => true]);
        foreach ([[$loan, 'Loan', 10.01], [$tax, 'Loan', 20.02], [$loan, 'Total Compensation', 30.03]] as [$type, $label, $amount]) {
            $detail->deductions()->create(['deduction_type_id' => $type->getKey(), 'deduction_name_snapshot' => $label, 'amount' => $amount]);
        }
        foreach ([7.77, 8.88] as $amount) {
            $detail->employerContributions()->create(['contribution_rate_id' => ContributionRate::factory()->create()->getKey(), 'contribution_type_snapshot' => 'Loan', 'amount' => $amount]);
        }

        $response = $this->get(route('payroll-runs.register', $detail->payrollRun))->assertOk();
        $headers = $response->viewData('headers');
        $this->assertSame(count($headers), count(array_unique($headers)));
        $this->assertSame([
            'Allowance [Earning #'.$first->getKey().']', 'Allowance [Earning #'.$second->getKey().']', 'ALLOWANCE [Earning #'.$first->getKey().']',
        ], array_slice($headers, 5, 3));
        $this->assertSame([1000.0, 30.03, 30.03, 40.04, 100.1, 1100.1, 10.01, 20.02, 30.03, 60.06, 1040.04, 0.0, 1040.04, 7.77, 8.88], array_slice($response->viewData('rows')[0], 4));
        $sheet = $this->spreadsheet($detail->payrollRun);
        $this->assertSame($headers, $sheet[0]);
        $this->assertSame($response->viewData('rows')[0], $sheet[1]);
        $report = $this->get(route('reports.deductions', $detail->payrollRun))->assertOk()->assertSee('Loan')->assertSee('Total Compensation');
        $this->assertSame(40.04, $report->viewData('groups')[$loan->getKey()]['total']);
    }

    public function test_missing_snapshots_use_identifiers_across_register_export_payslip_and_deduction_report(): void
    {
        $this->actingAs($this->officer());
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED])->getKey()]);
        $earning = EarningType::factory()->create(['earning_name' => 'Current earning is not historical']);
        $deduction = DeductionType::factory()->create(['deduction_name' => 'Current deduction is not historical']);
        $rate = ContributionRate::factory()->create();
        $earningLine = $detail->earnings()->create(['earning_type_id' => $earning->getKey(), 'earning_name_snapshot' => '', 'amount' => 12.34, 'is_taxable' => false]);
        $detail->earnings()->create(['earning_type_id' => $earning->getKey(), 'earning_name_snapshot' => '  ', 'amount' => 1.11, 'is_taxable' => false]);
        $detail->earnings()->create(['earning_type_id' => $earning->getKey(), 'earning_name_snapshot' => 'Historical label unavailable', 'amount' => 0, 'is_taxable' => false]);
        $deductionLine = $detail->deductions()->create(['deduction_type_id' => $deduction->getKey(), 'deduction_name_snapshot' => '  ', 'amount' => 5.67]);
        $detail->employerContributions()->create(['contribution_rate_id' => $rate->getKey(), 'contribution_type_snapshot' => '', 'amount' => 8.90]);
        $payslip = Payslip::create(['payroll_detail_id' => $detail->getKey(), 'payslip_number' => 'Historical-001', 'generated_at' => now(), 'publication_status' => Payslip::STATUS_GENERATED]);
        $earningLabel = 'Earning #'.$earning->getKey().' (historical label unavailable)';
        $deductionLabel = 'Deduction #'.$deduction->getKey().' (historical label unavailable)';
        $employerLabel = 'Employer rate #'.$rate->getKey().' (historical label unavailable)';

        $response = $this->get(route('payroll-runs.register', $detail->payrollRun))->assertOk()
            ->assertSee($earningLabel)->assertSee($deductionLabel)->assertSee($employerLabel)->assertDontSee('Current earning');
        $sheet = $this->spreadsheet($detail->payrollRun);
        $this->assertSame(13.45, $sheet[1][5]);
        $this->assertSame(count($sheet[0]), count(array_unique($sheet[0])));
        $this->assertSame($response->viewData('headers'), $sheet[0]);
        $this->assertSame($response->viewData('rows')[0], $sheet[1]);
        $this->get(route('payslips.show', $payslip))->assertOk()->assertSee($earningLabel)->assertSee($deductionLabel)->assertSee($employerLabel)->assertDontSee('not historical');
        $this->get(route('reports.deductions', $detail->payrollRun))->assertOk()->assertSee($deductionLabel)->assertDontSee('not historical');
        $this->assertSame('', $earningLine->fresh()->earning_name_snapshot);
        $this->assertSame('  ', $deductionLine->fresh()->deduction_name_snapshot);
    }

    public function test_legacy_discrepancies_are_explicit_and_saved_totals_are_not_rewritten(): void
    {
        $this->actingAs($this->officer());
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'basic_pay' => 1000, 'total_earnings' => 125,
            'gross_pay' => 1130, 'total_deductions' => 25, 'nthp_before_lbp' => 1100, 'lbp_loan_deduction' => 10, 'net_pay' => 1085]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'net_pay' => 45005]);
        $before = $detail->fresh()->getAttributes();

        $response = $this->get(route('payroll-runs.register', $run))->assertOk()->assertSee('reconciliation difference');
        $this->assertSame([125.0, 5.0, 25.0, -5.0, -5.0], array_slice($response->viewData('rows')[0], -5));
        $this->assertSame(0.0, array_slice($response->viewData('totals'), -1)[0]);
        $sheet = $this->spreadsheet($run);
        $this->assertSame($response->viewData('headers'), $sheet[0]);
        $this->assertSame($response->viewData('rows')[0], $sheet[1]);
        $this->assertSame($before, $detail->fresh()->getAttributes());
    }

    public function test_snapshot_text_is_escaped_in_html_and_stored_as_text_in_excel(): void
    {
        $this->actingAs($this->officer());
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);
        $type = EarningType::factory()->create();
        $detail->earnings()->create(['earning_type_id' => $type->getKey(), 'earning_name_snapshot' => '<script>alert(1)</script>', 'amount' => 0, 'is_taxable' => false]);
        $detail->earnings()->create(['earning_type_id' => $type->getKey(), 'earning_name_snapshot' => '=1+1', 'amount' => 0, 'is_taxable' => false]);

        $response = $this->get(route('payroll-runs.register', $run))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
        $sheet = $this->spreadsheet($run);
        $this->assertSame($response->viewData('headers'), $sheet[0]);
        $this->assertSame('=1+1 [Earning #'.$type->getKey().']', $sheet[0][6]);
    }

    public function test_empty_finalized_export_has_only_report_columns_and_zero_totals(): void
    {
        $this->actingAs($this->officer());
        EarningType::factory()->create(['earning_name' => 'Unrelated earning']);
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);

        $response = $this->get(route('payroll-runs.register', $run))->assertOk()->assertDontSee('Unrelated earning');
        $sheet = $this->spreadsheet($run);
        $this->assertCount(2, $sheet);
        $this->assertSame($response->viewData('headers'), $sheet[0]);
        $this->assertSame(array_fill(0, 7, 0.0), array_slice($sheet[1], 4));
    }

    public function test_finalized_export_expands_columns_and_rows_and_totals_each_saved_component(): void
    {
        $this->actingAs($this->officer());
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        $details = PayrollDetail::factory()->count(12)->create(['payroll_run_id' => $run->getKey()]);
        $details[0]->update(['total_earnings' => 45, 'gross_pay' => 45045, 'nthp_before_lbp' => 45045, 'net_pay' => 45045]);
        foreach (EarningType::factory()->count(45)->create() as $index => $type) {
            $details[0]->earnings()->create(['earning_type_id' => $type->getKey(), 'earning_name_snapshot' => 'Saved earning '.$index, 'amount' => 1, 'is_taxable' => false]);
        }

        $response = $this->get(route('payroll-runs.register', $run))->assertOk();
        $sheet = $this->spreadsheet($run);
        $this->assertCount(56, $sheet[0]);
        $this->assertCount(14, $sheet);
        $this->assertSame($response->viewData('rows')[0], $sheet[1]);
        $this->assertSame($response->viewData('rows')[11], $sheet[12]);
        $this->assertSame(540045.0, $sheet[13][55]);
        $this->assertSame(array_fill(0, 45, 1.0), array_slice($sheet[13], 5, 45));
    }

    private function officer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    /** @return list<list<string|float>> */
    private function spreadsheet(PayrollRun $run): array
    {
        $response = $this->get(route('payroll-runs.register.export', $run))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'historical-payroll-');
        file_put_contents($path, $response->streamedContent());
        $archive = new ZipArchive;
        try {
            $this->assertTrue($archive->open($path, ZipArchive::CHECKCONS) === true);
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($archive->getFromName('xl/worksheets/sheet1.xml')));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame(0, $xpath->query('//s:f')->length);
            $this->assertSame('landscape', $xpath->evaluate('string(//s:pageSetup/@orientation)'));
            $lastColumn = preg_replace('/\d+/', '', $xpath->evaluate('string((//s:row[@r="3"]/s:c)[last()]/@r)'));
            $lastRow = $xpath->evaluate('string((//s:sheetData/s:row)[last()]/@r)');
            $this->assertSame('A1:'.$lastColumn.$lastRow, $xpath->evaluate('string(//s:dimension/@ref)'));
            $workbook = new DOMDocument;
            $workbook->loadXML($archive->getFromName('xl/workbook.xml'));
            $workbookPath = new DOMXPath($workbook);
            $workbookPath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('Payroll!$B$1:$'.$lastColumn.'$'.$lastRow, $workbookPath->evaluate('string(//s:definedName[@name="_xlnm.Print_Area"])'));
            $rows = [];
            foreach ($xpath->query('//s:sheetData/s:row[@r >= 3]') as $row) {
                $values = [];
                foreach ($xpath->query('s:c', $row) as $cell) {
                    $values[] = $cell->getAttribute('t') === 'inlineStr'
                        ? $xpath->evaluate('string(s:is/s:t)', $cell)
                        : (float) $xpath->evaluate('string(s:v)', $cell);
                }
                $rows[] = $values;
            }

            return $rows;
        } finally {
            $archive->close();
            unlink($path);
        }
    }
}
