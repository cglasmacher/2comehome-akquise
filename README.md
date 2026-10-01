# 2 COME HOME Akquise

Internes Akquise-CRM für private Immobilienangebote. Laravel 12, PHP >=8.3, React 19, TypeScript, Inertia 2, Tailwind 4, Vite 7. Deployment: Plesk, `akquise.2comehome.de`.

## Funktionsumfang

- Geschützte Anmeldung ohne öffentliche Registrierung; Administratoren und Bearbeiter, deaktivierbare Zugänge.
- Trefferliste mit Suche (einschließlich `40XXX`), Quelle, Anbieter, Status, Bearbeiter, Verkauf/Vermietung und fälligen Wiedervorlagen.
- Manuelle Erfassung; getrennte Immobilie, Kontakt, Inserat und Akquisevorgang. Wohnungen/Häuser aktiv, andere Arten im Datenmodell vorbereitet.
- Zuweisung, Status, Kontaktsperre, Kontaktberechtigungsnotiz, Wiedervorlagen und Aktivitäten. Bearbeiter ändern nur eigene zugewiesene Vorgänge, Administratoren alle; das Team kann die gemeinsamen Daten lesen.
- Suchprofile mit PLZ-Mustern, Ortsmittelpunkt/Koordinaten, Radius, UND/ODER-Kombination sowie Preis-/Flächengrenzen.
- Täglicher Importjob, normalisiertes JSON-Feedformat, Preisverlauf, Wiederholungsfestigkeit pro Quelle/Inserat-ID und Importprotokoll.
- onOffice-Vorschau und Hintergrundübertragung, E-Mail-/Telefon-Dublettenprüfung, Kontakt und Objekt, Eigentümerrelation sowie Aktivitäten; gespeicherte externe IDs und manueller Abgleich nach unklaren Antworten.
- Hinweise auf möglicherweise gleiche Kontakte/Objekte; keine automatische Zusammenführung unsicherer portalübergreifender Dubletten.

## Lokal starten

```bash
composer install
npm ci
cp .env.example .env
# Lokal: APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8000,
# DB_CONNECTION=sqlite, DB_DATABASE=/absoluter/pfad/database/database.sqlite,
# SESSION_SECURE_COOKIE=false, CACHE_STORE=database, QUEUE_CONNECTION=database.
touch database/database.sqlite
php artisan key:generate
php artisan migrate
php artisan admin:create deine-mail@example.de --name="Dein Name"
npm run build
php artisan serve
# Weitere Prozesse:
php artisan queue:work --tries=1 --timeout=330
php artisan schedule:work
```

Keine Standardzugänge oder produktiven Beispieldaten. Der erste Administrator wird mit verdeckter Passwortabfrage angelegt.

## Portalzugriff: bewusst noch nicht aktiv

Normale Nutzeraccounts sind in `.env.example` vorbereitet. Sie werden noch nicht für automatisierte Browserlogins verwendet. Die konkreten Portal-API-/Crawleradapter werden nach Klärung des Zugriffs ergänzt. Aktuell implementiert ist ein Adapter für einen freigegebenen, normalisierten HTTPS-JSON-Feed pro Portal; dessen Format steht in [docs/IMPORTS.md](docs/IMPORTS.md).

Die UI kennzeichnet fehlenden Zugriff als „Wartet auf Datenzugriff“. Keine Demo-Inserate werden als echte Treffer ausgegeben. Ein manueller JSON-Import ist mit `php artisan acquisition:import-json datei.json --source=manual` möglich.

## onOffice

Token/Secret und API-Benutzer in `.env` ergänzen. Administratoren prüfen Feldschlüssel und Auswahlwerte im eigenen onOffice-Mandanten, insbesondere `ArtDaten`, `status2`, „Eigentümer“, „Notiz“ und „Akquise“. Dann `ONOFFICE_ENABLED=true` und Konfigurationscache neu aufbauen.

Passende Kontakte werden nach höchster ID sortiert, in der Vorschau ausgewählt und nicht überschrieben. Ein API-seitiger E-Mail-Dublettencheck ohne Überschreiben schützt zusätzlich gegen neu entstandene Dubletten. Objekte werden über `AKQ-{lokale Objekt-ID}` abgeglichen. Bereits exportierte Aktivitäten werden übersprungen; neue Aktivitäten können durch erneute Vorschau ergänzt werden. Bestehende Objekt-/Kontaktdaten werden dabei nicht automatisch aktualisiert.

Bei Timeout, unklarer Antwort oder Prozessabbruch keine automatische Wiederholung: In onOffice prüfen und IDs mit `php artisan onoffice:reconcile VORGANG --address=ID --estate=ID --owner-linked --activity=LOKALE_ID:ONOFFICE_ID` zuordnen. Erst dann die fehlenden Schritte erneut übertragen. Kein System kann bei einem verlorenen API-Ergebnis ohne serverseitige Idempotenz die Anlage zweifelsfrei erkennen.

## Grenzen der ersten Version

- Vollständiger realer Portalabruf hängt noch von den freigegebenen Bezugswegen ab.
- Frei wählbare Orte benötigen einen konfigurierten Nominatim-kompatiblen Geocoder oder manuelle Koordinaten; Hilden/40721, Düsseldorf und Köln sind als ungefähre Ortsmittelpunkte vorhanden.
- „Nicht mehr online“ wird noch nicht automatisch aus einem fehlenden Treffer abgeleitet: Ein gefilterter/unvollständiger Feed beweist keine Löschung.
- Klassifikation ist eine nachvollziehbare Heuristik und kann manuell korrigiert werden. „Privat“ und „Makleransprache erlaubt“ sind getrennt.
- Keine automatischen Nachrichten, Anrufe oder E-Mails an Eigentümer.

## Prüfung

```bash
php artisan test
npm run build
vendor/bin/pint --test
```

Plesk-Anleitung: [docs/PLESK.md](docs/PLESK.md).
