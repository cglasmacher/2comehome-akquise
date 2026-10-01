<?php

return [
    'statuses' => ['new' => 'Neu', 'checked' => 'Geprüft', 'assigned' => 'Zugewiesen', 'contact_attempt' => 'Kontaktversuch', 'in_conversation' => 'Im Gespräch', 'appointment' => 'Termin', 'won' => 'Auftrag gewonnen', 'no_interest' => 'Kein Interesse', 'later' => 'Später erneut', 'blocked' => 'Nicht kontaktieren', 'unsuitable' => 'Ungeeignet'],
    'property_types' => ['apartment' => 'Wohnung', 'house' => 'Haus', 'land' => 'Grundstück (vorbereitet)', 'commercial' => 'Gewerbe (vorbereitet)'],
    'sources' => collect(['immowelt', 'immoscout24', 'kleinanzeigen'])->mapWithKeys(fn ($s) => [$s => [
        'enabled' => env(strtoupper($s).'_ENABLED', false), 'access_approved' => env(strtoupper($s).'_ACCESS_APPROVED', false),
        'username' => env(strtoupper($s).'_USERNAME'), 'password' => env(strtoupper($s).'_PASSWORD'), 'api_key' => env(strtoupper($s).'_API_KEY'),
        // Normalisierter JSON-Feed eines freigegebenen Adapters; kein beliebiger Portal-HTML-Endpunkt.
        'feed_url' => env(strtoupper($s).'_FEED_URL'), 'feed_token' => env(strtoupper($s).'_FEED_TOKEN'),
    ]])->all(),
    'geocoder_url' => env('GEOCODER_URL'),
];
