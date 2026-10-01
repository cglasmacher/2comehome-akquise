<?php

namespace App\Services;

class Classifier
{
    public function classify(array $item): array
    {
        $text = mb_strtolower(($item['description'] ?? '').' '.($item['title'] ?? ''));
        $blocked = preg_match('/(keine|ohne|unerwünschte)\s+(makleranfragen|maklerkontakte)|makler(anfragen)?\s+(unerwünscht|nicht erwünscht)|bitte\s+keine\s+makler/u', $text) === 1;
        $type = $item['provider_type'] ?? 'unclear';
        if (! in_array($type, ['private', 'commercial', 'unclear'])) {
            $type = 'unclear';
        }
        $reason = 'Anbieterkennzeichnung der Quelle: '.$type;
        if (preg_match('/immobilienmakler|maklerbüro|courtage|maklerprovision/u', $text) && ! str_contains($text, 'keine maklerprovision')) {
            $type = 'unclear';
            $reason = 'Maklersignal im Text; manuelle Prüfung erforderlich.';
        }

        return ['provider_type' => $type, 'classification_reason' => $reason, 'contact_blocked' => $blocked, 'block_reason' => $blocked ? 'Inserat schließt Makleranfragen aus.' : null];
    }
}
