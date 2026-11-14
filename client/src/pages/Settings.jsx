import { useEffect, useState } from 'react';
import { patch } from '../lib/api.js';
import { useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import { relativeTime, statusMeta } from '../lib/format.js';
import {
  Badge, Button, Card, CardHead, ErrorState, Field, Input, PageHeader, Select, Spinner, StatusBadge, Textarea,
} from '../components/ui.jsx';
import Icon from '../components/icons.jsx';

const DEFAULTS = {
  org_name: 'Woodex Furniture',
  currency: 'PKR',
  tax_rate: '5',
  support_email: '',
  phone: '',
  address: '',
  timezone: 'Asia/Karachi',
  low_stock_threshold: '5',
  brand_color: '#B4531F',
};

export default function Settings() {
  const { isAdmin } = useAuth();
  const toast = useToast();
  const { data, loading, error, refresh } = useFetch('/settings');
  const [form, setForm] = useState(DEFAULTS);
  const [saving, setSaving] = useState(false);
  const [integrationBusy, setIntegrationBusy] = useState(null);

  useEffect(() => {
    if (data?.data?.settings) {
      setForm({ ...DEFAULTS, ...data.data.settings });
    }
  }, [data]);

  if (loading && !data) return <Spinner label="Loading workspace settings…" />;
  if (error) return <ErrorState message={error} onRetry={refresh} />;

  const integrations = data?.data?.integrations ?? [];

  async function save(event) {
    event?.preventDefault();
    setSaving(true);
    try {
      await patch('/settings', form);
      toast.success('Workspace settings saved.');
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function toggleIntegration(integration) {
    setIntegrationBusy(integration.id);
    const nextStatus = integration.status === 'connected' ? 'disabled' : 'connected';
    try {
      await patch(`/settings/integrations/${integration.id}`, { status: nextStatus });
      toast.success(`${integration.name} ${nextStatus === 'connected' ? 'connected' : 'disabled'}.`);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setIntegrationBusy(null);
    }
  }

  return (
    <>
      <PageHeader
        eyebrow="Workspace"
        title="Settings"
        description="Organisation profile, commerce defaults and the integrations wired into Woodex AOS."
        actions={
          isAdmin ? (
            <Button variant="primary" icon="check" onClick={save} loading={saving}>
              Save settings
            </Button>
          ) : (
            <Badge tone="neutral">Read-only for your role</Badge>
          )
        }
      />

      <div className="detail-grid">
        <div className="detail-main">
          <Card className="card-padded">
            <CardHead title="Organisation profile" subtitle="Shown on invoices and customer emails" icon="building" />
            <form className="form-grid" onSubmit={save}>
              <Field label="Organisation name">
                <Input value={form.org_name} onChange={(event) => setForm({ ...form, org_name: event.target.value })} disabled={!isAdmin} />
              </Field>
              <Field label="Support email">
                <Input type="email" value={form.support_email} onChange={(event) => setForm({ ...form, support_email: event.target.value })} disabled={!isAdmin} />
              </Field>
              <Field label="Phone">
                <Input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} disabled={!isAdmin} />
              </Field>
              <Field label="Timezone">
                <Select
                  value={form.timezone}
                  onChange={(event) => setForm({ ...form, timezone: event.target.value })}
                  options={['Asia/Karachi', 'Asia/Dubai', 'Asia/Riyadh', 'Europe/London', 'UTC']}
                  disabled={!isAdmin}
                />
              </Field>
              <Field label="Address" className="span-2">
                <Textarea value={form.address} onChange={(event) => setForm({ ...form, address: event.target.value })} disabled={!isAdmin} />
              </Field>
              <button type="submit" hidden />
            </form>
          </Card>

          <Card className="card-padded">
            <CardHead title="Commerce defaults" subtitle="Applied to new orders and stock alerts" icon="receipt" />
            <form className="form-grid" onSubmit={save}>
              <Field label="Currency">
                <Select
                  value={form.currency}
                  onChange={(event) => setForm({ ...form, currency: event.target.value })}
                  options={['PKR', 'AED', 'SAR', 'USD', 'GBP']}
                  disabled={!isAdmin}
                />
              </Field>
              <Field label="Sales tax (%)">
                <Input type="number" min="0" max="30" value={form.tax_rate} onChange={(event) => setForm({ ...form, tax_rate: event.target.value })} disabled={!isAdmin} />
              </Field>
              <Field label="Low stock threshold" hint="Used by dashboard alerts and reorder prompts.">
                <Input type="number" min="0" value={form.low_stock_threshold} onChange={(event) => setForm({ ...form, low_stock_threshold: event.target.value })} disabled={!isAdmin} />
              </Field>
              <Field label="Brand colour">
                <div className="row">
                  <input
                    type="color"
                    value={form.brand_color}
                    onChange={(event) => setForm({ ...form, brand_color: event.target.value })}
                    disabled={!isAdmin}
                    style={{ width: 46, height: 40, border: '1px solid var(--border-strong)', borderRadius: 10, background: 'var(--surface)', padding: 3 }}
                    aria-label="Brand colour"
                  />
                  <Input value={form.brand_color} onChange={(event) => setForm({ ...form, brand_color: event.target.value })} disabled={!isAdmin} />
                </div>
              </Field>
              <button type="submit" hidden />
            </form>
          </Card>
        </div>

        <aside className="detail-side">
          <Card>
            <CardHead
              title="Integrations"
              subtitle="Third-party services connected to the platform"
              icon="layers"
              actions={<Badge tone="neutral">{integrations.filter((item) => item.status === 'connected').length} live</Badge>}
            />
            <div style={{ padding: '4px 18px 14px' }}>
              {integrations.map((integration) => (
                <div key={integration.id} style={{ display: 'grid', gap: 7, padding: '13px 0', borderBottom: '1px dashed var(--border)' }}>
                  <div className="row-between">
                    <div className="row" style={{ gap: 9 }}>
                      <span className="cell-thumb" style={{ width: 30, height: 30, borderRadius: 8 }}>
                        <Icon name={integration.category === 'Payments' ? 'key' : integration.category === 'Logistics' ? 'building' : integration.category === 'Analytics' ? 'pulse' : integration.category === 'Finance' ? 'receipt' : 'sparkle'} size={14} />
                      </span>
                      <div>
                        <div className="cell-title">{integration.name}</div>
                        <div className="cell-sub">{integration.category}</div>
                      </div>
                    </div>
                    <StatusBadge value={integration.status} />
                  </div>
                  <p className="muted small">{integration.description}</p>
                  <div className="row-between">
                    <span className="muted small">Updated {relativeTime(integration.updated_at)}</span>
                    {isAdmin ? (
                      <Button
                        size="sm"
                        variant={integration.status === 'connected' ? 'secondary' : 'primary'}
                        loading={integrationBusy === integration.id}
                        onClick={() => toggleIntegration(integration)}
                      >
                        {integration.status === 'connected' ? 'Disable' : 'Connect'}
                      </Button>
                    ) : null}
                  </div>
                </div>
              ))}
            </div>
          </Card>

          <Card className="card-padded">
            <CardHead title="Environment" icon="sliders" />
            <div className="stack-sm">
              <div className="row-between">
                <span className="muted small">API service</span>
                <Badge tone="success" dot>Operational</Badge>
              </div>
              <div className="row-between">
                <span className="muted small">Database</span>
                <span className="small mono">SQLite · WAL</span>
              </div>
              <div className="row-between">
                <span className="muted small">Session lifetime</span>
                <span className="small">7 days</span>
              </div>
              <div className="row-between">
                <span className="muted small">Your role</span>
                <Badge tone={statusMeta(isAdmin ? 'admin' : 'manager').tone}>{statusMeta(isAdmin ? 'admin' : 'manager').label}</Badge>
              </div>
              <p className="muted small" style={{ marginTop: 4 }}>
                Configure secrets such as <span className="mono">JWT_SECRET</span> and <span className="mono">PORT</span> through environment variables before deploying.
              </p>
            </div>
          </Card>
        </aside>
      </div>
    </>
  );
}
