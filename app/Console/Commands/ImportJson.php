<?php

namespace App\Console\Commands;

use App\Services\Imports\ListingImporter;
use Illuminate\Console\Command;

class ImportJson extends Command
{
    protected $signature = 'acquisition:import-json {file} {--source=manual}';

    protected $description = 'Normalisierte Inserate aus einer lokalen JSON-Datei importieren';

    public function handle(ListingImporter $importer): int
    {
        if (! in_array($this->option('source'), ['manual', 'immowelt', 'immoscout24', 'kleinanzeigen'])) {
            $this->error('Unbekannte Quelle.');

            return self::FAILURE;
        }
        try {
            $payload = json_decode(file_get_contents($this->argument('file')), true, 512, JSON_THROW_ON_ERROR);
            if (! isset($payload['listings']) || ! is_array($payload['listings'])) {
                throw new \RuntimeException('listings fehlt.');
            }
            $count = 0;
            foreach ($payload['listings'] as $item) {
                $importer->ingest($this->option('source'), $item);
                $count++;
            }
            $this->info($count.' Inserate verarbeitet.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Import abgebrochen: '.class_basename($e));

            return self::FAILURE;
        }
    }
}
