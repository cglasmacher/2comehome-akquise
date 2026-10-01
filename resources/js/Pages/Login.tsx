import { Head, useForm } from '@inertiajs/react';
import { Field, Errors } from '../components';
export default function Login() {
    const f = useForm({ email: '', password: '', remember: false });
    return (
        <div className="login">
            <Head title="Anmelden" />
            <div className="login-art">
                <div className="brand">
                    2 COME HOME<span>IMMOBILIEN · AKQUISE</span>
                </div>
                <div>
                    <span className="eyebrow">DEIN NÄCHSTER AUFTRAG BEGINNT HIER.</span>
                    <h1>
                        Potenziale erkennen.
                        <br />
                        Kontakte gewinnen.
                    </h1>
                    <p>
                        Alle Angebote, Gespräche und nächsten Schritte
                        <br />
                        an einem Ort.
                    </p>
                </div>
                <small>2 COME HOME · Hilden</small>
            </div>
            <div className="login-panel">
                <div className="login-form">
                    <div className="eyebrow">WILLKOMMEN ZURÜCK</div>
                    <h1>
                        Deine Akquise.
                        <br />
                        Dein Überblick.
                    </h1>
                    <p className="subtitle">Melde dich mit deinem Bearbeiterzugang an.</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post('/login');
                        }}
                    >
                        <Errors errors={f.errors} />
                        <Field label="E-Mail-Adresse">
                            <input
                                type="email"
                                autoComplete="username"
                                required
                                value={f.data.email}
                                onChange={(e) => f.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field label="Passwort">
                            <input
                                type="password"
                                autoComplete="current-password"
                                required
                                value={f.data.password}
                                onChange={(e) => f.setData('password', e.target.value)}
                            />
                        </Field>
                        <label className="check">
                            <input
                                type="checkbox"
                                checked={f.data.remember}
                                onChange={(e) => f.setData('remember', e.target.checked)}
                            />
                            Angemeldet bleiben
                        </label>
                        <button disabled={f.processing} className="button wide">
                            {f.processing ? 'Anmeldung läuft …' : 'Anmelden →'}
                        </button>
                    </form>
                    <small className="muted">
                        Zugänge werden von eurem Administrator angelegt.
                    </small>
                </div>
            </div>
        </div>
    );
}
