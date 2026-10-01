<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;

class ReconcileTransfer extends Command
{
    protected $signature = 'onoffice:reconcile {lead} {--address=} {--estate=} {--activity=*} {--owner-linked}';

    protected $description = 'Nach manueller onOffice-Prüfung externe IDs zuordnen und unklaren Transfer freigeben';

    public function handle(): int
    {
        $lead = Lead::findOrFail($this->argument('lead'));
        if (! $this->confirm('Datensätze und Aktivitäten in onOffice geprüft? Nur tatsächlich vorhandene IDs zuordnen.')) {
            return self::FAILURE;
        }
        foreach (['address', 'estate'] as $option) {
            if ($this->option($option) && ! ctype_digit($this->option($option))) {
                $this->error('IDs müssen positive Ganzzahlen sein.');

                return self::FAILURE;
            }
        }
        if ($this->option('address')) {
            $lead->contact->update(['onoffice_id' => (int) $this->option('address')]);
        }
        if ($this->option('estate')) {
            $lead->property->update(['onoffice_id' => (int) $this->option('estate')]);
        }
        foreach ($this->option('activity') as $mapping) {
            if (! preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', $mapping, $m)) {
                $this->error('Aktivität als lokaleID:onOfficeID angeben.');

                return self::FAILURE;
            }
            $lead->activities()->findOrFail($m[1])->update(['onoffice_id' => (int) $m[2]]);
        }
        $lead->update(['owner_linked' => $lead->owner_linked || $this->option('owner-linked'), 'transfer_state' => 'failed', 'transfer_error' => null]);
        $this->info('Abgleich gespeichert. Fehlende Schritte können über die Vorschau ergänzt werden.');

        return self::SUCCESS;
    }
}
