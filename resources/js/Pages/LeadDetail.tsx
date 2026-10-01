import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Layout, Field, Errors, Status, Back, External } from '../components';
import { Lead, Shared, User, date, money, provider, sources, localInput } from '../types';
export default function LeadDetail({
    lead: l,
    users,
    canEdit,
    duplicates,
    onofficeEnabled,
}: {
    lead: Lead;
    users: User[];
    canEdit: boolean;
    duplicates: Lead[];
    onofficeEnabled: boolean;
}) {
    const { statuses, auth } = usePage<Shared>().props;
    const f = useForm({
        status: l.status,
        assigned_to: l.assigned_to?.toString() ?? '',
        follow_up_at: l.follow_up_at ? localInput(l.follow_up_at) : '',
        acquisition_active: l.acquisition_active,
        contact_permission: l.contact_permission ?? '',
        name: l.contact.name ?? '',
        email: l.contact.email ?? '',
        phone: l.contact.phone ?? '',
        provider_type: l.contact.provider_type,
        contact_blocked: l.contact.contact_blocked,
        block_reason: l.contact.block_reason ?? '',
    });
    const activity = useForm({ type: 'note', note: '', occurred_at: localInput() });
    return (
        <Layout
            title={l.property.title}
            subtitle={`Vorgang #${l.id} · ${l.property.postal_code ?? ''} ${l.property.city ?? ''}`}
            action={
                canEdit && onofficeEnabled ? (
                    <Link className="button" href={`/leads/${l.id}/onoffice`}>
                        Übertrag in onOffice →
                    </Link>
                ) : undefined
            }
        >
            <Back />
            {l.contact.contact_blocked && (
                <div className="notice error">Kontakt gesperrt: {l.contact.block_reason}</div>
            )}
            {duplicates.length > 0 && (
                <div className="notice">
                    Mögliche verwandte Vorgänge:{' '}
                    {duplicates.map((d) => (
                        <Link key={d.id} href={`/leads/${d.id}`}>
                            #{d.id} {d.property.title} ·{' '}
                        </Link>
                    ))}
                </div>
            )}
            <div className="detail-grid">
                <div>
                    <section className="card form-card">
                        <div className="section-head">
                            <h2>Objekt & Quellen</h2>
                            {canEdit && (
                                <Link className="text-link" href={`/leads/${l.id}/property`}>
                                    Objektdaten bearbeiten
                                </Link>
                            )}
                            <Status value={l.status} label={statuses[l.status]} />
                        </div>
                        <div className="object-facts">
                            <strong>{money(l.property.price)}</strong>
                            <span>
                                {l.property.area ? `${Number(l.property.area)} m²` : 'Fläche offen'}{' '}
                                ·{' '}
                                {l.property.rooms
                                    ? `${Number(l.property.rooms)} Zimmer`
                                    : 'Zimmer offen'}{' '}
                                · {l.property.market === 'sale' ? 'Verkauf' : 'Vermietung'}
                            </span>
                        </div>
                        <p className="preline">
                            {l.property.description || 'Keine Beschreibung hinterlegt.'}
                        </p>
                        {l.property.street && (
                            <p>
                                {l.property.street}, {l.property.postal_code} {l.property.city}
                            </p>
                        )}
                        <small className="muted">
                            {l.property.location_approximate
                                ? 'Lage nur ungefähr bekannt'
                                : 'Genaue Lage hinterlegt'}
                        </small>
                        {l.property.listings.map((s) => (
                            <div key={s.id} className="source-row">
                                <div>
                                    <strong>{sources[s.source]}</strong>
                                    <small>
                                        Erstfund {date(s.first_seen_at)} · zuletzt{' '}
                                        {date(s.last_seen_at)}
                                    </small>
                                    <small>
                                        {s.availability === 'online'
                                            ? 'Online'
                                            : 'Nicht mehr online'}
                                    </small>
                                </div>
                                <div>
                                    <External href={s.url}>Inserat öffnen</External>
                                    <External href={s.contact_url}>Kontaktweg</External>
                                </div>
                                {s.price_history?.length > 1 && (
                                    <details>
                                        <summary>Preisverlauf ({s.price_history.length})</summary>
                                        {s.price_history.map((p, i) => (
                                            <small key={i}>
                                                {date(p.at)} · {money(p.price)}
                                            </small>
                                        ))}
                                    </details>
                                )}
                            </div>
                        ))}
                    </section>
                    <section className="card form-card">
                        <h2>Akquisehistorie</h2>
                        {canEdit && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    activity.transform((d) => ({
                                        ...d,
                                        occurred_at: new Date(d.occurred_at).toISOString(),
                                    }));
                                    activity.post(`/leads/${l.id}/activities`, {
                                        onSuccess: () => activity.reset('note'),
                                    });
                                }}
                            >
                                <Errors errors={activity.errors} />
                                <div className="form-grid two">
                                    <Field label="Aktivität">
                                        <select
                                            value={activity.data.type}
                                            onChange={(e) =>
                                                activity.setData('type', e.target.value)
                                            }
                                        >
                                            {Object.entries({
                                                note: 'Notiz',
                                                phone: 'Telefon',
                                                email: 'E-Mail',
                                                portal: 'Portalnachricht',
                                                appointment: 'Termin',
                                            }).map(([k, v]) => (
                                                <option key={k} value={k}>
                                                    {v}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field label="Zeitpunkt">
                                        <input
                                            type="datetime-local"
                                            required
                                            value={activity.data.occurred_at}
                                            onChange={(e) =>
                                                activity.setData('occurred_at', e.target.value)
                                            }
                                        />
                                    </Field>
                                </div>
                                <Field label="Notiz / Ergebnis">
                                    <textarea
                                        rows={3}
                                        required
                                        value={activity.data.note}
                                        onChange={(e) => activity.setData('note', e.target.value)}
                                    />
                                </Field>
                                <button disabled={activity.processing} className="button secondary">
                                    Aktivität speichern
                                </button>
                            </form>
                        )}
                        <div className="timeline">
                            {l.activities.map((a) => (
                                <article key={a.id}>
                                    <div className="timeline-dot" />
                                    <div>
                                        <small>
                                            {date(a.occurred_at)} · {a.user?.name ?? 'System'} ·{' '}
                                            {a.type}
                                            {a.onoffice_id ? ' · in onOffice' : ''}
                                        </small>
                                        <p className="preline">{a.note}</p>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                </div>
                <div>
                    <form
                        className="card form-card"
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.transform(
                                (d) =>
                                    ({
                                        ...d,
                                        assigned_to: d.assigned_to || null,
                                        follow_up_at: d.follow_up_at
                                            ? new Date(d.follow_up_at).toISOString()
                                            : null,
                                    }) as unknown as typeof d,
                            );
                            f.put(`/leads/${l.id}`);
                        }}
                    >
                        <h2>Vorgang bearbeiten</h2>
                        <Errors errors={f.errors} />
                        <fieldset disabled={!canEdit}>
                            <Field label="Status">
                                <select
                                    value={f.data.status}
                                    onChange={(e) => f.setData('status', e.target.value)}
                                >
                                    {Object.entries(statuses).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Bearbeiter">
                                <select
                                    disabled={auth.user.role !== 'admin'}
                                    value={f.data.assigned_to}
                                    onChange={(e) => f.setData('assigned_to', e.target.value)}
                                >
                                    <option value="">Nicht zugewiesen</option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Wiedervorlage">
                                <input
                                    type="datetime-local"
                                    value={f.data.follow_up_at}
                                    onChange={(e) => f.setData('follow_up_at', e.target.value)}
                                />
                            </Field>
                            <label className="check">
                                <input
                                    type="checkbox"
                                    checked={f.data.acquisition_active}
                                    onChange={(e) =>
                                        f.setData('acquisition_active', e.target.checked)
                                    }
                                />
                                Aktive Akquise
                            </label>
                            <h2>Anbieter</h2>
                            <Field label="Name">
                                <input
                                    value={f.data.name}
                                    onChange={(e) => f.setData('name', e.target.value)}
                                />
                            </Field>
                            <Field label="E-Mail">
                                <input
                                    type="email"
                                    value={f.data.email}
                                    onChange={(e) => f.setData('email', e.target.value)}
                                />
                            </Field>
                            <Field label="Telefon">
                                <input
                                    value={f.data.phone}
                                    onChange={(e) => f.setData('phone', e.target.value)}
                                />
                            </Field>
                            <Field label="Anbietertyp">
                                <select
                                    value={f.data.provider_type}
                                    onChange={(e) => f.setData('provider_type', e.target.value)}
                                >
                                    {Object.entries(provider).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <small className="muted">{l.contact.classification_reason}</small>
                            <Field label="Kontaktberechtigung / Nachweis">
                                <textarea
                                    rows={2}
                                    value={f.data.contact_permission}
                                    onChange={(e) =>
                                        f.setData('contact_permission', e.target.value)
                                    }
                                />
                            </Field>
                            <label className="check">
                                <input
                                    type="checkbox"
                                    checked={f.data.contact_blocked}
                                    onChange={(e) => f.setData('contact_blocked', e.target.checked)}
                                />
                                Nicht kontaktieren
                            </label>
                            {f.data.contact_blocked && (
                                <Field label="Grund der Sperre">
                                    <textarea
                                        value={f.data.block_reason}
                                        onChange={(e) => f.setData('block_reason', e.target.value)}
                                    />
                                </Field>
                            )}
                            {canEdit && (
                                <button disabled={f.processing} className="button wide">
                                    Änderungen speichern
                                </button>
                            )}
                        </fieldset>
                        {!canEdit && (
                            <p className="muted">
                                Bearbeitung durch den zugewiesenen Bearbeiter oder Administrator.
                            </p>
                        )}
                    </form>
                    <section className="card form-card">
                        <h2>onOffice</h2>
                        <p>
                            {onofficeEnabled
                                ? 'Manuelle Übertragung mit Vorschau'
                                : 'Zugang noch nicht aktiviert'}
                        </p>
                        <small>
                            Kontakt-ID: {l.contact.onoffice_id ?? '—'}
                            <br />
                            Objekt-ID: {l.property.onoffice_id ?? '—'}
                            <br />
                            Übertragung:{' '}
                            {
                                (
                                    {
                                        pending: 'Ausstehend',
                                        queued: 'Eingeplant',
                                        running: 'In Bearbeitung',
                                        completed: 'Abgeschlossen',
                                        failed: 'Fehler – ergänzbar',
                                        review: 'Manueller Abgleich erforderlich',
                                    } as Record<string, string>
                                )[l.transfer_state]
                            }
                        </small>
                        <button
                            type="button"
                            className="button secondary"
                            onClick={() => router.reload({ only: ['lead'] })}
                        >
                            Status aktualisieren
                        </button>
                        {l.transfer_error && <div className="notice error">{l.transfer_error}</div>}
                    </section>
                </div>
            </div>
        </Layout>
    );
}
