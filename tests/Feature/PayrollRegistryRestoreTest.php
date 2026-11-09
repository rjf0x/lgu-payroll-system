<?php

namespace Tests\Feature;

use App\Models\PayrollRegistration;
use App\Models\User;
use App\Services\PayrollVerificationService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('registry-restore')]
class PayrollRegistryRestoreTest extends TestCase
{
    public function test_restored_mysql_snapshot_verifies_against_the_original_blockchain_registration(): void
    {
        if (! config('blockchain.registry.restore_check')) {
            $this->markTestSkipped('Run only through the isolated pilot backup/restore workflow.');
        }
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringStartsWith('lgu_payroll_qa_registry_restore_', DB::connection()->getDatabaseName());
        $registration = PayrollRegistration::query()->where('status', 'CONFIRMED')->firstOrFail();
        $reviewer = User::findOrFail($registration->payrollRun->finalized_by);

        $log = app(PayrollVerificationService::class)->verify($registration, $reviewer);

        $this->assertSame('MATCH', $log->verification_result);
        $this->assertSame($registration->payload_hash, $log->stored_hash);
        $this->assertSame($log->stored_hash, $log->calculated_hash);
        $this->artisan('blockchain:readiness', ['--json' => true])->assertSuccessful();
    }
}
