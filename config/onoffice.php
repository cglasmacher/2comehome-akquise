<?php

return [
    'enabled' => env('ONOFFICE_ENABLED', false), 'token' => env('ONOFFICE_TOKEN'), 'secret' => env('ONOFFICE_SECRET'),
    'url' => 'https://api.onoffice.de/api/stable/api.php',
    'user_id' => (int) env('ONOFFICE_USER_ID', 39), 'username' => env('ONOFFICE_USERNAME', 'CG'),
    'address_type_field' => env('ONOFFICE_ADDRESS_TYPE_FIELD', 'ArtDaten'), 'owner_value' => env('ONOFFICE_OWNER_VALUE', 'Eigentümer'),
    'estate_status_field' => env('ONOFFICE_ESTATE_STATUS_FIELD', 'status2'), 'estate_status_value' => env('ONOFFICE_ESTATE_STATUS_VALUE', 'in_akquise'),
    'activity_kind' => env('ONOFFICE_ACTIVITY_KIND', 'Notiz'), 'activity_type' => env('ONOFFICE_ACTIVITY_TYPE', 'Akquise'),
];
