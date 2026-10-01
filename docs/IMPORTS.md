# Datenquellen und normalisierter Feed

Pro Quelle ist ein `SourceAdapter` vorgesehen. Bis der konkrete Zugriff feststeht, verarbeitet `ApprovedFeedAdapter` einen normalisierten HTTPS-Feed. Das ist keine direkte Portal-Loginintegration.

Erst nach geklärtem Zugriff setzen:

```dotenv
IMMOWELT_ENABLED=true
IMMOWELT_ACCESS_APPROVED=true
IMMOWELT_FEED_URL=https://freigegebener-anbieter.example/feed
IMMOWELT_FEED_TOKEN=
```

Feedparameter: `profile_id`, `postal_patterns` (kommagetrennt), `latitude`, `longitude`, `radius_km`. Objektarten, Angebotsarten, Preis/Fläche und PLZ/Radius werden zusätzlich lokal geprüft. Der Feed muss sämtliche Seiten bereits zusammenführen; `next_page` mit einem nichtleeren Wert führt zum Fehler statt zu einer vermeintlich vollständigen Verarbeitung. Ein Feedfehler kann einen teilweise verarbeiteten Lauf hinterlassen; die Zähler zeigen tatsächlich verarbeitete Datensätze. Erneuter Import ergänzt bzw. aktualisiert anhand `(source, external_id)`.

```json
{
    "listings": [
        {
            "external_id": "beispiel-123",
            "title": "Beispiel: Wohnung in Hilden",
            "property_type": "apartment",
            "market": "sale",
            "postal_code": "40721",
            "city": "Hilden",
            "latitude": 51.1686,
            "longitude": 6.9308,
            "location_approximate": true,
            "price": 299000,
            "area": 78,
            "rooms": 3,
            "description": "Beispielbeschreibung",
            "provider_type": "private",
            "name": "Beispielkontakt",
            "email": "beispiel@example.de",
            "phone": null,
            "url": "https://example.de/inserat/123",
            "contact_url": null
        }
    ]
}
```

Pflicht: `external_id` (String), `title`, `property_type`, `market`. Weitere Felder optional. Arten: `apartment`, `house`, `land`, `commercial`; Märkte: `sale`, `rent`; Anbieter: `private`, `commercial`, `unclear`.

Ohne verfügbare Lagekoordinaten kann ein Inserat keinen Radiusfilter bestehen. Bei UND muss es auch zum PLZ-Gebiet passen, bei ODER genügt ein passender PLZ-Filter. Unbekannte Preise/Flächen bestehen aktive Preis-/Flächenfilter nicht. PLZ-Muster bestehen aus genau fünf Ziffern/X, z.B. `40XXX`, `4072X`, `40721`.

Mietangebote und gewerbliche Anbieter starten ohne aktive Akquise. Explizite Hinweise gegen Maklerkontakte sperren den Kontakt. Einstufungen und Kontaktkorrekturen werden beim erneuten Import nicht überschrieben.
