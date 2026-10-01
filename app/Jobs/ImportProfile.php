<?php

namespace App\Jobs;

use App\Models\ImportRun;
use App\Models\SearchProfile;
use App\Services\AreaMatcher;
use App\Services\Imports\ApprovedFeedAdapter;
use App\Services\Imports\ListingImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ImportProfile implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $profileId, public string $source) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('import:'.$this->profileId.':'.$this->source))->expireAfter(180)];
    }

    public function handle(ListingImporter $importer, AreaMatcher $matcher): void
    {
        $profile = SearchProfile::find($this->profileId);
        if (! $profile || ! $profile->active) {
            return;
        }
        $run = ImportRun::create(['search_profile_id' => $profile->id, 'source' => $this->source, 'status' => 'running', 'started_at' => now()]);
        try {
            foreach ((new ApprovedFeedAdapter($this->source))->fetch($profile) as $item) {
                if (! is_array($item) || ! isset($item['market'],$item['property_type'])) {
                    throw new \RuntimeException('Ungültiger Feed-Datensatz.');
                }
                if (! $matcher->matches($profile, $item)) {
                    $run->increment('skipped_count');

                    continue;
                }
                $result = $importer->ingest($this->source, $item);
                $run->increment($result['created'] ? 'created_count' : 'updated_count');
            }
            $run->update(['status' => 'completed', 'finished_at' => now(), 'message' => 'Import abgeschlossen.']);
        } catch (\Throwable $e) {
            // HTTP-Antworten/Zugangsdaten nicht in UI oder Logs schreiben.
            $waiting = ! config('acquisition.sources.'.$this->source.'.enabled') || ! config('acquisition.sources.'.$this->source.'.access_approved') || ! config('acquisition.sources.'.$this->source.'.feed_url');
            $run->update(['status' => $waiting ? 'waiting' : 'failed', 'finished_at' => now(), 'message' => $waiting ? 'Wartet auf freigegebenen Datenzugriff.' : 'Import fehlgeschlagen ('.class_basename($e).'). Konfiguration und Feed prüfen.']);
        }
    }
}
