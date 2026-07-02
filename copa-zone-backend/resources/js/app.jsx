import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

const initialForm = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
};

async function apiRequest(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
    };

    const response = await fetch(path, {
        ...options,
        headers,
        credentials: 'same-origin',
    });

    if (response.status === 204) {
        return null;
    }

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        const message = payload?.message
            || payload?.error?.message
            || Object.values(payload?.errors || {})?.[0]?.[0]
            || 'Não foi possível concluir a operação.';

        throw new Error(message);
    }

    return payload;
}

function getCsrfToken() {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

async function refreshCsrfToken() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
}

function App() {
    const [mode, setMode] = useState('login');
    const [form, setForm] = useState(initialForm);
    const [user, setUser] = useState(null);
    const [isBooting, setIsBooting] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');

    const isRegister = mode === 'register';

    const title = useMemo(() => {
        if (user) {
            return 'Sua sessão CopaZone';
        }

        return isRegister ? 'Criar conta' : 'Entrar no CopaZone';
    }, [isRegister, user]);

    useEffect(() => {
        apiRequest('/api/v1/me')
            .then((payload) => setUser(payload.data.user))
            .catch(() => setUser(null))
            .finally(() => setIsBooting(false));
    }, []);

    function updateField(event) {
        setForm((current) => ({
            ...current,
            [event.target.name]: event.target.value,
        }));
    }

    async function submitAuth(event) {
        event.preventDefault();
        setIsSubmitting(true);
        setError('');
        setNotice('');

        const path = isRegister ? '/api/v1/auth/register' : '/api/v1/auth/login';
        const body = isRegister
            ? form
            : { email: form.email, password: form.password };

        try {
            await refreshCsrfToken();

            const payload = await apiRequest(path, {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': getCsrfToken() },
                body: JSON.stringify(body),
            });

            setUser(payload.data.user);
            setForm(initialForm);
            setNotice(payload.message);
        } catch (authError) {
            setError(authError.message);
        } finally {
            setIsSubmitting(false);
        }
    }

    async function logout() {
        setIsSubmitting(true);
        setError('');
        setNotice('');

        try {
            await refreshCsrfToken();

            const payload = await apiRequest('/api/v1/auth/logout', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': getCsrfToken() },
            });

            setUser(null);
            setNotice(payload.message);
        } catch (logoutError) {
            setError(logoutError.message);
        } finally {
            setIsSubmitting(false);
        }
    }

    if (isBooting) {
        return (
            <main className="auth-shell">
                <section className="auth-panel compact">
                    <p className="eyebrow">CopaZone</p>
                    <h1>Validando sessão</h1>
                </section>
            </main>
        );
    }

    return (
        <main className="auth-shell">
            <section className="auth-panel">
                <div className="brand-block">
                    <p className="eyebrow">CopaZone</p>
                    <h1>{title}</h1>
                    <p>
                        Autenticação por sessão segura. O cookie é HttpOnly e o frontend apenas envia as requisições com credenciais.
                    </p>
                </div>

                {notice && <div className="alert success">{notice}</div>}
                {error && <div className="alert error">{error}</div>}

                {user ? (
                    <div className="profile-box">
                        <div>
                            <span>Usuário autenticado</span>
                            <strong>{user.name}</strong>
                            <small>{user.email}</small>
                        </div>
                        <button type="button" onClick={logout} disabled={isSubmitting}>
                            {isSubmitting ? 'Saindo...' : 'Sair'}
                        </button>
                    </div>
                ) : (
                    <form className="auth-form" onSubmit={submitAuth}>
                        {isRegister && (
                            <label>
                                Nome
                                <input
                                    name="name"
                                    value={form.name}
                                    onChange={updateField}
                                    autoComplete="name"
                                    required
                                />
                            </label>
                        )}

                        <label>
                            E-mail
                            <input
                                type="email"
                                name="email"
                                value={form.email}
                                onChange={updateField}
                                autoComplete="email"
                                required
                            />
                        </label>

                        <label>
                            Senha
                            <input
                                type="password"
                                name="password"
                                value={form.password}
                                onChange={updateField}
                                autoComplete={isRegister ? 'new-password' : 'current-password'}
                                required
                            />
                        </label>

                        {isRegister && (
                            <label>
                                Confirmar senha
                                <input
                                    type="password"
                                    name="password_confirmation"
                                    value={form.password_confirmation}
                                    onChange={updateField}
                                    autoComplete="new-password"
                                    required
                                />
                            </label>
                        )}

                        <button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? 'Processando...' : isRegister ? 'Criar conta' : 'Entrar'}
                        </button>
                    </form>
                )}

                {!user && (
                    <button
                        className="link-button"
                        type="button"
                        onClick={() => {
                            setMode(isRegister ? 'login' : 'register');
                            setError('');
                            setNotice('');
                        }}
                    >
                        {isRegister ? 'Já tenho conta' : 'Criar uma nova conta'}
                    </button>
                )}
            </section>
        </main>
    );
}

createRoot(document.getElementById('root')).render(<App />);
