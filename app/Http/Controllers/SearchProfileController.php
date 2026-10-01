<?php

namespace App\Http\Controllers;

use App\Jobs\ImportProfile;
use App\Models\ImportRun;
use App\Models\SearchProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SearchProfileController extends Controller
{
    private function admin(Request $r): void
    {
        abort_unless($r->user()->role === 'admin', 403);
    }

    public function index(Request $r)
    {
        return Inertia::render('Profiles', ['profiles' => SearchProfile::latest()->get(), 'runs' => ImportRun::latest()->limit(30)->get(), 'sources' => collect(config('acquisition.sources'))->map(fn ($c) => ['enabled' => $c['enabled'], 'approved' => $c['access_approved'], 'configured' => (bool) $c['feed_url']])->all()]);
    }

    public function save(Request $r, ?SearchProfile $profile = null)
    {
        $this->admin($r);
        $data = $r->validate(['name' => 'required|string|max:255', 'active' => 'required|boolean', 'postal_patterns' => 'present|array', 'postal_patterns.*' => 'required|regex:/^[0-9Xx]{5}$/', 'center' => 'nullable|string|max:255', 'latitude' => 'nullable|numeric|between:-90,90', 'longitude' => 'nullable|numeric|between:-180,180', 'radius_km' => 'nullable|numeric|min:1|max:500', 'area_mode' => 'required|in:any,all',
            'sources' => 'required|array|min:1', 'sources.*' => Rule::in(array_keys(config('acquisition.sources'))), 'property_types' => 'required|array|min:1', 'property_types.*' => 'in:apartment,house', 'markets' => 'required|array|min:1', 'markets.*' => 'in:sale,rent',
            'min_price' => 'nullable|numeric|min:0', 'max_price' => 'nullable|numeric|min:0|gte:min_price', 'min_area' => 'nullable|numeric|min:0', 'max_area' => 'nullable|numeric|min:0|gte:min_area']);
        if (empty($data['postal_patterns']) && empty($data['radius_km'])) {
            throw ValidationException::withMessages(['postal_patterns' => 'Bitte PLZ-Gebiet oder Umkreis festlegen.']);
        }
        if (! empty($data['radius_km']) && (($data['latitude'] ?? null) === null || ($data['longitude'] ?? null) === null)) {
            throw ValidationException::withMessages(['center' => 'Für den Umkreis zuerst den Mittelpunkt bestimmen.']);
        }
        if ($profile) {
            $profile->update($data);
        } else {
            SearchProfile::create($data);
        }

        return back()->with('success', 'Suchprofil gespeichert.');
    }

    public function run(SearchProfile $profile, Request $r)
    {
        $this->admin($r);
        abort_unless($profile->active, 422, 'Suchprofil ist pausiert.');
        foreach ($profile->sources as $source) {
            ImportProfile::dispatch($profile->id, $source);
        }

        return back()->with('success', 'Import wurde eingeplant.');
    }

    public function geocode(Request $r)
    {
        $this->admin($r);
        $data = $r->validate(['query' => 'required|string|max:200']);
        $known = ['hilden' => ['label' => 'Hilden', 'latitude' => 51.1686, 'longitude' => 6.9308], '40721' => ['label' => 'Hilden (PLZ-Mittelpunkt ungefähr)', 'latitude' => 51.1686, 'longitude' => 6.9308], 'düsseldorf' => ['label' => 'Düsseldorf', 'latitude' => 51.2277, 'longitude' => 6.7735], 'köln' => ['label' => 'Köln', 'latitude' => 50.9375, 'longitude' => 6.9603]];
        if (isset($known[mb_strtolower(trim($data['query']))])) {
            return response()->json([$known[mb_strtolower(trim($data['query']))]]);
        }
        $url = config('acquisition.geocoder_url');
        if (! $url) {
            return response()->json(['message' => 'Geocoder noch nicht konfiguriert. Koordinaten können manuell eingetragen werden.'], 422);
        }
        abort_unless(str_starts_with($url, 'https://'), 422);
        try {
            $results = Cache::remember('geocode:'.hash('sha256', $data['query']), 86400, fn () => Http::timeout(10)->withOptions(['allow_redirects' => false])->get($url, ['q' => $data['query'], 'countrycodes' => 'de', 'format' => 'jsonv2', 'limit' => 5])->throw()->json());

            return response()->json(collect($results)->map(fn ($v) => ['label' => $v['display_name'], 'latitude' => (float) $v['lat'], 'longitude' => (float) $v['lon']])->values());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Ortssuche derzeit nicht verfügbar.'], 503);
        }
    }
}
