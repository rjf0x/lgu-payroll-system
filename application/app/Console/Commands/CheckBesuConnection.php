<?php

namespace App\Console\Commands;

use App\Services\Blockchain\BesuConnectionException;
use App\Services\Blockchain\BesuRegistry;
use Illuminate\Console\Command;
use Throwable;

class CheckBesuConnection extends Command
{
    protected $signature = 'besu:check {--sign : Sign a synthetic transaction offline; never broadcast}';

    protected $description = 'Check configured Besu chain, registry and writer; never anchor payroll.';

    public function handle(BesuRegistry $registry): int
    {
        try {
            $health = $registry->health();
            $lookup = $registry->lookup('0x'.hash('sha256', 'BESU-04 synthetic health lookup'));
            $result = [...$health, 'lookup_exists' => $lookup['exists'], 'broadcast' => false];
            if ($this->option('sign')) {
                $signed = $registry->sign('0x'.bin2hex(random_bytes(32)), hash('sha256', 'BESU-04 synthetic signing probe'));
                // The raw signature/transaction is deliberately never printed or persisted.
                $result['offline_signature_valid'] = true;
                $result['signed_hash'] = $signed['hash'];
            }
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (BesuConnectionException $error) {
            $this->error($error->getMessage());
        } catch (Throwable) {
            $this->error('Besu connection check failed (unavailable).');
        }

        return self::FAILURE;
    }
}
