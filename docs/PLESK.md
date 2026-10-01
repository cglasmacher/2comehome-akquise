# Installation auf Plesk

## Voraussetzungen

PHP 8.3 oder 8.4 (CLI und FPM identische Version), PDO MySQL, mbstring, XML, curl, zip, Redis/PhpRedis; für Horizon außerdem CLI pcntl/posix. MariaDB/MySQL, Redis, Composer 2 und Node >=22.12 zum Build. Node kann auch nur lokal/CI genutzt werden, wenn `public/build` beim Deployment übertragen wird.

## Erstinstallation

1. Repository aus GitHub in ein Verzeichnis außerhalb des öffentlichen Document Root klonen. Subdomain `akquise.2comehome.de` mit TLS einrichten. Document Root auf den Unterordner `public` stellen. `.env`, `vendor`, `storage` und Git-Daten dürfen nicht öffentlich erreichbar sein.
2. `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build` ausführen.
3. `.env.example` nach `.env` kopieren. DB, Redis, APP_URL und onOffice-Zugang konfigurieren. `APP_ENV=production`, `APP_DEBUG=false`, sichere Sessioncookies belassen.
4. Mit derselben PHP-Version wie FPM ausführen:

```bash
/opt/plesk/php/8.3/bin/php artisan key:generate
/opt/plesk/php/8.3/bin/php artisan migrate --force
/opt/plesk/php/8.3/bin/php artisan admin:create deine-mail@example.de --name="Christian"
/opt/plesk/php/8.3/bin/php artisan optimize
```

5. Schreibrechte für den Subscription-Benutzer in `storage` und `bootstrap/cache` setzen (keine pauschalen 777-Rechte). Logs und DB sichern.

## Scheduler

Plesk „Geplante Aufgaben“, jede Minute als Subscription-Benutzer:

```cron
* * * * * cd /ABSOLUTER/PROJEKTPFAD && /opt/plesk/php/8.3/bin/php artisan schedule:run >> /dev/null 2>&1
```

Der tägliche Import läuft um 06:00 Uhr Europe/Berlin, auch bei Sommer-/Winterzeit. Datenbankzeitstempel bleiben UTC.

## Hintergrundprozess

Horizon dauerhaft über Supervisor/systemd betreiben. Beispiel Supervisor (Pfade und Benutzer ersetzen):

```ini
[program:2comehome-akquise-horizon]
command=/opt/plesk/php/8.3/bin/php /ABSOLUTER/PROJEKTPFAD/artisan horizon
directory=/ABSOLUTER/PROJEKTPFAD
user=SUBSCRIPTION_USER
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/ABSOLUTER/PROJEKTPFAD/storage/logs/horizon.log
stopwaitsecs=360
```

Horizon ist für aktive Administratoren zugänglich. Queue-Retry-Zeit 360 Sekunden, Worker-Timeout 330 Sekunden, Importjob-Timeout 120 Sekunden, onOffice-Job-Timeout 300 Sekunden. Bei mehreren Anwendungen auf demselben Redis unterschiedliche Redis-Datenbanken/Präfixe konfigurieren.

## Aktualisierung

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
/opt/plesk/php/8.3/bin/php artisan migrate --force
/opt/plesk/php/8.3/bin/php artisan optimize
/opt/plesk/php/8.3/bin/php artisan horizon:terminate
```

Keine `.env` im Repository speichern. Nach Änderungen an Zugangsdaten ebenfalls Konfigurationscache erneuern und Horizon neu starten. Ein laufender Redis-Server und erreichbare, zum Mandanten passende onOffice-Credentials sind Voraussetzung für Livebetrieb.
