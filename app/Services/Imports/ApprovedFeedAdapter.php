<?php

namespace App\Services\Imports;

use App\Models\SearchProfile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApprovedFeedAdapter implements SourceAdapter
{
    public function __construct(private string $source) {}

    public function fetch(SearchProfile $profile): iterable
    {
        $config = config('acquisition.sources.'.$this->source);
        if (! $config['enabled'] || ! $config['access_approved'] || ! $config['feed_url']) {
            throw new RuntimeException('Portaladapter wartet auf freigegebenen Datenzugriff und Feed-Konfiguration.');
        }
        if (! str_starts_with($config['feed_url'], 'https://')) {
            throw new RuntimeException('Feed muss HTTPS verwenden.');
        }
        $request = Http::timeout(45)->withOptions(['allow_redirects' => false])->acceptJson();
        if ($config['feed_token']) {
            $request = $request->withToken($config['feed_token']);
        }
        $response = $request->get($config['feed_url'], ['profile_id' => $profile->id, 'postal_patterns' => implode(',', $profile->postal_patterns ?? []), 'latitude' => $profile->latitude, 'longitude' => $profile->longitude, 'radius_km' => $profile->radius_km]);
        $response->throw();
        $data = $response->json();
        if (! is_array($data) || ! isset($data['listings']) || ! is_array($data['listings'])) {
            throw new RuntimeException('Ungültiger Feed: listings muss ein Array sein.');
        }
        if (isset($data['next_page']) && $data['next_page']) {
            throw new RuntimeException('Unvollständiger Feed: Der Adapter muss alle Seiten zusammenführen.');
        }

        return $data['listings'];
    }
}
