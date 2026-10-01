import { Link, useForm } from '@inertiajs/react';
import { Layout, Field, Errors } from '../components';
import { Lead } from '../types';
export default function PropertyForm({ lead }: { lead: Lead }) {
    const p = lead.property;
    const f = useForm({
        title: p.title,
        postal_code: p.postal_code ?? '',
        city: p.city ?? '',
        street: p.street ?? '',
        price: p.price ?? '',
        area: p.area ?? '',
        rooms: p.rooms ?? '',
        description: p.description ?? '',
    });
    return (
        <Layout title="Objektdaten bearbeiten" subtitle={`Vorgang #${lead.id}`}>
            <Link className="text-link" href={`/leads/${lead.id}`}>
                ← Zum Vorgang
            </Link>
            <form
                className="card form-card"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.transform(
                        (d) =>
                            Object.fromEntries(
                                Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]),
                            ) as typeof d,
                    );
                    f.put(`/leads/${lead.id}/property`);
                }}
            >
                <Errors errors={f.errors} />
                <div className="form-grid">
                    {(
                        [
                            'title',
                            'postal_code',
                            'city',
                            'street',
                            'price',
                            'area',
                            'rooms',
                        ] as const
                    ).map((key) => (
                        <Field
                            key={key}
                            label={
                                {
                                    title: 'Titel',
                                    postal_code: 'PLZ',
                                    city: 'Ort',
                                    street: 'Straße',
                                    price: 'Preis (€)',
                                    area: 'Fläche (m²)',
                                    rooms: 'Zimmer',
                                }[key]
                            }
                        >
                            <input
                                required={key === 'title'}
                                type={['price', 'area', 'rooms'].includes(key) ? 'number' : 'text'}
                                step="any"
                                value={f.data[key]}
                                onChange={(e) => f.setData(key, e.target.value)}
                            />
                        </Field>
                    ))}
                </div>
                <Field label="Beschreibung">
                    <textarea
                        rows={6}
                        value={f.data.description}
                        onChange={(e) => f.setData('description', e.target.value)}
                    />
                </Field>
                <p className="muted">
                    Bei Portalangeboten können neue Importe diese Objektdaten wieder aktualisieren.
                    Kontaktkorrekturen und Akquisehistorie bleiben erhalten.
                </p>
                <button disabled={f.processing} className="button">
                    Objektdaten speichern
                </button>
            </form>
        </Layout>
    );
}
