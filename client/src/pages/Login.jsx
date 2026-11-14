import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BrandMark } from '../components/icons.jsx';
import Icon from '../components/icons.jsx';
import { Button, Field, Input } from '../components/ui.jsx';
import { useAuth } from '../state/AuthContext.jsx';
import { statusMeta } from '../lib/format.js';

const DEMO_ACCOUNTS = [
  { role: 'admin', email: 'admin@woodex.pk', password: 'Woodex@2026', label: 'Administrator' },
  { role: 'manager', email: 'manager@woodex.pk', password: 'Manager@2026', label: 'Manager' },
  { role: 'viewer', email: 'viewer@woodex.pk', password: 'Viewer@2026', label: 'Viewer' },
];

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('admin@woodex.pk');
  const [password, setPassword] = useState('Woodex@2026');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setBusy(true);
    try {
      await login(email.trim(), password);
      navigate('/dashboard', { replace: true });
    } catch (err) {
      setError(err.message || 'Could not sign in.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login">
      <section className="login-brand">
        <div className="login-brand-top">
          <BrandMark size={42} />
          <div className="login-brand-name">
            <strong>Woodex</strong>
            <small>Operating System</small>
          </div>
        </div>

        <div className="login-hero">
          <h1>
            Every sofa, order and <em>deployment</em> in one place.
          </h1>
          <p>
            Woodex AOS runs the storefront, showroom orders, trade partners and production data for a
            furniture manufacturer — with one clean audit trail behind it.
          </p>
        </div>

        <div>
          <div className="login-metrics">
            <div className="login-metric">
              <strong>30</strong>
              <span>Catalogue items</span>
            </div>
            <div className="login-metric">
              <strong>5</strong>
              <span>Live projects</span>
            </div>
            <div className="login-metric">
              <strong>6</strong>
              <span>Showrooms</span>
            </div>
          </div>
          <div className="login-quote" style={{ marginTop: 28 }}>
            “Production, trade sales and the storefront finally agree on one number.”
            <span>Ayesha Khan · Platform Owner</span>
          </div>
        </div>
      </section>

      <section className="login-panel">
        <div className="login-card">
          <div>
            <h2>Welcome back</h2>
            <p className="sub">Sign in to your Woodex operations workspace.</p>
          </div>

          <form className="login-form" onSubmit={submit}>
            {error ? (
              <div className="login-error">
                <Icon name="alert" size={15} />
                <span>{error}</span>
              </div>
            ) : null}

            <Field label="Work email" required>
              <Input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="you@woodex.pk"
                autoComplete="username"
                required
              />
            </Field>

            <Field label="Password" required>
              <Input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="••••••••"
                autoComplete="current-password"
                required
              />
            </Field>

            <Button type="submit" variant="primary" size="lg" className="btn-block" loading={busy}>
              {busy ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>

          <div className="demo-accounts">
            <p className="demo-title">Demo accounts — tap to fill</p>
            {DEMO_ACCOUNTS.map((account) => (
              <button
                key={account.email}
                type="button"
                className="demo-account"
                onClick={() => {
                  setEmail(account.email);
                  setPassword(account.password);
                  setError('');
                }}
              >
                <span>
                  <strong>{account.label}</strong>
                  <small>{account.email}</small>
                </span>
                <span className={`badge badge-${statusMeta(account.role).tone}`}>{account.password}</span>
              </button>
            ))}
          </div>

          <p className="login-foot">Seeded demo workspace · sessions expire after 7 days</p>
        </div>
      </section>
    </div>
  );
}
