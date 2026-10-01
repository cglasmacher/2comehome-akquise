import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, MapPin, Play } from 'lucide-react';
import { Layout, Field, Errors, Status, Empty } from '../components';
import { Profile, Shared, date, sources } from '../types';
type Run = {
    id: number;
    source: string;
    status: string;
    created_count: number;
    updated_count: number;
    skipped_count: number;
    message: string;
    started_at: string;
};
type Geo = { label: string; latitude: number; longitude: number };
export default function Profiles({
    profiles,
    runs,
    sources: connections,
}: {
    profiles: Profile[];
    runs: Run[];
    sources: Record<string, { enabled: boolean; approved: boolean; configured: boolean }>;
}) {
    const { auth } = usePage<Shared>().props;
    const admin = auth.user.role === 'admin';
    const [editing, setEditing] = useState<number | null>(null);
    const [open, setOpen] = useState(false);
    const [geo, setGeo] = useState<Geo[]>([]);
    const [geoError, setGeoError] = useState('');
    const [loading, setLoading] = useState(false);
    const blank = {
        name: '',
        active: true,
        postal_patterns: [] as string[],
        center: '',
        latitude: '',
        longitude: '',
        radius_km: '',
        area_mode: 'any',
        sources: ['immowelt', 'immoscout24', 'kleinanzeigen'],
        property_types: ['apartment', 'house'],
        markets: ['sale', 'rent'],
        min_price: '',
        max_price: '',
        min_area: '',
        max_area: '',
    };
    const f = useForm(blank);
    const [postal, setPostal] = useState('');
    const edit = (p?: Profile) => {
        setEditing(p?.id ?? null);
        f.setData(
            p
                ? {
                      ...blank,
                      ...p,
                      center: p.center ?? '',
                      latitude: p.latitude ?? '',
                      longitude: p.longitude ?? '',
                      radius_km: p.radius_km ?? '',
                      min_price: p.min_price ?? '',
                      max_price: p.max_price ?? '',
                      min_area: p.min_area ?? '',
                      max_area: p.max_area ?? '',
                  }
                : blank,
        );
        setPostal(p?.postal_patterns?.join(', ') ?? '');
        f.clearErrors();
        setGeo([]);
        setGeoError('');
        setOpen(true);
    };
    const toggle = (key: 'sources' | 'property_types' | 'markets', value: string) =>
        f.setData(
            key,
            f.data[key].includes(value)
                ? f.data[key].filter((v) => v !== value)
                : [...f.data[key], value],
        );
    const locate = async () => {
        setLoading(true);
        setGeoError('');
        setGeo([]);
        try {
            const res = await fetch('/geocode', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.slice('XSRF-TOKEN='.length) ?? '',
                    ),
                },
                body: JSON.stringify({ query: f.data.center }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message ?? 'Ortssuche fehlgeschlagen.');
            setGeo(data);
            if (!data.length) setGeoError('Kein Ort gefunden.');
        } catch (e) {
            setGeoError(e instanceof Error ? e.message : 'Ortssuche fehlgeschlagen.');
        } finally {
            setLoading(false);
        }
    };
    return (
        <Layout
            title="Suchprofile & Quellen"
            subtitle="Definiere, wo und wonach eure Akquise sucht."
            action={
                admin ? (
                    <button className="button" onClick={() => edit()}>
                        <Plus size={17} /> Suchprofil anlegen
                    </button>
                ) : undefined
            }
        >
            <div className="source-cards">
                {Object.entries(connections).map(([key, c]) => (
                    <div className="card source-card" key={key}>
                        <strong>{sources[key]}</strong>
                        <Status
                            value={c.enabled && c.approved && c.configured ? 'private' : 'unclear'}
                            label={
                                c.enabled && c.approved && c.configured
                                    ? 'Feed aktiviert'
                                    : 'Wartet auf Datenzugriff'
                            }
                        />
                    </div>
                ))}
            </div>
            {open && (
                <form
                    className="card form-card"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.transform(
                            (d) =>
                                ({
                                    ...d,
                                    postal_patterns: postal
                                        .split(/[\s,;]+/)
                                        .filter(Boolean)
                                        .map((s) => s.toUpperCase()),
                                    latitude: d.latitude || null,
                                    longitude: d.longitude || null,
                                    radius_km: d.radius_km || null,
                                    min_price: d.min_price || null,
                                    max_price: d.max_price || null,
                                    min_area: d.min_area || null,
                                    max_area: d.max_area || null,
                                }) as unknown as typeof d,
                        );
                        const opts = { onSuccess: () => setOpen(false) };
                        editing ? f.put(`/profiles/${editing}`, opts) : f.post('/profiles', opts);
                    }}
                >
                    <h2>{editing ? 'Suchprofil bearbeiten' : 'Neues Suchprofil'}</h2>
                    <Errors errors={f.errors} />
                    <div className="form-grid two">
                        <Field label="Bezeichnung">
                            <input
                                required
                                value={f.data.name}
                                onChange={(e) => f.setData('name', e.target.value)}
                            />
                        </Field>
                        <Field label="PLZ / Muster, durch Komma getrennt">
                            <input
                                placeholder="40XXX, 42XXX, 50667"
                                value={postal}
                                onChange={(e) => setPostal(e.target.value)}
                            />
                        </Field>
                        <Field label="Mittelpunkt: Ort oder PLZ">
                            <div className="inline">
                                <input
                                    value={f.data.center}
                                    onChange={(e) => {
                                        f.setData({
                                            ...f.data,
                                            center: e.target.value,
                                            latitude: '',
                                            longitude: '',
                                        });
                                    }}
                                />
                                <button
                                    type="button"
                                    className="button secondary"
                                    disabled={loading || !f.data.center}
                                    onClick={locate}
                                >
                                    Ort suchen
                                </button>
                            </div>
                        </Field>
                        <Field label="Radius in km">
                            <input
                                type="number"
                                min={1}
                                max={500}
                                value={f.data.radius_km}
                                onChange={(e) => f.setData('radius_km', e.target.value)}
                            />
                        </Field>
                    </div>
                    {geoError && <div className="notice">{geoError}</div>}
                    {geo.map((g, i) => (
                        <button
                            type="button"
                            className="geo-result"
                            key={i}
                            onClick={() => {
                                f.setData({
                                    ...f.data,
                                    center: g.label,
                                    latitude: String(g.latitude),
                                    longitude: String(g.longitude),
                                });
                                setGeo([]);
                            }}
                        >
                            <MapPin size={16} />
                            {g.label}
                        </button>
                    ))}
                    <div className="form-grid">
                        <Field label="Breitengrad">
                            <input
                                type="number"
                                step="any"
                                value={f.data.latitude}
                                onChange={(e) => f.setData('latitude', e.target.value)}
                            />
                        </Field>
                        <Field label="Längengrad">
                            <input
                                type="number"
                                step="any"
                                value={f.data.longitude}
                                onChange={(e) => f.setData('longitude', e.target.value)}
                            />
                        </Field>
                        <Field label="PLZ und Umkreis kombinieren">
                            <select
                                value={f.data.area_mode}
                                onChange={(e) => f.setData('area_mode', e.target.value)}
                            >
                                <option value="any">Mindestens eines trifft zu (ODER)</option>
                                <option value="all">Beides trifft zu (UND)</option>
                            </select>
                        </Field>
                    </div>
                    <div className="form-grid">
                        {(['sources', 'property_types', 'markets'] as const).map((key) => (
                            <div key={key}>
                                <h3>
                                    {key === 'sources'
                                        ? 'Quellen'
                                        : key === 'property_types'
                                          ? 'Objektarten'
                                          : 'Angebotsarten'}
                                </h3>
                                {Object.entries(
                                    key === 'sources'
                                        ? {
                                              immowelt: 'Immowelt',
                                              immoscout24: 'ImmoScout24',
                                              kleinanzeigen: 'Kleinanzeigen',
                                          }
                                        : key === 'property_types'
                                          ? { apartment: 'Wohnungen', house: 'Häuser' }
                                          : {
                                                sale: 'Verkauf – aktive Akquise',
                                                rent: 'Vermietung – zunächst inaktiv',
                                            },
                                ).map(([k, v]) => (
                                    <label className="check" key={k}>
                                        <input
                                            type="checkbox"
                                            checked={f.data[key].includes(k)}
                                            onChange={() => toggle(key, k)}
                                        />
                                        {v}
                                    </label>
                                ))}
                            </div>
                        ))}
                    </div>
                    <div className="form-grid">
                        {(['min_price', 'max_price', 'min_area', 'max_area'] as const).map((k) => (
                            <Field
                                key={k}
                                label={
                                    {
                                        min_price: 'Preis ab (€)',
                                        max_price: 'Preis bis (€)',
                                        min_area: 'Fläche ab (m²)',
                                        max_area: 'Fläche bis (m²)',
                                    }[k]
                                }
                            >
                                <input
                                    type="number"
                                    min={0}
                                    value={f.data[k]}
                                    onChange={(e) => f.setData(k, e.target.value)}
                                />
                            </Field>
                        ))}
                    </div>
                    <label className="check">
                        <input
                            type="checkbox"
                            checked={f.data.active}
                            onChange={(e) => f.setData('active', e.target.checked)}
                        />
                        Täglich durchsuchen
                    </label>
                    <div className="inline">
                        <button disabled={f.processing} className="button">
                            Suchprofil speichern
                        </button>
                        <button
                            type="button"
                            className="button secondary"
                            onClick={() => setOpen(false)}
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>
            )}
            <section className="card">
                <div className="section-head">
                    <h2>Suchgebiete</h2>
                    <span className="muted">Täglicher Lauf um 06:00 Uhr</span>
                </div>
                {profiles.length ? (
                    profiles.map((p) => (
                        <div className="profile-row" key={p.id}>
                            <div className="profile-icon">
                                <MapPin size={22} />
                            </div>
                            <div className="grow">
                                <strong>{p.name}</strong>
                                <small>
                                    {p.postal_patterns?.join(', ')}
                                    {p.radius_km
                                        ? ` · ${p.center} + ${Number(p.radius_km)} km`
                                        : ''}
                                </small>
                                <small>{p.sources.map((s) => sources[s]).join(' · ')}</small>
                            </div>
                            <Status
                                value={p.active ? 'private' : ''}
                                label={p.active ? 'Aktiv' : 'Pausiert'}
                            />
                            {admin && (
                                <>
                                    <button className="button secondary" onClick={() => edit(p)}>
                                        Bearbeiten
                                    </button>
                                    <button
                                        aria-label="Import starten"
                                        className="icon-button dark"
                                        disabled={!p.active}
                                        onClick={() => router.post(`/profiles/${p.id}/run`)}
                                    >
                                        <Play size={17} />
                                    </button>
                                </>
                            )}
                        </div>
                    ))
                ) : (
                    <Empty
                        title="Dein Gebiet. Deine Chancen."
                        text="Lege ein Suchprofil mit PLZ-Mustern oder einem Umkreis an."
                    />
                )}
            </section>
            <section className="card">
                <div className="section-head">
                    <h2>Importverlauf</h2>
                    <button
                        className="button secondary"
                        onClick={() => router.reload({ only: ['runs'] })}
                    >
                        Aktualisieren
                    </button>
                </div>
                <div className="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Zeitpunkt</th>
                                <th>Quelle</th>
                                <th>Status</th>
                                <th>Neu / aktualisiert / gefiltert</th>
                                <th>Ergebnis</th>
                            </tr>
                        </thead>
                        <tbody>
                            {runs.map((r) => (
                                <tr key={r.id}>
                                    <td>{date(r.started_at)}</td>
                                    <td>{sources[r.source]}</td>
                                    <td>
                                        {
                                            (
                                                {
                                                    waiting: 'Wartet auf Zugriff',
                                                    running: 'Läuft',
                                                    completed: 'Abgeschlossen',
                                                    failed: 'Fehlgeschlagen',
                                                } as Record<string, string>
                                            )[r.status]
                                        }
                                    </td>
                                    <td>
                                        {r.created_count} / {r.updated_count} / {r.skipped_count}
                                    </td>
                                    <td>{r.message}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!runs.length && <p className="padded muted">Noch keine Importläufe vorhanden.</p>}
            </section>
        </Layout>
    );
}
