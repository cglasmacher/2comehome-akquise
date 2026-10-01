<?php

namespace App\Services\OnOffice;

use App\Models\Lead;
use Illuminate\Support\Facades\Cache;

class Transfer
{
    public function __construct(private Client $client) {}

    public function duplicates(Lead $lead): array
    {
        $records = [];
        foreach (['email' => $lead->contact->email, 'normalizedPhoneNumbers' => $this->normalizePhone($lead->contact->phone)] as $field => $value) {
            if (! $value) {
                continue;
            }
            foreach ($this->client->call('read', 'address', ['data' => ['Name', 'Vorname', 'Status', 'email', 'phone'], 'filter' => [$field => [['op' => '=', 'val' => $value]]], 'sortby' => 'Id', 'sortorder' => 'DESC', 'listlimit' => 500]) as $record) {
                $records[$record['id']] = $record;
            }
        }
        krsort($records, SORT_NUMERIC);

        return array_values($records);
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! $value) {
            return null;
        } $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '49'.substr($digits, 1);
        }

        return $digits;
    }

    public function payload(Lead $lead): array
    {
        $p = $lead->property;
        $c = $lead->contact;
        $source = collect($p->listings)->map(fn ($l) => $l->source.': '.($l->url ?? $l->external_id))->join("\n");

        return [
            'address' => array_filter(['Name' => $c->name, 'email' => $c->email, 'phone' => $c->phone, 'Benutzer' => config('onoffice.username'), config('onoffice.address_type_field') => [config('onoffice.owner_value')], 'Bemerkung' => 'Akquisetool: '.$source], fn ($v) => $v !== null && $v !== ''),
            'estate' => array_filter(['objektart' => ['apartment' => 'wohnung', 'house' => 'haus', 'land' => 'grundstueck', 'commercial' => 'buero_praxen'][$p->property_type],
                'nutzungsart' => $p->property_type === 'commercial' ? 'gewerbe' : 'wohnen', 'vermarktungsart' => $p->market === 'sale' ? 'kauf' : 'miete',
                'objekttitel' => $p->title, 'objektnr_extern' => 'AKQ-'.$p->id, 'plz' => $p->postal_code, 'ort' => $p->city, 'strasse' => $p->street, 'land' => 'DEU',
                'wohnflaeche' => $p->area, 'anzahl_zimmer' => $p->rooms, ($p->market === 'sale' ? 'kaufpreis' : 'kaltmiete') => $p->price,
                'objektbeschreibung' => ($p->description ?? '')."\n\nQuelle:\n".$source, 'status' => 2,
                config('onoffice.estate_status_field') => config('onoffice.estate_status_value'), 'benutzer' => config('onoffice.user_id')], fn ($v) => $v !== null),
        ];
    }

    public function fingerprint(Lead $lead): string
    {
        $lead->loadMissing(['contact', 'property.listings', 'activities']);

        return hash('sha256', json_encode([
            $this->payload($lead), $lead->contact->onoffice_id, $lead->property->onoffice_id,
            $lead->transfer_state,
            $lead->activities->map(fn ($activity) => $activity->only('id', 'type', 'note', 'occurred_at', 'onoffice_id'))->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function run(Lead $lead, ?int $existingId): void
    {
        $lock = Cache::lock('onoffice:transfer', 360);
        if (! $lock->get()) {
            throw new \RuntimeException('Eine Übertragung läuft bereits. Bitte kurz warten.');
        }
        try {
            $lead->refresh()->load(['contact', 'property.listings', 'activities.user']);
            if (in_array($lead->transfer_state, ['review', 'running'])) {
                throw new \RuntimeException('Eine frühere Übertragung muss zuerst in onOffice abgeglichen werden.');
            }
            $payload = $this->payload($lead);
            if (! $lead->contact->onoffice_id) {
                $duplicates = $this->duplicates($lead);
                if ($duplicates && (! $existingId || ! collect($duplicates)->contains('id', $existingId))) {
                    throw new \RuntimeException('Vorhandenen Kontakt in der Vorschau auswählen.');
                }
                if (! $duplicates && $existingId) {
                    throw new \RuntimeException('Kontaktprüfung hat sich geändert. Vorschau erneut öffnen.');
                }
            }
            $lead->update(['transfer_state' => 'running', 'transfer_error' => null]);
            if (! $lead->contact->onoffice_id) {
                $id = $existingId ?: $this->client->create('address', [...$payload['address'], 'checkDuplicate' => true, 'noOverrideByDuplicate' => true]);
                $lead->contact->update(['onoffice_id' => $id]);
            }
            if (! $lead->property->onoffice_id) {
                // Externe Kennung ist stabil; Abgleich schützt bei bereits vorhandenem Objekt.
                $found = $this->client->call('read', 'estate', ['data' => ['Id', 'objektnr_extern'], 'filter' => ['objektnr_extern' => [['op' => '=', 'val' => 'AKQ-'.$lead->property_id]]], 'listlimit' => 2]);
                if (count($found) > 1) {
                    throw new \RuntimeException('Mehrere Objekte mit gleicher Akquise-Kennung gefunden.');
                }
                $id = $found[0]['id'] ?? $this->client->create('estate', ['data' => $payload['estate']]);
                $lead->property->update(['onoffice_id' => $id]);
            }
            if (! $lead->owner_linked) {
                $this->client->call('create', 'relation', ['parentid' => [$lead->property->onoffice_id], 'childid' => [$lead->contact->onoffice_id], 'relationtype' => 'urn:onoffice-de-ns:smart:2.5:relationTypes:estate:address:owner']);
                $lead->update(['owner_linked' => true]);
            }
            foreach ($lead->activities->sortBy('id') as $activity) {
                if ($activity->onoffice_id) {
                    continue;
                }
                $id = $this->client->create('agentslog', ['addressids' => [$lead->contact->onoffice_id], 'estateid' => $lead->property->onoffice_id,
                    'datetime' => $activity->occurred_at->timezone('Europe/Berlin')->format('Y-m-d H:i:s'), 'actionkind' => config('onoffice.activity_kind'), 'actiontype' => config('onoffice.activity_type'), 'userid' => config('onoffice.user_id'),
                    'note' => 'Akquise #'.$lead->id.' / Aktivität #'.$activity->id.' / '.($activity->user?->name ?? 'System')."\n".$activity->note]);
                $activity->update(['onoffice_id' => $id]);
            }
            $lead->update(['transfer_state' => 'completed', 'transferred_at' => now(), 'transfer_error' => null]);
        } catch (\Throwable $e) {
            if ($lead->transfer_state === 'running') {
                $lead->update(['transfer_state' => $e instanceof ApiException && ! $e->uncertain ? 'failed' : 'review', 'transfer_error' => $e instanceof ApiException ? $e->getMessage() : 'Übertragung abgebrochen. Vor Wiederholung die vorhandenen Datensätze abgleichen.']);
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
