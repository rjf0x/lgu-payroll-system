<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class PayrollSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        $this->seed([RolePermissionSeeder::class, DeductionTypeSeeder::class, EarningTypeSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    /** @return array<string, string> */
    private function workbookParts(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'payroll-test-');
        file_put_contents($path, $contents);
        $archive = new ZipArchive;
        try {
            $this->assertTrue($archive->open($path, ZipArchive::CHECKCONS) === true);
            $this->assertFalse($archive->locateName('xl/calcChain.xml'));
            $parts = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                    $parts[$name] = $archive->getFromIndex($index);
                    $this->assertTrue((new DOMDocument)->loadXML($parts[$name]), $name);
                }
            }

            return $parts;
        } finally {
            $archive->close();
            unlink($path);
        }
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    }

    public function test_export_maps_amounts_to_template_columns_and_preserves_formatting(): void
    {
        $officer = $this->officer();
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey()]);
        $detail = PayrollDetail::factory()->create([
            'payroll_run_id' => $run->getKey(), 'employee_number_snapshot' => '000123',
            'employee_name_snapshot' => 'Peña & Sons <Office>', 'basic_pay' => 10000,
            'gross_pay' => 12000, 'total_deductions' => 900, 'nthp_before_lbp' => 11100,
            'lbp_loan_deduction' => 100, 'net_pay' => 11000,
        ]);
        $detail->earnings()->create([
            'earning_type_id' => EarningType::where('earning_name', 'PERA')->value('earning_type_id'),
            'earning_name_snapshot' => 'PERA', 'amount' => 2000, 'is_taxable' => false,
        ]);
        foreach (['GSIS_LOAN' => 300, 'PAGIBIG_LOAN' => 400] as $category => $amount) {
            $type = DeductionType::where('deduction_name', 'MPL')->where('deduction_category', $category)->firstOrFail();
            $detail->deductions()->create(['deduction_type_id' => $type->getKey(), 'deduction_name_snapshot' => 'MPL', 'amount' => $amount]);
        }
        $type = DeductionType::where('deduction_name', 'PhilHealth Contribution')->firstOrFail();
        $detail->deductions()->create(['deduction_type_id' => $type->getKey(), 'deduction_name_snapshot' => $type->deduction_name, 'amount' => 200]);

        $response = $this->actingAs($officer)->get(route('payroll-runs.register.export', $run))->assertOk();
        $parts = $this->workbookParts($response->streamedContent());
        $sheet = $this->xpath($parts['xl/worksheets/sheet1.xml']);

        $this->assertSame('000123', $sheet->evaluate('string(//s:c[@r="C4"]/s:is/s:t)'));
        $this->assertSame('Peña & Sons <Office>', $sheet->evaluate('string(//s:c[@r="D4"]/s:is/s:t)'));
        foreach (['F4' => '10000.00', 'G4' => '2000.00', 'M4' => '12000.00', 'T4' => '300.00', 'Z4' => '400.00', 'AJ4' => '900.00', 'AK4' => '11100.00', 'AL4' => '100.00', 'AM4' => '11000.00', 'AN4' => '200.00', 'AM12' => '11000.00'] as $cell => $amount) {
            $this->assertSame($amount, $sheet->evaluate('string(//s:c[@r="'.$cell.'"]/s:v)'), $cell);
        }
        $this->assertSame('11/2026 DUES', $sheet->evaluate('string(//s:c[@r="AE3"]/s:is/s:t)'));
        $this->assertSame('PhilHealth Contrib.', $sheet->evaluate('string(//s:c[@r="AN3"]/s:is/s:t)'));
        $this->assertSame('GRAND TOTAL', $sheet->evaluate('string(//s:c[@r="D12"]/s:is/s:t)'));
        $this->assertSame(5, $sheet->query('//s:mergeCell')->length);
        $this->assertSame('landscape', $sheet->evaluate('string(//s:pageSetup/@orientation)'));
        $this->assertSame('18', $sheet->evaluate('string(//s:c[@r="M4"]/@s)'));
        $this->assertStringNotContainsString('calcChain.xml', $parts['xl/_rels/workbook.xml.rels']);
        $this->assertStringNotContainsString('calcChain.xml', $parts['[Content_Types].xml']);
    }

    public function test_export_expands_beyond_template_rows_and_updates_print_area(): void
    {
        $officer = $this->officer();
        $run = PayrollRun::factory()->create();
        PayrollDetail::factory()->count(12)->create(['payroll_run_id' => $run->getKey(), 'net_pay' => 123.45]);

        $response = $this->actingAs($officer)->get(route('payroll-runs.register.export', $run))->assertOk();
        $parts = $this->workbookParts($response->streamedContent());
        $sheet = $this->xpath($parts['xl/worksheets/sheet1.xml']);

        $this->assertSame('123.45', $sheet->evaluate('string(//s:c[@r="AM15"]/s:v)'));
        $this->assertSame('1481.40', $sheet->evaluate('string(//s:c[@r="AM16"]/s:v)'));
        $this->assertSame('A1:AN16', $sheet->evaluate('string(//s:dimension/@ref)'));
        $this->assertSame('Payroll!$B$1:$AN$16', $this->xpath($parts['xl/workbook.xml'])->evaluate('string(//s:definedName[@name="_xlnm.Print_Area"])'));
    }

    public function test_empty_run_exports_zero_totals(): void
    {
        $officer = $this->officer();
        $run = PayrollRun::factory()->create();

        $response = $this->actingAs($officer)->get(route('payroll-runs.register.export', $run))->assertOk();
        $parts = $this->workbookParts($response->streamedContent());

        $this->assertSame('0.00', $this->xpath($parts['xl/worksheets/sheet1.xml'])->evaluate('string(//s:c[@r="AM12"]/s:v)'));
    }

    public function test_export_requires_report_generation_permission(): void
    {
        $run = PayrollRun::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('payroll-runs.register.export', $run))->assertForbidden();

        $this->assertDatabaseCount('generated_reports', 0);
    }
}
