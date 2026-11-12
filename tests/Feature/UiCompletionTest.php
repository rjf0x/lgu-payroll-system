<?php

namespace Tests\Feature;

use App\Models\GeneratedReport;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\BlockchainAnchorService;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class UiCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(string $roleName = Role::ADMIN): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    public static function secondaryScreens(): array
    {
        $indexes = ['departments', 'positions', 'employment-records', 'salary-schedules', 'salary-grade-steps', 'compensation-profiles', 'earning-types', 'deduction-types', 'employee-earning-assignments', 'employee-deduction-assignments', 'tax-brackets', 'contribution-rates', 'payroll-periods', 'payroll-runs', 'payslips', 'users', 'blockchain', 'reports'];
        $cases = [];

        foreach ($indexes as $name) {
            if ($name !== 'payslips') {
                $cases[$name.' list'] = [$name.'.index'];
            }
        }

        foreach (['departments', 'positions', 'employees', 'users', 'earning-types', 'deduction-types', 'tax-brackets', 'contribution-rates', 'payroll-periods'] as $name) {
            $cases[$name.' form'] = [$name.'.create', $name === 'payroll-periods' ? Role::PAYROLL_OFFICER : Role::ADMIN];
        }

        return $cases;
    }

    #[DataProvider('secondaryScreens')]
    public function test_secondary_screens_have_named_table_regions_and_labelled_controls(string $routeName, string $roleName = Role::ADMIN): void
    {
        $user = $this->administrator($roleName);

        $response = $this->actingAs($user)->get(route($routeName))->assertOk();

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//main//table[not(ancestor::div[@role="region" and @aria-label and @tabindex="0"])]')->length);
        $this->assertSame(0, $xpath->query('//main//th[not(@scope)]')->length);
        $this->assertSame(0, $xpath->query('//main//form[@method="POST" and not(@data-submit-feedback)]')->length);

        foreach ($xpath->query('//main//*[contains(concat(" ", normalize-space(@class), " "), " ui-button ")]') as $button) {
            $this->assertSame(1, $xpath->query('svg[@aria-hidden="true"]', $button)->length, 'Missing or duplicate action icon on '.$routeName);
        }

        foreach ($xpath->query('//main//input[not(@type="hidden")]|//main//select|//main//textarea') as $field) {
            $labelled = $field->hasAttribute('aria-label') || $xpath->query('ancestor::label', $field)->length > 0;

            if ($field->hasAttribute('id')) {
                $labelled = $labelled || $xpath->query('//label[@for="'.$field->getAttribute('id').'"]')->length > 0;
            }

            $this->assertTrue($labelled, 'Unlabelled '.$field->nodeName.' on '.$routeName);
        }
    }

    public function test_reports_searches_periods_and_displays_readable_scope_without_raw_filters(): void
    {
        $user = $this->administrator();
        $period = PayrollPeriod::factory()->create(['period_name' => 'Target payroll period']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'status' => 'FINALIZED']);
        PayrollRun::factory()->create(['status' => 'DRAFT']);
        GeneratedReport::create([
            'report_type' => 'PAYROLL_REGISTER', 'generated_by' => $user->getKey(),
            'filters' => ['payroll_run_id' => $run->getKey(), 'untrusted' => '<script>private-marker</script>'],
            'generated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('reports.index', ['q' => 'Target', 'status' => 'FINALIZED']))
            ->assertOk()->assertSee('Target payroll period')->assertSee('Export history')
            ->assertDontSee('private-marker')->assertDontSee('payroll_run_id')
            ->assertViewHas('runs', fn ($runs) => $runs->total() === 1 && $runs->first()->getKey() === $run->getKey())
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_unknown_report_scope_is_handled_without_rendering_arbitrary_metadata(): void
    {
        $user = $this->administrator();
        GeneratedReport::create(['report_type' => 'PAYROLL_REGISTER', 'generated_by' => $user->getKey(), 'filters' => ['payroll_run_id' => ['malformed']], 'generated_at' => now()]);

        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Payroll scope unavailable')->assertDontSee('malformed');
    }

    public function test_register_and_summary_identify_working_copies_and_offer_print_actions(): void
    {
        $user = $this->administrator();
        $run = PayrollRun::factory()->create(['status' => 'DRAFT']);

        $this->actingAs($user)->get(route('payroll-runs.register', $run))->assertOk()->assertSee('Working copy.')->assertSee('Print register')->assertSee('Pay date');
        $this->get(route('reports.payroll-summary', $run))->assertOk()->assertSee('Print summary')->assertSee('Amounts in PHP.');
    }

    public function test_register_spreadsheet_preserves_formula_text_without_executing_it(): void
    {
        $user = $this->administrator();
        $run = PayrollRun::factory()->create(['status' => 'FINALIZED']);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employee_name_snapshot' => '=SUM(1,1)']);

        $response = $this->actingAs($user)->get(route('payroll-runs.register.export', $run))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'payroll-test-');
        $archive = new ZipArchive;
        try {
            file_put_contents($path, $response->streamedContent());
            $this->assertTrue($archive->open($path) === true);
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($archive->getFromName('xl/worksheets/sheet1.xml')));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('inlineStr', $xpath->evaluate('string(//s:c[@r="D4"]/@t)'));
            $this->assertSame('=SUM(1,1)', $xpath->evaluate('string(//s:c[@r="D4"]/s:is/s:t)'));
            $this->assertSame(0, $xpath->query('//s:c[@r="D4"]/s:f')->length);
        } finally {
            $archive->close();
            unlink($path);
        }
    }

    public function test_anchor_page_prioritizes_latest_integrity_result_over_technical_evidence(): void
    {
        $user = $this->administrator();
        config(['blockchain.driver' => 'simulated']);
        $run = PayrollRun::factory()->create(['status' => 'FINALIZED']);
        $service = app(BlockchainAnchorService::class);
        $anchor = $service->anchor($run, $user);
        $service->verify($anchor, $user);
        $anchor->update(['payload_hash' => str_repeat('a', 64)]);
        $service->verify($anchor, $user);

        $this->actingAs($user)->get(route('blockchain-anchors.show', $anchor))->assertOk()
            ->assertSeeInOrder(['Integrity verification', 'Mismatch', 'Stored Payload Hash', 'Verification History'])
            ->assertSee('Simulated proof:');
    }
}
