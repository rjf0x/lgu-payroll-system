<?php

namespace App\Console\Commands;

use App\Models\BesuAnchor;
use App\Services\BesuAnchoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessBesuAnchors extends Command
{
    protected $signature = 'besu:anchor {--limit=20 : Maximum durable outbox entries to process}';

    protected $description = 'Submit/reconcile authorized finalized payroll commitments from the durable Besu outbox.';

    public function handle(BesuAnchoringService $service): int
    {
        if (! config('besu.enabled') || ! config('besu.automatic_anchoring')) {
            $this->error('Automatic Besu anchoring is disabled.');

            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 100.');

            return self::FAILURE;
        }
        $incomplete = false;
        foreach (BesuAnchor::whereIn('status', ['queued', 'signed', 'pending'])->orderBy('updated_at')->orderBy('id')->limit($limit)->pluck('id') as $id) {
            // Rotate even incomplete attempts so persistent head entries cannot monopolize every run.
            DB::table('besu_anchor_outbox')->where('id', $id)->whereIn('status', ['queued', 'signed', 'pending'])
                ->update(['updated_at' => now()]);
            $status = $service->process($id);
            $this->line('Outbox '.$id.': '.$status);
            $incomplete = $incomplete || in_array($status, ['incomplete', 'failed'], true);
        }

        return $incomplete ? self::FAILURE : self::SUCCESS;
    }
}
