import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, ArrowUpRight, Search, Clock3, Building2, CheckCircle2 } from 'lucide-react';
import { Layout, Status, Empty } from '../components';
import { Lead, Shared, User, money, date, provider, sources } from '../types';
type Props = {
    leads: {
        data: Lead[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Record<string, string>;
    users: User[];
    stats: Record<string, number>;
};
export default function Leads({ leads, filters, users, stats }: Props) {
    const { statuses } = usePage<Shared>().props;
    const [q, setQ] = useState(filters.q ?? '');
    const apply = (key: string, value: string) =>
        router.get(
            '/',
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true },
        );
    return (
        <Layout
            title="Akquiseübersicht"
            subtitle="Neue Angebote entdecken. Gespräche im Blick behalten."
            action={
                <Link className="button" href="/leads/create">
                    <Plus size={17} /> Vorgang erfassen
                </Link>
            }
        >
            <div className="stats">
                {[
                    ['Verkaufsangebote', stats.total, Building2],
                    ['Neue Vorgänge', stats.new, Search],
                    ['Fällige Wiedervorlagen', stats.due, Clock3],
                    ['Gewonnene Aufträge', stats.won, CheckCircle2],
                ].map(([label, n, Icon]) => {
                    const I = Icon as typeof Building2;
                    return (
                        <div className="stat" key={String(label)}>
                            <div>
                                {String(label)}
                                <I size={19} />
                            </div>
                            <strong>{String(n)}</strong>
                            <small>
                                {label === 'Fällige Wiedervorlagen'
                                    ? 'Jetzt nachfassen'
                                    : 'Dein aktueller Überblick'}
                            </small>
                        </div>
                    );
                })}
            </div>
            <section className="card">
                <div className="section-head">
                    <div>
                        <h2>Deine Angebote</h2>
                        <p>{leads.total} Vorgänge in dieser Ansicht</p>
                    </div>
                    <div className="tabs">
                        <button
                            className={filters.market !== 'rent' ? 'active' : ''}
                            onClick={() => apply('market', 'sale')}
                        >
                            Verkauf
                        </button>
                        <button
                            className={filters.market === 'rent' ? 'active' : ''}
                            onClick={() => apply('market', 'rent')}
                        >
                            Vermietung
                        </button>
                    </div>
                </div>
                {filters.market === 'rent' && (
                    <div className="notice">
                        Mietangebote werden erfasst und starten ohne aktive Akquise.
                    </div>
                )}
                <div className="filters">
                    <form
                        className="search-input"
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply('q', q);
                        }}
                    >
                        <Search size={17} />
                        <input
                            aria-label="Ort, PLZ oder Titel suchen"
                            placeholder="Ort, PLZ / 40XXX oder Titel …"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <button type="submit">Suchen</button>
                    </form>
                    <select
                        aria-label="Status"
                        value={filters.status ?? ''}
                        onChange={(e) => apply('status', e.target.value)}
                    >
                        <option value="">Alle Status</option>
                        {Object.entries(statuses).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Bearbeiter"
                        value={filters.assigned_to ?? ''}
                        onChange={(e) => apply('assigned_to', e.target.value)}
                    >
                        <option value="">Alle Bearbeiter</option>
                        {users.map((u) => (
                            <option value={u.id} key={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Anbietertyp"
                        value={filters.provider_type ?? ''}
                        onChange={(e) => apply('provider_type', e.target.value)}
                    >
                        <option value="">Alle Anbieter</option>
                        {Object.entries(provider).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Quelle"
                        value={filters.source ?? ''}
                        onChange={(e) => apply('source', e.target.value)}
                    >
                        <option value="">Alle Quellen</option>
                        {Object.entries(sources).map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                    </select>
                    <button
                        className="button secondary"
                        onClick={() => {
                            setQ('');
                            router.get('/');
                        }}
                    >
                        Zurücksetzen
                    </button>
                </div>
                {leads.data.length ? (
                    <>
                        <div className="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Immobilie</th>
                                        <th>Angebot</th>
                                        <th>Anbieter / Quelle</th>
                                        <th>Status</th>
                                        <th>Bearbeiter / Wiedervorlage</th>
                                        <th />
                                    </tr>
                                </thead>
                                <tbody>
                                    {leads.data.map((l) => (
                                        <tr key={l.id}>
                                            <td>
                                                <Link
                                                    href={`/leads/${l.id}`}
                                                    className="property-title"
                                                >
                                                    {l.property.title}
                                                </Link>
                                                <small>
                                                    {l.property.postal_code} {l.property.city} ·{' '}
                                                    {l.property.property_type === 'house'
                                                        ? 'Haus'
                                                        : 'Wohnung'}
                                                </small>
                                            </td>
                                            <td>
                                                <strong>{money(l.property.price)}</strong>
                                                <small>
                                                    {l.property.area
                                                        ? `${Number(l.property.area)} m²`
                                                        : 'Fläche offen'}
                                                    {l.property.rooms
                                                        ? ` · ${Number(l.property.rooms)} Zi.`
                                                        : ''}
                                                </small>
                                            </td>
                                            <td>
                                                <Status
                                                    value={l.contact.provider_type}
                                                    label={provider[l.contact.provider_type]}
                                                />
                                                <small>
                                                    {[
                                                        ...new Set(
                                                            l.property.listings.map(
                                                                (s) => sources[s.source],
                                                            ),
                                                        ),
                                                    ].join(', ')}
                                                </small>
                                            </td>
                                            <td>
                                                <Status
                                                    value={l.status}
                                                    label={statuses[l.status]}
                                                />
                                                {!l.acquisition_active && (
                                                    <small>Akquise inaktiv</small>
                                                )}
                                            </td>
                                            <td>
                                                {l.assignee?.name ?? (
                                                    <span className="muted">Nicht zugewiesen</span>
                                                )}
                                                <small
                                                    className={
                                                        l.follow_up_at &&
                                                        new Date(l.follow_up_at) < new Date()
                                                            ? 'due'
                                                            : ''
                                                    }
                                                >
                                                    {date(l.follow_up_at)}
                                                </small>
                                            </td>
                                            <td>
                                                <Link
                                                    aria-label="Vorgang öffnen"
                                                    href={`/leads/${l.id}`}
                                                >
                                                    <ArrowUpRight size={18} />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="pagination">
                            {leads.links.map((l, i) => (
                                <button
                                    key={i}
                                    disabled={!l.url}
                                    className={l.active ? 'active' : ''}
                                    onClick={() => l.url && router.get(l.url)}
                                >
                                    {l.label.includes('Previous')
                                        ? '←'
                                        : l.label.includes('Next')
                                          ? '→'
                                          : l.label}
                                </button>
                            ))}
                        </div>
                    </>
                ) : (
                    <Empty
                        title="Hier beginnt eure nächste Akquise"
                        text="Erfasse einen Vorgang oder richte Suchprofile ein. Neue Importe erscheinen in dieser Übersicht."
                    />
                )}
            </section>
        </Layout>
    );
}
