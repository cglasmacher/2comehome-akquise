<?php

namespace App\Services\OnOffice;

class ApiException extends \RuntimeException
{
    public function __construct(public bool $uncertain = false)
    {
        parent::__construct($uncertain ? 'Antwort unklar. Vor erneutem Übertragen in onOffice prüfen.' : 'onOffice hat den API-Aufruf abgelehnt. Feldkonfiguration und Berechtigungen prüfen.');
    }
}
