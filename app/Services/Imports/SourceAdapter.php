<?php

namespace App\Services\Imports;

use App\Models\SearchProfile;

interface SourceAdapter
{
    public function fetch(SearchProfile $profile): iterable;
}
