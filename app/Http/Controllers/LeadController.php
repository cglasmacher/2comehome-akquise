<?php

namespace App\Http\Controllers;

use App\Jobs\TransferLead;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Services\Imports\ListingImporter;
use App\Services\OnOffice\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LeadController extends Controller
{
    private function canEdit(Request $r, Lead $lead): void
    {
        abort_unless($r->user()->role === 'admin' || $lead->assigned_to === $r->user()->id, 403, 'Nur der zugewiesene Bearbeiter oder Administrator darf diesen Vorgang ändern.');
    }

    public function index(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:200', 'market' => 'nullable|in:sale,rent', 'status' => ['nullable', Rule::in(array_keys(config('acquisition.statuses')))], 'source' => 'nullable|in:manual,immowelt,immoscout24,kleinanzeigen', 'assigned_to' => 'nullable|integer', 'provider_type' => 'nullable|in:private,commercial,unclear', 'due' => 'nullable|boolean']);
        $query = Lead::with(['property.listings', 'contact', 'assignee'])->whereHas('property', fn ($q) => $q->where('market', $filters['market'] ?? 'sale'));
        if ($filters['q'] ?? null) {
            $query->whereHas('property', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$filters['q'].'%')->orWhere('city', 'like', '%'.$filters['q'].'%')->orWhere('postal_code', 'like', str_replace(['X', 'x'], '_', $filters['q']))));
        }
        foreach (['status', 'assigned_to'] as $f) {
            if ($filters[$f] ?? null) {
                $query->where($f, $filters[$f]);
            }
        }
        if ($filters['provider_type'] ?? null) {
            $query->whereHas('contact', fn ($q) => $q->where('provider_type', $filters['provider_type']));
        }
        if ($filters['source'] ?? null) {
            $query->whereHas('property.listings', fn ($q) => $q->where('source', $filters['source']));
        }
        if ($r->boolean('due')) {
            $query->where('acquisition_active', true)->where('follow_up_at', '<=', now());
        }

        return Inertia::render('Leads', ['leads' => $query->latest()->paginate(20)->withQueryString(), 'filters' => $filters, 'users' => User::where('active', true)->get(['id', 'name']),
            'stats' => ['total' => Lead::whereHas('property', fn ($q) => $q->where('market', 'sale'))->count(), 'new' => Lead::where('acquisition_active', true)->where('status', 'new')->count(), 'due' => Lead::where('acquisition_active', true)->where('follow_up_at', '<=', now())->count(), 'won' => Lead::where('status', 'won')->count()]]);
    }

    public function create()
    {
        return Inertia::render('LeadForm');
    }

    public function store(Request $r, ListingImporter $importer)
    {
        $r->validate(['title' => 'required|string', 'property_type' => 'required|in:apartment,house', 'market' => 'required|in:sale,rent']);
        $result = $importer->ingest('manual', [...$r->all(), 'external_id' => (string) Str::uuid()]);
        $result['lead']->update(['assigned_to' => $r->user()->id]);

        return redirect('/leads/'.$result['lead']->id)->with('success', 'Vorgang erfasst.');
    }

    public function show(Lead $lead, Request $r)
    {
        $lead->load(['property.listings', 'contact', 'assignee', 'activities.user']);
        $duplicateHints = Lead::whereKeyNot($lead->id)->where(function ($q) use ($lead) {
            $q->whereHas('contact', function ($c) use ($lead) {
                $c->whereRaw('1=0');
                if ($lead->contact->email) {
                    $c->orWhere('email', $lead->contact->email);
                } if ($lead->contact->phone) {
                    $c->orWhere('phone', $lead->contact->phone);
                }
            });
            if ($lead->property->street) {
                $q->orWhereHas('property', fn ($p) => $p->where('postal_code', $lead->property->postal_code)->where('street', $lead->property->street));
            }
        })->with('property')->limit(10)->get();

        return Inertia::render('LeadDetail', ['lead' => $lead, 'users' => User::where('active', true)->get(['id', 'name']), 'canEdit' => $r->user()->role === 'admin' || $lead->assigned_to === $r->user()->id, 'duplicates' => $duplicateHints, 'onofficeEnabled' => (bool) config('onoffice.enabled')]);
    }

    public function update(Lead $lead, Request $r)
    {
        $this->canEdit($r, $lead);
        $data = $r->validate(['status' => ['required', Rule::in(array_keys(config('acquisition.statuses')))], 'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('active', true)], 'follow_up_at' => 'nullable|date', 'acquisition_active' => 'required|boolean', 'contact_permission' => 'nullable|string|max:5000',
            'name' => 'nullable|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:100', 'provider_type' => 'required|in:private,commercial,unclear', 'contact_blocked' => 'required|boolean', 'block_reason' => 'nullable|required_if:contact_blocked,true|string|max:5000']);
        if ($r->user()->role !== 'admin') {
            if (isset($data['assigned_to']) && (int) $data['assigned_to'] !== $lead->assigned_to) {
                abort(403);
            } $data['assigned_to'] = $lead->assigned_to;
        }
        DB::transaction(function () use ($lead, $data, $r) {
            $lead->contact->update(collect($data)->only(['name', 'email', 'phone', 'provider_type', 'contact_blocked', 'block_reason'])->all());
            $changes = collect($data)->only(['status', 'assigned_to', 'follow_up_at', 'acquisition_active', 'contact_permission'])->all();
            if ($data['contact_blocked'] || in_array($data['status'], ['blocked', 'won', 'no_interest', 'unsuitable'])) {
                $changes['acquisition_active'] = false;
                $changes['follow_up_at'] = null;
            }
            if ($data['contact_blocked']) {
                $changes['status'] = 'blocked';
            }
            $old = $lead->status;
            $lead->update($changes);
            Activity::create(['lead_id' => $lead->id, 'user_id' => $r->user()->id, 'type' => 'update', 'note' => 'Vorgang aktualisiert. Status: '.config('acquisition.statuses.'.$old).' → '.config('acquisition.statuses.'.$lead->status), 'occurred_at' => now()]);
        });

        return back()->with('success', 'Vorgang gespeichert.');
    }

    public function editProperty(Lead $lead, Request $r)
    {
        $this->canEdit($r, $lead);

        return Inertia::render('PropertyForm', ['lead' => $lead->load('property')]);
    }

    public function saveProperty(Lead $lead, Request $r)
    {
        $this->canEdit($r, $lead);
        $data = $r->validate(['title' => 'required|string|max:255', 'postal_code' => 'nullable|regex:/^[0-9]{5}$/', 'city' => 'nullable|string|max:255', 'street' => 'nullable|string|max:255', 'price' => 'nullable|numeric|min:0|max:999999999999', 'area' => 'nullable|numeric|min:0|max:99999999', 'rooms' => 'nullable|numeric|min:0|max:9999', 'description' => 'nullable|string|max:100000']);
        DB::transaction(function () use ($lead, $data, $r) {
            $lead->property->update($data);
            $lead->touch();
            $lead->activities()->create(['user_id' => $r->user()->id, 'type' => 'update', 'note' => 'Objektdaten manuell aktualisiert.', 'occurred_at' => now()]);
        });

        return redirect('/leads/'.$lead->id)->with('success', 'Objektdaten gespeichert.');
    }

    public function activity(Lead $lead, Request $r)
    {
        $this->canEdit($r, $lead);
        $data = $r->validate(['type' => 'required|in:note,phone,email,portal,appointment', 'note' => 'required|string|max:20000', 'occurred_at' => 'required|date']);
        abort_if($lead->contact->contact_blocked && in_array($data['type'], ['phone', 'email', 'portal']), 422, 'Kontakt ist gesperrt.');
        $lead->activities()->create([...$data, 'user_id' => $r->user()->id]);

        return back()->with('success', 'Aktivität dokumentiert.');
    }

    public function preview(Lead $lead, Request $r, Transfer $transfer)
    {
        $this->canEdit($r, $lead);
        $lead->load(['contact', 'property.listings', 'activities.user']);
        try {
            $duplicates = $lead->contact->onoffice_id ? [] : $transfer->duplicates($lead);
        } catch (\Throwable $e) {
            return back()->with('error', 'onOffice-Vorschau nicht verfügbar. Zugang und Konfiguration prüfen.');
        }
        $token = Str::random(40);
        $r->session()->put('transfer.'.$lead->id, ['token' => $token, 'expires' => now()->addMinutes(10)->timestamp, 'version' => $transfer->fingerprint($lead)]);

        return Inertia::render('TransferPreview', ['lead' => $lead, 'payload' => $transfer->payload($lead), 'duplicates' => $duplicates, 'token' => $token]);
    }

    public function transfer(Lead $lead, Request $r, Transfer $transfer)
    {
        $this->canEdit($r, $lead);
        $data = $r->validate(['confirmed' => 'accepted', 'token' => 'required|string', 'existing_contact_id' => 'nullable|integer']);
        $preview = $r->session()->pull('transfer.'.$lead->id);
        abort_unless($preview && hash_equals($preview['token'], $data['token']) && $preview['expires'] >= now()->timestamp && $preview['version'] === $transfer->fingerprint($lead), 409, 'Vorschau abgelaufen oder Vorgang verändert. Bitte neu öffnen.');
        abort_if(in_array($lead->transfer_state, ['queued', 'running', 'review']), 409, 'Übertragung läuft oder benötigt einen manuellen Abgleich.');
        $reserved = Lead::whereKey($lead->id)->whereNotIn('transfer_state', ['queued', 'running', 'review'])->update(['transfer_state' => 'queued', 'transfer_error' => null]);
        abort_unless($reserved, 409, 'Übertragung wurde bereits eingeplant.');
        try {
            TransferLead::dispatch($lead->id, isset($data['existing_contact_id']) ? (int) $data['existing_contact_id'] : null);
        } catch (\Throwable $e) {
            // Ein Queue-Timeout kann bereits einen Job eingereiht haben: erst abgleichen.
            Lead::whereKey($lead->id)->where('transfer_state', 'queued')->update(['transfer_state' => 'review', 'transfer_error' => 'Queue-Ergebnis unklar. Vor erneutem Einplanen prüfen.']);

            return redirect('/leads/'.$lead->id)->with('error', 'Übertragung konnte nicht sicher eingeplant werden. Queue und Übertragungsstatus prüfen.');
        }

        return redirect('/leads/'.$lead->id)->with('success', 'Übertragung eingeplant. Der Hintergrundprozess übernimmt Kontakt, Immobilie und Historie.');
    }
}
