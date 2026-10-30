<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollSnapshotArchive;
use App\Models\PayrollWorkflowEvent;
use App\Models\User;
use App\Services\Blockchain\BlockchainReader;
use App\Services\Blockchain\BlockchainSubmitter;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use App\Services\PayrollAnchoringService;
use App\Services\PayrollDocumentService;
use App\Services\PayrollSnapshotService;
use App\Services\PayrollVerificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\FinalizedPayrollFixture;
use Tests\TestCase;

class PayrollSnapshotTest extends TestCase
{
    use FinalizedPayrollFixture;

    private User $officer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->app->instance(BlockchainSubmitter::class, Mockery::mock(BlockchainSubmitter::class)->shouldNotReceive('submit')->getMock());
        $this->app->instance(BlockchainReader::class, Mockery::mock(BlockchainReader::class)->shouldNotReceive('receipt')->getMock());
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->approver = User::factory()->create(['role' => Role::Approver]);
        $this->actor($this->officer);
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_finalization_atomically_preserves_exact_v1_bytes_and_digest_without_preparing_or_submitting(PersonnelClassification $category): void
    {
        [$batch, $final] = $this->fixture($category);
        $archive = PayrollSnapshotArchive::sole()->fresh();
        $expected = (new CanonicalPayrollSnapshot)->encodeFinalization($batch->id, $final->id, $final->version, $final->snapshot);
        $this->assertSame($expected, $archive->canonical_bytes);
        $this->assertSame(hash('sha256', $expected), $archive->sha256);
        $this->assertSame(PayrollSnapshotService::SCHEMA, $archive->schema_version);
        $this->assertSame(CanonicalPayrollSnapshot::VERSION, $archive->canonicalization);
        $this->assertSame('sha256', $archive->algorithm);
        $this->assertSame(32, strlen(hex2bin($archive->sha256)));
        $this->assertSame($archive->sha256, app(PayrollAnchoringService::class)->digest($batch, $final));
        $this->assertFalse(str_ends_with($archive->canonical_bytes, "\n"));
        $this->assertDatabaseCount('payroll_commitments', 0);
        $this->assertDatabaseCount('payroll_anchor_attempts', 0);
        $this->assertDatabaseCount('jobs', 0);
        $before = $archive->getAttributes();
        app(PayrollSnapshotService::class)->archive($final);
        $this->assertSame($before, $archive->fresh()->getAttributes());
    }

    public function test_archive_failure_rolls_back_finalization_and_workflow_event(): void
    {
        $this->app->instance(PayrollSnapshotService::class, Mockery::mock(PayrollSnapshotService::class)
            ->shouldReceive('archive')->once()->andThrow(new RuntimeException('Injected archive failure'))->getMock());
        $this->withoutExceptionHandling();
        try {
            $this->fixture();
            $this->fail('Finalization committed without its bytes.');
        } catch (RuntimeException $error) {
            $this->assertSame('Injected archive failure', $error->getMessage());
        }
        $this->assertSame('approved', PayrollBatch::sole()->status);
        $this->assertDatabaseCount('payroll_finalizations', 0);
        $this->assertDatabaseCount('payroll_snapshot_archives', 0);
        $this->assertSame(0, PayrollWorkflowEvent::where('action', 'finalize')->count());
    }

    public function test_independent_v1_vector_and_amount_or_order_changes_have_expected_hashes(): void
    {
        $bytes = rtrim(file_get_contents(base_path('tests/Fixtures/payroll-commitment-v1-vector.json')), "\r\n");
        $input = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        $encoder = new CanonicalPayrollSnapshot;
        $this->assertSame($bytes, $encoder->encodeFinalization(11, 21, 2, $input['snapshot']));
        // Independently calculated with Node's crypto SHA-256, not this encoder.
        $this->assertSame('b59b444791271872119426f8ffa81071b2d4c41cf047b765d779f2a1f9bde575', hash('sha256', $bytes));
        $amount = $input;
        $amount['snapshot']['payroll']['employees'][0]['amount'] = '12345.68';
        $this->assertNotSame(hash('sha256', $bytes), hash('sha256', $encoder->encode($amount)));
        $order = $input;
        $order['snapshot']['payroll']['employees'] = array_reverse($order['snapshot']['payroll']['employees']);
        $this->assertNotSame($bytes, $encoder->encode($order));
        $this->assertNotSame($bytes, $encoder->encodeFinalization(11, 21, 3, $input['snapshot']));
    }

    public function test_employee_creation_order_is_deterministic_but_a_changed_saved_list_is_detected(): void
    {
        [$batch, $final] = $this->fixture(employeeCount: 3);
        $ids = array_column(array_column($final->snapshot['payroll']['employees'], 'employee'), 'employee_id');
        $this->assertSame(Employee::orderBy('id')->pluck('id')->all(), $ids);
        $archive = PayrollSnapshotArchive::sole();
        $changed = $final->snapshot;
        $changed['payroll']['employees'] = array_reverse($changed['payroll']['employees']);
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($changed)]);
        $this->assertNotSame($archive->sha256, app(PayrollAnchoringService::class)->digest($batch, $final->fresh()));
        $this->expectException(HttpException::class);
        app(PayrollSnapshotService::class)->assertPreserved($batch, $final->fresh());
    }

    public function test_legacy_unicode_and_string_line_endings_round_trip_as_exact_bytes(): void
    {
        [$batch, $final] = $this->fixture();
        DB::table('payroll_snapshot_archives')->delete();
        $snapshot = $final->snapshot;
        $snapshot['workflow'][0]['reason'] = "FICTIONAL Peña/測試\r\nline";
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE)]);
        $archive = app(PayrollSnapshotService::class)->archive($final->fresh())->fresh();
        $this->assertStringContainsString('Peña/測試\\r\\nline', $archive->canonical_bytes);
        $this->assertSame($archive->canonical_bytes, app(PayrollAnchoringService::class)->bytes($batch, $final->fresh()));
        $this->assertSame(hash('sha256', $archive->canonical_bytes), $archive->sha256);
    }

    public function test_database_json_order_and_live_employee_changes_cannot_change_saved_bytes(): void
    {
        [$batch, $final] = $this->fixture();
        $archive = PayrollSnapshotArchive::sole();
        $reverse = function ($value) use (&$reverse) {
            if (! is_array($value)) {
                return $value;
            }
            $result = array_map($reverse, $value);

            return array_is_list($result) ? $result : array_reverse($result, true);
        };
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($reverse($final->snapshot), JSON_PRETTY_PRINT)]);
        Employee::sole()->forceFill(['first_name' => 'Later fictional name', 'is_active' => false])->save();
        app(PayrollSnapshotService::class)->assertPreserved($batch->fresh(), $final->fresh());
        $this->assertSame($archive->canonical_bytes, app(PayrollSnapshotService::class)->currentBytes($batch->fresh(), $final->fresh()));
        $this->assertSame($archive->sha256, hash('sha256', $archive->fresh()->canonical_bytes));
    }

    public function test_changed_snapshot_without_manual_commitment_is_detected_and_cannot_be_rearchived(): void
    {
        [$batch, $final] = $this->fixture();
        $before = PayrollSnapshotArchive::sole()->getAttributes();
        $snapshot = $final->snapshot;
        $snapshot['workflow'][0]['reason'] = 'ISOLATED tamper probe';
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $inspection = app(PayrollVerificationService::class)->inspect($batch, $final->fresh());
        $this->assertSame('invalid_snapshot', $inspection['reason_code']);
        $this->assertNotSame($before['sha256'], $inspection['current_hash']);
        try {
            app(PayrollSnapshotService::class)->archive($final);
            $this->fail('Changed snapshot replaced archived bytes.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertSame($before, PayrollSnapshotArchive::sole()->getAttributes());
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public function test_corrupt_archive_is_detected_without_creating_a_commitment(): void
    {
        [$batch, $final] = $this->fixture();
        DB::table('payroll_snapshot_archives')->update(['canonical_bytes' => '{}']);
        $this->assertSame('invalid_snapshot', app(PayrollVerificationService::class)->inspect($batch, $final)['reason_code']);
        $this->post(route('payroll-anchoring.act', [$batch, $final, 'prepare']), [
            'sha256' => app(PayrollAnchoringService::class)->digest($batch, $final), 'demonstration_confirmed' => 1,
        ])->assertStatus(409);
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public static function archiveMismatches(): array
    {
        return [
            'changed source, unprepared' => ['snapshot', false],
            'corrupt archive, unprepared' => ['archive', false],
            'changed source, prepared' => ['snapshot', true],
            'corrupt archive, prepared' => ['archive', true],
        ];
    }

    #[DataProvider('archiveMismatches')]
    public function test_document_issuance_and_anchoring_controls_block_archive_mismatches(string $changed, bool $prepared): void
    {
        // Production-style errors must not include the debug renderer's test source/arguments.
        config(['app.debug' => false]);
        [$batch, $final] = $this->fixture();
        $employee = $final->snapshot['payroll']['employees'][0]['employee']['employee_id'];
        if ($prepared) {
            $this->post(route('payroll-anchoring.act', [$batch, $final, 'prepare']), [
                'sha256' => app(PayrollAnchoringService::class)->digest($batch, $final), 'demonstration_confirmed' => 1,
            ])->assertRedirect();
        }
        $commitment = PayrollCommitment::first()?->getAttributes();
        if ($changed === 'snapshot') {
            $snapshot = $final->snapshot;
            $snapshot['payroll']['employees'][0]['employee']['employee_name'] = 'ISOLATED CHANGED FICTIONAL NAME';
            DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        } else {
            DB::table('payroll_snapshot_archives')->update(['canonical_bytes' => '{}']);
        }
        $final->refresh();
        $retained = [$final->getAttributes(), PayrollSnapshotArchive::sole()->getAttributes()];
        foreach ([
            route('payroll-documents.index', $batch),
            route('payroll-documents.register', [$batch, $final]),
            route('payroll-documents.register-pdf', [$batch, $final]),
            route('payroll-documents.register-csv', [$batch, $final]),
            route('payroll-documents.payslip', [$batch, $final, $employee]),
            route('payroll-documents.payslip-pdf', [$batch, $final, $employee]),
        ] as $url) {
            $this->get($url)->assertStatus(409)->assertDontSee('ISOLATED CHANGED FICTIONAL NAME');
        }
        try {
            app(PayrollDocumentService::class)->prepare($batch, $final);
            $this->fail('Document preparation accepted an archive mismatch.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()
            ->assertSee('Submission is blocked;')->assertDontSee('name="demonstration_confirmed"', false);
        $this->post(route('payroll-anchoring.act', [$batch, $final, $prepared ? 'submit' : 'prepare']), [
            'sha256' => app(PayrollAnchoringService::class)->digest($batch, $final), 'demonstration_confirmed' => 1,
        ])->assertStatus(409);
        $this->assertSame($retained, [$final->fresh()->getAttributes(), PayrollSnapshotArchive::sole()->getAttributes()]);
        $this->assertSame($commitment, PayrollCommitment::first()?->getAttributes());
        $this->assertDatabaseCount('payroll_anchor_attempts', 0);
    }

    public function test_legacy_documents_and_preparation_page_remain_read_only_and_available_without_archive(): void
    {
        [$batch, $final] = $this->fixture();
        // Simulate a valid legacy finalization, not removal through any supported application action.
        DB::table('payroll_snapshot_archives')->delete();
        $before = $final->getAttributes();
        $this->get(route('payroll-documents.register-csv', [$batch, $final]))->assertOk();
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()
            ->assertSee('Prepare local commitment')->assertSee('name="demonstration_confirmed"', false);
        $this->assertSame($before, $final->fresh()->getAttributes());
        $this->assertDatabaseCount('payroll_snapshot_archives', 0);
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    private function legacy(): array
    {
        [$batch, $final] = $this->fixture();
        $digest = PayrollSnapshotArchive::sole()->sha256;
        // Isolated fixture simulates a pre-migration finalization; production code never deletes archives.
        DB::table('payroll_snapshot_archives')->delete();
        $commitment = PayrollCommitment::forceCreate([
            'payroll_finalization_id' => $final->id, 'canonicalization' => CanonicalPayrollSnapshot::VERSION,
            'algorithm' => 'sha256', 'sha256' => $digest, 'submission_key' => (string) Str::uuid(), 'created_by' => $this->approver->id,
        ]);

        return [$batch, $final, $commitment->fresh()];
    }

    public function test_legacy_dry_run_apply_and_rerun_preserve_original_hash_key_and_snapshot(): void
    {
        [$batch, $final, $commitment] = $this->legacy();
        $before = [$final->getAttributes(), $commitment->getAttributes()];
        $this->assertSame($commitment->sha256, app(PayrollAnchoringService::class)->digest($batch, $final));
        $this->artisan('payroll:archive-snapshots')->expectsOutput('Dry run: no records will be written.')->assertSuccessful();
        $this->assertDatabaseCount('payroll_snapshot_archives', 0);
        $this->artisan('payroll:archive-snapshots', ['--apply' => true])->assertSuccessful();
        $archive = PayrollSnapshotArchive::sole()->getAttributes();
        $this->artisan('payroll:archive-snapshots', ['--apply' => true, '--finalization' => (string) $final->id])->assertSuccessful();
        $this->assertSame($archive, PayrollSnapshotArchive::sole()->getAttributes());
        $this->assertSame($commitment->sha256, $archive['sha256']);
        $this->assertSame($before, [$final->fresh()->getAttributes(), $commitment->fresh()->getAttributes()]);
    }

    public function test_legacy_digest_mismatch_blocks_dry_run_and_apply_without_repair(): void
    {
        [, $final, $commitment] = $this->legacy();
        DB::table('payroll_commitments')->where('id', $commitment->id)->update(['sha256' => str_repeat('0', 64)]);
        foreach ([[], ['--apply' => true]] as $options) {
            $this->artisan('payroll:archive-snapshots', $options)->assertFailed();
        }
        $this->assertDatabaseCount('payroll_snapshot_archives', 0);
        $this->assertSame(str_repeat('0', 64), $commitment->fresh()->sha256);
        $this->assertSame($final->snapshot, $final->fresh()->snapshot);
    }

    public function test_legacy_snapshot_without_commitment_can_be_explicitly_captured_without_preparation(): void
    {
        [, $final] = $this->fixture();
        $original = PayrollSnapshotArchive::sole()->sha256;
        DB::table('payroll_snapshot_archives')->delete();
        $this->artisan('payroll:archive-snapshots', ['--finalization' => (string) $final->id])->assertSuccessful();
        $this->assertDatabaseCount('payroll_snapshot_archives', 0);
        $this->artisan('payroll:archive-snapshots', ['--apply' => true])->assertSuccessful();
        $this->assertSame($original, PayrollSnapshotArchive::sole()->sha256);
        $this->assertDatabaseCount('payroll_commitments', 0);
        $this->artisan('payroll:archive-snapshots', ['--finalization' => '-1'])->assertFailed();
        $this->artisan('payroll:archive-snapshots', ['--finalization' => '999999'])->assertFailed();
    }

    public function test_archive_model_uniqueness_and_retention_guards(): void
    {
        $migration = require database_path('migrations/2026_10_30_000001_create_payroll_snapshot_archives.php');
        $migration->down();
        $migration->up();
        $this->fixture();
        $archive = PayrollSnapshotArchive::sole();
        foreach (['update', 'delete', 'rollback'] as $action) {
            try {
                match ($action) {
                    'update' => $archive->forceFill(['sha256' => str_repeat('0', 64)])->save(),
                    'delete' => $archive->delete(),
                    'rollback' => $migration->down(),
                };
                $this->fail('Retained bytes could be changed or removed.');
            } catch (LogicException) {
                $this->assertSame(hash('sha256', $archive->fresh()->canonical_bytes), $archive->fresh()->sha256);
            }
        }
        try {
            DB::table('payroll_snapshot_archives')->insert(collect($archive->getAttributes())->except('id')->all());
            $this->fail('Duplicate finalization archive accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('payroll_snapshot_archives', 1);
        }
        try {
            DB::table('payroll_finalizations')->where('id', $archive->payroll_finalization_id)->delete();
            $this->fail('Archived parent deleted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('payroll_finalizations', 1);
        }
    }
}
