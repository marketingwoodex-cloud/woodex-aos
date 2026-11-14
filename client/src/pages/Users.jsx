import { useState } from 'react';
import { del, patch, post } from '../lib/api.js';
import { useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import { ROLES, formatDate, relativeTime, statusMeta } from '../lib/format.js';
import {
  Avatar, Badge, Button, Card, ConfirmDialog, EmptyState, ErrorState, Field, IconButton, Input,
  Modal, PageHeader, Select, Spinner, StatusBadge,
} from '../components/ui.jsx';
import Icon from '../components/icons.jsx';

const BLANK = { name: '', email: '', role: 'manager', title: '', phone: '', password: '', status: 'active' };

export default function Users() {
  const { isAdmin, user: currentUser } = useAuth();
  const toast = useToast();
  const { data, loading, error, refresh } = useFetch(isAdmin ? '/users' : null);

  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(BLANK);
  const [saving, setSaving] = useState(false);
  const [toDelete, setToDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const users = data?.data ?? [];

  if (!isAdmin) {
    return (
      <>
        <PageHeader eyebrow="Workspace" title="Team & access" description="Manage who can see and change the Woodex workspace." />
        <Card>
          <EmptyState
            icon="shield"
            title="Administrators only"
            description="Your role does not include team management. Ask Ayesha Khan to grant administrator access if you need it."
          />
        </Card>
      </>
    );
  }

  function openCreate() {
    setEditing({ id: null });
    setForm(BLANK);
  }

  function openEdit(member) {
    setEditing(member);
    setForm({
      name: member.name,
      email: member.email,
      role: member.role,
      title: member.title ?? '',
      phone: member.phone ?? '',
      password: '',
      status: member.status,
    });
  }

  async function save(event) {
    event?.preventDefault();
    setSaving(true);
    try {
      if (editing?.id) {
        const body = { name: form.name, role: form.role, title: form.title, phone: form.phone, status: form.status };
        if (form.password) body.password = form.password;
        await patch(`/users/${editing.id}`, body);
        toast.success('Team member updated.');
      } else {
        await post('/users', form);
        toast.success('Invitation created.');
      }
      setEditing(null);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function confirmDelete() {
    setDeleting(true);
    try {
      await del(`/users/${toDelete.id}`);
      toast.success(`Removed ${toDelete.name}.`);
      setToDelete(null);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setDeleting(false);
    }
  }

  return (
    <>
      <PageHeader
        eyebrow="Workspace"
        title="Team & access"
        description="Three roles: administrators manage the workspace, managers run commerce, viewers read."
        actions={
          <Button variant="primary" icon="plus" onClick={openCreate}>
            Invite member
          </Button>
        }
      />

      <div className="kpi-grid kpi-grid-3">
        {ROLES.map((role) => (
          <div className="stat-card" key={role}>
            <div className="stat-top">
              <span className="stat-label">{statusMeta(role).label}s</span>
              <span className="stat-icon">
                <Icon name={role === 'admin' ? 'shield' : role === 'manager' ? 'sliders' : 'eye'} size={17} />
              </span>
            </div>
            <strong className="stat-value">{users.filter((member) => member.role === role).length}</strong>
            <span className="muted small">
              {role === 'admin' ? 'Full workspace control' : role === 'manager' ? 'Create and edit commerce data' : 'Read-only access'}
            </span>
          </div>
        ))}
      </div>

      <Card>
        {loading && !data ? (
          <Spinner label="Loading team…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : (
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Member</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th className="num">Last sign-in</th>
                  <th className="num">Joined</th>
                  <th className="num">Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.map((member) => (
                  <tr key={member.id}>
                    <td>
                      <div className="cell-main">
                        <Avatar name={member.name} color={member.accent} size={36} />
                        <div className="grow">
                          <div className="cell-title truncate">
                            {member.name}
                            {member.id === currentUser?.id ? <span className="muted small"> · you</span> : null}
                          </div>
                          <div className="cell-sub truncate">{member.email}</div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <Badge tone={statusMeta(member.role).tone}>{statusMeta(member.role).label}</Badge>
                      {member.title ? <div className="cell-sub">{member.title}</div> : null}
                    </td>
                    <td>
                      <StatusBadge value={member.status} />
                    </td>
                    <td className="num muted small nowrap">{member.last_login_at ? relativeTime(member.last_login_at) : 'Never'}</td>
                    <td className="num muted small nowrap">{formatDate(member.created_at)}</td>
                    <td>
                      <div className="row-actions">
                        <IconButton icon="edit" label="Edit member" onClick={() => openEdit(member)} />
                        <IconButton
                          icon="trash"
                          label="Remove member"
                          variant="danger"
                          disabled={member.id === currentUser?.id}
                          onClick={() => setToDelete(member)}
                        />
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <Modal
        open={Boolean(editing)}
        title={editing?.id ? `Edit ${editing.name}` : 'Invite a team member'}
        subtitle={editing?.id ? editing.email : 'Create credentials for a colleague.'}
        onClose={() => setEditing(null)}
        footer={
          <>
            <Button onClick={() => setEditing(null)} disabled={saving}>
              Cancel
            </Button>
            <Button variant="primary" onClick={save} loading={saving}>
              {editing?.id ? 'Save changes' : 'Create member'}
            </Button>
          </>
        }
      >
        <form className="form-grid" onSubmit={save}>
          <Field label="Full name" required>
            <Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required />
          </Field>
          <Field label="Email" required>
            <Input
              type="email"
              value={form.email}
              onChange={(event) => setForm({ ...form, email: event.target.value })}
              disabled={Boolean(editing?.id)}
              required
            />
          </Field>
          <Field label="Role">
            <Select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })} options={ROLES} />
          </Field>
          <Field label="Job title">
            <Input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder="Retail Operations Lead" />
          </Field>
          <Field label="Phone">
            <Input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} />
          </Field>
          <Field label="Status">
            <Select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} options={['active', 'suspended']} />
          </Field>
          <Field
            label={editing?.id ? 'New password' : 'Password'}
            hint={editing?.id ? 'Leave blank to keep the current password.' : 'At least 8 characters.'}
            required={!editing?.id}
            className="span-2"
          >
            <Input
              type="password"
              value={form.password}
              onChange={(event) => setForm({ ...form, password: event.target.value })}
              autoComplete="new-password"
              required={!editing?.id}
            />
          </Field>
          <button type="submit" hidden />
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(toDelete)}
        title="Remove this team member?"
        message={toDelete ? `${toDelete.name} will immediately lose access to the workspace. Their audit history is kept.` : ''}
        confirmLabel="Remove member"
        busy={deleting}
        onConfirm={confirmDelete}
        onClose={() => setToDelete(null)}
      />
    </>
  );
}
