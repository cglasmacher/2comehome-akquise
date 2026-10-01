import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Layout, Field, Errors, Status } from '../components';
import { User } from '../types';
export default function Users({ users }: { users: User[] }) {
    const [id, setId] = useState<number | null>(null);
    const f = useForm({ name: '', email: '', password: '', role: 'agent', active: true });
    return (
        <Layout title="Bearbeiter" subtitle="Zugänge verwalten und Verantwortlichkeiten festlegen.">
            <div className="detail-grid">
                <section className="card">
                    <div className="section-head">
                        <h2>Dein Team</h2>
                    </div>
                    {users.map((u) => (
                        <div key={u.id} className="profile-row">
                            <div className="avatar dark">{u.name.slice(0, 1)}</div>
                            <div className="grow">
                                <strong>{u.name}</strong>
                                <small>
                                    {u.email} ·{' '}
                                    {u.role === 'admin' ? 'Administrator' : 'Bearbeiter'}
                                </small>
                            </div>
                            <Status
                                value={u.active ? 'private' : 'blocked'}
                                label={u.active ? 'Aktiv' : 'Deaktiviert'}
                            />
                            <button
                                className="button secondary"
                                onClick={() => {
                                    setId(u.id);
                                    f.setData({
                                        name: u.name,
                                        email: u.email,
                                        password: '',
                                        role: u.role,
                                        active: u.active,
                                    });
                                    f.clearErrors();
                                }}
                            >
                                Bearbeiten
                            </button>
                        </div>
                    ))}
                </section>
                <form
                    className="card form-card"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const opts = {
                            onSuccess: () => {
                                f.reset();
                                setId(null);
                            },
                        };
                        id ? f.put(`/users/${id}`, opts) : f.post('/users', opts);
                    }}
                >
                    <h2>{id ? 'Bearbeiter bearbeiten' : 'Bearbeiter anlegen'}</h2>
                    <Errors errors={f.errors} />
                    <Field label="Name">
                        <input
                            required
                            value={f.data.name}
                            onChange={(e) => f.setData('name', e.target.value)}
                        />
                    </Field>
                    <Field label="E-Mail">
                        <input
                            type="email"
                            required
                            value={f.data.email}
                            onChange={(e) => f.setData('email', e.target.value)}
                        />
                    </Field>
                    <Field label={id ? 'Neues Passwort (optional)' : 'Passwort'}>
                        <input
                            type="password"
                            autoComplete="new-password"
                            required={!id}
                            minLength={12}
                            value={f.data.password}
                            onChange={(e) => f.setData('password', e.target.value)}
                        />
                        <small>Mindestens 12 Zeichen, Groß-/Kleinbuchstaben und Zahl.</small>
                    </Field>
                    <Field label="Rolle">
                        <select
                            value={f.data.role}
                            onChange={(e) => f.setData('role', e.target.value)}
                        >
                            <option value="agent">Bearbeiter</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </Field>
                    <label className="check">
                        <input
                            type="checkbox"
                            checked={f.data.active}
                            onChange={(e) => f.setData('active', e.target.checked)}
                        />
                        Zugang aktiv
                    </label>
                    <div className="inline">
                        <button disabled={f.processing} className="button">
                            Speichern
                        </button>
                        {id && (
                            <button
                                type="button"
                                className="button secondary"
                                onClick={() => {
                                    setId(null);
                                    f.reset();
                                    f.clearErrors();
                                }}
                            >
                                Neuer Bearbeiter
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </Layout>
    );
}
