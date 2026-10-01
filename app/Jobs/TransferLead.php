<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\OnOffice\Transfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TransferLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public int $leadId, public ?int $existingContactId) {}

    public function handle(Transfer $transfer): void
    {
        $lead = Lead::find($this->leadId);
        if (! $lead || $lead->transfer_state !== 'queued') {
            return;
        }
        try {
            $transfer->run($lead, $this->existingContactId);
        } catch (\Throwable $e) {
            Lead::whereKey($lead->id)->where('transfer_state', 'queued')->update(['transfer_state' => 'failed', 'transfer_error' => 'Übertragung nicht gestartet. Kontaktwahl oder Konfiguration prüfen.']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Lead::whereKey($this->leadId)->whereIn('transfer_state', ['queued', 'running'])->update(['transfer_state' => 'review', 'transfer_error' => 'Hintergrundprozess abgebrochen. Vor Wiederholung in onOffice abgleichen.']);
    }
}
