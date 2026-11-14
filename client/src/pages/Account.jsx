import { useState } from 'react';
import { post } from '../lib/api.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import { formatDateTime, relativeTime, statusMeta } from '../lib/format.js';
import { Avatar, Badge, Button, Card, CardHead, Field, Input, PageHeader, StatusBadge } from '../components/ui.jsx';
import Icon from '../components/icons.jsx';

export default function Account() {
  const { user } = useAuth();
  const toast = useToast();
  const [form, setForm] = useState({ currentPassword: '', newPassword: '', confirmPassword: '' });
  const [saving, setSaving] = useState(false);

  async function changePassword(event) {
    event.preventDefault();
    if (form.newPassword.length < 8) return toast.error('New password must be at least 8 characters.');
    if (form.newPassword !== form.confirmPassword) return toast.error('The new passwords do not match.');
    setSaving(true);
    try {
      await post('/auth/password', { currentPassword: form.currentPassword, newPassword: form.newPassword });
      toast.success('Password updated. Use it next time you sign in.');
      setForm({ currentPassword: '', newPassword: '', confirmPassword: '' });
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <PageHeader eyebrow="Workspace" title="My account" description="Your profile, role and sign-in credentials." />

      <div className="detail-grid">
        <div className="detail-main">
          <Card className="card-padded">
            <CardHead title="Change password" subtitle="Choose something at least 8 characters long" icon="shield" />
            <form className="form-grid" onSubmit={changePassword}>
              <Field label="Current password" required className="span-2">
                <Input
                  type="password"
                  value={form.currentPassword}
                  onChange={(event) => setForm({ ...form, currentPassword: event.target.value })}
                  autoComplete="current-password"
                  required
                />
              </Field>
              <Field label="New password" required>
                <Input
                  type="password"
                  value={form.newPassword}
                  onChange={(event) => setForm({ ...form, newPassword: event.target.value })}
                  autoComplete="new-password"
                  required
                />
              </Field>
              <Field label="Confirm new password" required>
                <Input
                  type="password"
                  value={form.confirmPassword}
                  onChange={(event) => setForm({ ...form, confirmPassword: event.target.value })}
                  autoComplete="new-password"
                  required
                />
              </Field>
              <div className="span-2 row" style={{ justifyContent: 'flex-end' }}>
                <Button type="submit" variant="primary" loading={saving}>
                  Update password
                </Button>
              </div>
            </form>
          </Card>
        </div>

        <aside className="detail-side">
          <Card className="card-padded">
            <div className="row" style={{ gap: 14 }}>
              <Avatar name={user?.name} color={user?.accent} size={56} />
              <div>
                <h2 className="card-title" style={{ fontSize: 16 }}>
                  {user?.name}
                </h2>
                <p className="muted small">{user?.title ?? 'Team member'}</p>
              </div>
            </div>
            <div className="stack" style={{ marginTop: 16 }}>
              <div className="row-between">
                <span className="muted small">Role</span>
                <Badge tone={statusMeta(user?.role).tone}>{statusMeta(user?.role).label}</Badge>
              </div>
              <div className="row-between">
                <span className="muted small">Status</span>
                <StatusBadge value={user?.status} />
              </div>
              <div className="row-between">
                <span className="muted small">Email</span>
                <span className="small">{user?.email}</span>
              </div>
              <div className="row-between">
                <span className="muted small">Phone</span>
                <span className="small">{user?.phone ?? '—'}</span>
              </div>
              <div className="row-between">
                <span className="muted small">Last sign-in</span>
                <span className="small">{user?.last_login_at ? relativeTime(user.last_login_at) : '—'}</span>
              </div>
              <div className="row-between">
                <span className="muted small">Member since</span>
                <span className="small">{formatDateTime(user?.created_at)}</span>
              </div>
            </div>
            <div className="notice" style={{ marginTop: 16 }}>
              <Icon name="shield" size={15} />
              <span>Sessions are signed with an httpOnly cookie and expire after seven days.</span>
            </div>
          </Card>
        </aside>
      </div>
    </>
  );
}
