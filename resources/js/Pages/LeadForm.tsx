import { useForm } from '@inertiajs/react';
import { Layout, Field, Errors, Back } from '../components';
export default function LeadForm() {
    const f = useForm({
        title: '',
        property_type: 'apartment',
        market: 'sale',
        postal_code: '',
        city: '',
        street: '',
        price: '',
        area: '',
        rooms: '',
        description: '',
        name: '',
        email: '',
        phone: '',
        provider_type: 'unclear',
        url: '',
        contact_url: '',
    });
    const input = (key: keyof typeof f.data, type = 'text') => (
        <input
            type={type}
            step={type === 'number' ? 'any' : undefined}
            value={f.data[key]}
            onChange={(e) => f.setData(key, e.target.value)}
        />
    );
    return (
        <Layout
            title="Vorgang erfassen"
            subtitle="Ein Angebot manuell aufnehmen und anschließend bearbeiten."
        >
            <Back />
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
                    f.post('/leads');
                }}
            >
                <Errors errors={f.errors} />
                <h2>Immobilie</h2>
                <div className="form-grid">
                    <Field label="Titel *">{input('title')}</Field>
                    <Field label="Objektart">
                        <select
                            value={f.data.property_type}
                            onChange={(e) => f.setData('property_type', e.target.value)}
                        >
                            <option value="apartment">Wohnung</option>
                            <option value="house">Haus</option>
                        </select>
                    </Field>
                    <Field label="Angebot">
                        <select
                            value={f.data.market}
                            onChange={(e) => f.setData('market', e.target.value)}
                        >
                            <option value="sale">Verkauf</option>
                            <option value="rent">Vermietung</option>
                        </select>
                    </Field>
                    <Field label="PLZ">{input('postal_code')}</Field>
                    <Field label="Ort">{input('city')}</Field>
                    <Field label="Straße (soweit bekannt)">{input('street')}</Field>
                    <Field label="Preis in €">{input('price', 'number')}</Field>
                    <Field label="Wohnfläche in m²">{input('area', 'number')}</Field>
                    <Field label="Zimmer">{input('rooms', 'number')}</Field>
                </div>
                <Field label="Beschreibung">
                    <textarea
                        rows={4}
                        value={f.data.description}
                        onChange={(e) => f.setData('description', e.target.value)}
                    />
                </Field>
                <h2>Anbieter & Kontaktwege</h2>
                <div className="form-grid">
                    <Field label="Name">{input('name')}</Field>
                    <Field label="E-Mail">{input('email', 'email')}</Field>
                    <Field label="Telefon">{input('phone')}</Field>
                    <Field label="Anbietertyp">
                        <select
                            value={f.data.provider_type}
                            onChange={(e) => f.setData('provider_type', e.target.value)}
                        >
                            <option value="unclear">Prüfung offen</option>
                            <option value="private">Privat</option>
                            <option value="commercial">Gewerblich / Makler</option>
                        </select>
                    </Field>
                    <Field label="Inseratlink">{input('url', 'url')}</Field>
                    <Field label="Portal-Kontaktlink">{input('contact_url', 'url')}</Field>
                </div>
                <button className="button" disabled={f.processing}>
                    Vorgang erfassen
                </button>
            </form>
        </Layout>
    );
}
