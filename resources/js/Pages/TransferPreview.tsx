import { Link, useForm } from '@inertiajs/react';
import { Layout, Field, Errors } from '../components';
import { Lead } from '../types';
type Duplicate = { id: number; elements: { Name?: string; Vorname?: string; Status?: number } };
export default function TransferPreview({
    lead,
    payload,
    duplicates,
    token,
}: {
    lead: Lead;
    payload: { address: Record<string, unknown>; estate: Record<string, unknown> };
    duplicates: Duplicate[];
    token: string;
}) {
    const f = useForm({
        token,
        confirmed: false,
        existing_contact_id: duplicates[0]?.id.toString() ?? '',
    });
    const review = ['review', 'running', 'queued'].includes(lead.transfer_state);
    return (
        <Layout
            title="Übertrag in onOffice"
            subtitle="Kontakt, Immobilie und Historie vor der Übertragung prüfen."
        >
            <Link className="text-link" href={`/leads/${lead.id}`}>
                ← Zum Vorgang
            </Link>
            <form
                className="card form-card"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.transform(
                        (d) =>
                            ({
                                ...d,
                                existing_contact_id: d.existing_contact_id || null,
                            }) as unknown as typeof d,
                    );
                    f.post(`/leads/${lead.id}/onoffice`);
                }}
            >
                <Errors errors={f.errors} />
                {review && (
                    <div className="notice error">
                        Die Übertragung läuft bereits oder benötigt einen manuellen Abgleich. Eine
                        Wiederholung ist gesperrt.
                    </div>
                )}
                {duplicates.length > 0 && (
                    <div className="notice">
                        <strong>Vorhandener Kontakt aus Datenbank</strong>
                        <p>
                            Die höchste passende Kontakt-ID ist vorausgewählt. Vorhandene
                            Kontaktdaten werden nicht überschrieben.
                        </p>
                        <Field label="Kontakt auswählen">
                            <select
                                value={f.data.existing_contact_id}
                                onChange={(e) => f.setData('existing_contact_id', e.target.value)}
                            >
                                {duplicates.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        #{d.id} · {d.elements.Vorname} {d.elements.Name} ·{' '}
                                        {Number(d.elements.Status) === 1
                                            ? 'Aktiv'
                                            : 'ACHTUNG: Archiviert / Status prüfen'}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                )}
                <div className="form-grid two">
                    {Object.entries(payload).map(([section, data]) => (
                        <section key={section}>
                            <h2>{section === 'address' ? 'Kontakt' : 'Immobilie'}</h2>
                            <p className="muted">
                                {section === 'address' && lead.contact.onoffice_id
                                    ? `Bereits verbunden: #${lead.contact.onoffice_id}`
                                    : section === 'estate' && lead.property.onoffice_id
                                      ? `Bereits verbunden: #${lead.property.onoffice_id}`
                                      : 'Wird neu angelegt, sofern kein vorhandener Datensatz zugeordnet wird.'}
                            </p>
                            <dl className="preview-data">
                                {Object.entries(data).map(([k, v]) => (
                                    <div key={k}>
                                        <dt>{k}</dt>
                                        <dd>{Array.isArray(v) ? v.join(', ') : String(v)}</dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    ))}
                </div>
                <h2>Aktivitäten</h2>
                <p>
                    {lead.activities.filter((a) => !a.onoffice_id).length} noch nicht übertragene
                    Aktivitäten werden mit Kontakt und Immobilie verknüpft.
                </p>
                <label className="check">
                    <input
                        required
                        type="checkbox"
                        checked={f.data.confirmed}
                        onChange={(e) => f.setData('confirmed', e.target.checked)}
                    />
                    Daten geprüft – Kontakt, Immobilie und Historie übertragen.
                </label>
                <button disabled={f.processing || review} className="button">
                    {f.processing ? 'Übertragung läuft …' : 'Jetzt in onOffice übertragen'}
                </button>
            </form>
        </Layout>
    );
}
