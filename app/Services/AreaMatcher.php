<?php

namespace App\Services;

use App\Models\SearchProfile;

class AreaMatcher
{
    public function matches(SearchProfile $p, array $item): bool
    {
        if (! in_array($item['property_type'], $p->property_types) || ! in_array($item['market'], $p->markets)) {
            return false;
        }
        foreach (['price', 'area'] as $field) {
            foreach (['min', 'max'] as $bound) {
                $limit = $p->{$bound.'_'.$field};
                if ($limit !== null && (! isset($item[$field]) || ($bound === 'min' ? $item[$field] < $limit : $item[$field] > $limit))) {
                    return false;
                }
            }
        }
        $checks = [];
        if ($p->postal_patterns) {
            $checks[] = collect($p->postal_patterns)->contains(function ($pattern) use ($item) {
                $regex = '/^'.str_replace(['X', 'x'], '[0-9]', preg_quote($pattern, '/')).'$/';

                return preg_match($regex, $item['postal_code'] ?? '') === 1;
            });
        }
        if ($p->radius_km !== null) {
            $checks[] = isset($item['latitude'],$item['longitude']) && $this->distance((float) $p->latitude, (float) $p->longitude, (float) $item['latitude'], (float) $item['longitude']) <= (float) $p->radius_km;
        }

        return ! $checks || ($p->area_mode === 'all' ? ! in_array(false, $checks, true) : in_array(true, $checks, true));
    }

    public function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 6371 * 2 * asin(min(1,sqrt($a)));
    }
}
