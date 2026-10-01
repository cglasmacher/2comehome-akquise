import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import {
    Building2,
    Search,
    Users,
    LogOut,
    ArrowUpRight,
    Clock3,
    LayoutDashboard,
} from 'lucide-react';
import { Shared } from './types';
export function Layout({
    title,
    subtitle,
    children,
    action,
}: {
    title: string;
    subtitle?: string;
    children: ReactNode;
    action?: ReactNode;
}) {
    const { auth, flash } = usePage<Shared>().props;
    const url = usePage().url;
    return (
        <div className="app">
            <Head title={title} />
            <aside className="sidebar">
                <Link href="/" className="brand">
                    2 COME HOME<span>IMMOBILIEN · AKQUISE</span>
                </Link>
                <div className="workspace">
                    <span className="live-dot" /> Dein Akquisebereich
                </div>
                <nav>
                    <Link
                        className={
                            url === '/' || url.startsWith('/?') || url.startsWith('/leads')
                                ? 'selected'
                                : ''
                        }
                        href="/"
                    >
                        <LayoutDashboard size={19} /> Akquiseübersicht
                    </Link>
                    <Link href="/?due=1">
                        <Clock3 size={19} /> Wiedervorlagen
                    </Link>
                    <Link
                        className={url.startsWith('/profiles') ? 'selected' : ''}
                        href="/profiles"
                    >
                        <Search size={19} /> Suchprofile & Quellen
                    </Link>
                    {auth.user.role === 'admin' && (
                        <Link className={url.startsWith('/users') ? 'selected' : ''} href="/users">
                            <Users size={19} /> Bearbeiter
                        </Link>
                    )}
                </nav>
                <div className="sidebar-note">
                    <Building2 size={25} />
                    <p>
                        Aus Angeboten werden
                        <br />
                        neue Möglichkeiten.
                    </p>
                    <span>Ein Vorgang. Die ganze Historie.</span>
                </div>
                <div className="account">
                    <div className="avatar">{auth.user.name.slice(0, 1)}</div>
                    <div>
                        <strong>{auth.user.name}</strong>
                        <small>{auth.user.role === 'admin' ? 'Administrator' : 'Bearbeiter'}</small>
                    </div>
                    <button
                        aria-label="Abmelden"
                        className="icon-button"
                        onClick={() => router.post('/logout')}
                    >
                        <LogOut size={18} />
                    </button>
                </div>
            </aside>
            <main>
                <header>
                    <div>
                        <div className="eyebrow">2 COME HOME / AKQUISE</div>
                        <h1>{title}</h1>
                        {subtitle && <p className="subtitle">{subtitle}</p>}
                    </div>
                    {action}
                </header>
                {flash.success && (
                    <div role="status" className="notice success">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div role="alert" className="notice error">
                        {flash.error}
                    </div>
                )}
                {children}
                <footer>2 COME HOME · Akquise mit Überblick</footer>
            </main>
        </div>
    );
}
export function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <label className="field">
            <span>{label}</span>
            {children}
            {error && <small className="field-error">{error}</small>}
        </label>
    );
}
export function Errors({ errors }: { errors: Record<string, string> }) {
    return Object.keys(errors).length ? (
        <div className="notice error" role="alert">
            {Object.values(errors).map((e, i) => (
                <div key={i}>{e}</div>
            ))}
        </div>
    ) : null;
}
export function Status({ value, label }: { value: string; label: string }) {
    return (
        <span
            className={`badge ${value === 'won' || value === 'private' ? 'green' : value === 'blocked' ? 'red' : value === 'new' || value === 'unclear' ? 'amber' : ''}`}
        >
            {label}
        </span>
    );
}
export function Empty({ title, text }: { title: string; text: string }) {
    return (
        <div className="empty">
            <Search size={30} />
            <h3>{title}</h3>
            <p>{text}</p>
        </div>
    );
}
export function Back() {
    return (
        <Link className="text-link" href="/">
            ← Zur Übersicht
        </Link>
    );
}
export function External({ href, children }: { href: string | null; children: ReactNode }) {
    return href && /^https?:\/\//i.test(href) ? (
        <a className="text-link" href={href} target="_blank" rel="noreferrer">
            {children} <ArrowUpRight size={14} />
        </a>
    ) : null;
}
