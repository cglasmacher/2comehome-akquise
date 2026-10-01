<?php

namespace App\Services\Imports;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\Property;
use App\Services\Classifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ListingImporter
{
    public function ingest(string $source, array $item): array
    {
        $item = Validator::make($item, [
            'external_id' => 'required|string|max:190', 'title' => 'required|string|max:255',
            'property_type' => 'required|in:apartment,house,land,commercial', 'market' => 'required|in:sale,rent',
            'postal_code' => 'nullable|regex:/^[0-9]{5}$/', 'city' => 'nullable|string|max:255', 'street' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180',
            'location_approximate' => 'sometimes|boolean', 'price' => 'nullable|numeric|min:0|max:999999999999', 'area' => 'nullable|numeric|min:0|max:99999999', 'rooms' => 'nullable|numeric|min:0|max:9999',
            'description' => 'nullable|string|max:100000', 'name' => 'nullable|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:100',
            'provider_type' => 'nullable|in:private,commercial,unclear', 'url' => 'nullable|url:http,https|max:4000', 'contact_url' => 'nullable|url:http,https|max:4000',
        ])->validate();

        return DB::transaction(function () use ($source, $item) {
            $existing = Listing::where('source', $source)->where('external_id', $item['external_id'])->lockForUpdate()->first();
            $fields = collect($item)->only(['title', 'property_type', 'market', 'postal_code', 'city', 'street', 'latitude', 'longitude', 'location_approximate', 'price', 'area', 'rooms', 'description'])->all();
            if ($existing) {
                $history = $existing->price_history ?? [];
                if (array_key_exists('price', $item) && (string) $existing->price !== (string) $item['price']) {
                    $history[] = ['price' => $item['price'], 'at' => now()->toIso8601String()];
                }
                $existing->property->update($fields);
                $existing->update([...collect($item)->only(['price', 'url', 'contact_url'])->all(), 'last_seen_at' => now(), 'availability' => 'online', 'price_history' => $history]);

                // Bearbeiter, Kontaktkorrekturen, Status und Historie bleiben unangetastet.
                return ['created' => false, 'lead' => Lead::where('property_id', $existing->property_id)->firstOrFail()];
            }
            $classification = app(Classifier::class)->classify($item);
            $contact = Contact::create([...collect($item)->only(['name', 'email', 'phone'])->all(), ...$classification]);
            $property = Property::create($fields);
            Listing::create(['source' => $source, 'external_id' => $item['external_id'], 'property_id' => $property->id, 'contact_id' => $contact->id,
                ...collect($item)->only(['price', 'url', 'contact_url'])->all(), 'first_seen_at' => now(), 'last_seen_at' => now(), 'price_history' => isset($item['price']) ? [['price' => $item['price'], 'at' => now()->toIso8601String()]] : []]);
            $lead = Lead::create(['property_id' => $property->id, 'contact_id' => $contact->id, 'acquisition_active' => $item['market'] === 'sale' && $item['property_type'] !== 'land' && $item['property_type'] !== 'commercial' && $classification['provider_type'] !== 'commercial' && ! $classification['contact_blocked'],
                'status' => $classification['contact_blocked'] ? 'blocked' : 'new']);
            Activity::create(['lead_id' => $lead->id, 'type' => 'import', 'note' => 'Erfasst aus '.$source, 'occurred_at' => now()]);

            return ['created' => true, 'lead' => $lead];
        });
    }
}
