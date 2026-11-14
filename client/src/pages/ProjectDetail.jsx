import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { del, patch, post } from '../lib/api.js';
import { useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import { ENVIRONMENTS, PROJECT_STATUSES, formatDateTime, number, relativeTime } from '../lib/format.js';
import {
  Badge, Button, Card, CardHead, ConfirmDialog, EmptyState, ErrorState, Field, IconButton, Input,
  Modal, PageHeader, Select, Spinner, StatusBadge, Textarea,
} from '../components/ui.jsx';
import { ProgressBar } from '../components/charts.jsx';
import Icon from '../components/icons.jsx';

export default function ProjectDetail() {
  const { id } = useParams();
  const { canWrite, isAdmin } = useAuth();
  const toast = useToast();
  const { data, loading, error, refresh } = useFetch(`/projects/${id}`);
  const project = data?.data;

  const [deploying, setDeploying] = useState(false);
  const [keyModal, setKeyModal] = useState(false);
  const [keyLabel, setKeyLabel] = useState('');
  const [keyScopes, setKeyScopes] = useState('read');
  const [newSecret, setNewSecret] = useState('');
  const [revoking, setRevoking] = useState(null);
  const [savingKey, setSavingKey] = useState(false);
  const [editing, setEditing] = useState(false);
  const [editForm, setEditForm] = useState(null);
  const [savingEdit, setSavingEdit] = useState(false);

  if (loading && !project) return <Spinner label="Loading project…" />;
  if (error) return <ErrorState message={error} onRetry={refresh} />;
  if (!project) return null;

  async function deploy() {
    setDeploying(true);
    try {
      const result = await post(`/projects/${project.id}/deployments`, {
        environment: project.environment,
        notes: 'Triggered from the Woodex AOS deployment console',
      });
      toast.success(`Deployed ${result.data.version} to ${project.environment}.`);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setDeploying(false);
    }
  }

  async function createKey() {
    if (!keyLabel.trim()) return toast.error('Give the key a label.');
    setSavingKey(true);
    try {
      const result = await post(`/projects/${project.id}/keys`, { label: keyLabel.trim(), scopes: keyScopes.split(',') });
      setNewSecret(result.secret);
      setKeyModal(false);
      setKeyLabel('');
      setKeyScopes('read');
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSavingKey(false);
    }
  }

  async function revokeKey() {
    try {
      await del(`/projects/${project.id}/keys/${revoking.id}`);
      toast.success(`Revoked “${revoking.label}”.`);
      setRevoking(null);
      refresh();
    } catch (err) {
      toast.error(err.message);
    }
  }

  function openEdit() {
    setEditForm({
      name: project.name,
      client: project.client ?? '',
      description: project.description ?? '',
      environment: project.environment,
      status: project.status,
      apiBase: project.api_base ?? '',
      progress: String(project.progress),
      region: project.region ?? '',
    });
    setEditing(true);
  }

  async function saveEdit(event) {
    event?.preventDefault();
    setSavingEdit(true);
    try {
      await patch(`/projects/${project.id}`, { ...editForm, progress: Number(editForm.progress) });
      toast.success('Project updated.');
      setEditing(false);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSavingEdit(false);
    }
  }

  return (
    <>
      <div>
        <Link to="/projects" className="btn btn-ghost btn-sm" style={{ marginBottom: 10, paddingLeft: 0 }}>
          <Icon name="chevronLeft" size={15} /> All projects
        </Link>
        <PageHeader
          eyebrow={`${project.code} · ${project.environment}`}
          title={project.name}
          description={project.description ?? 'No description recorded for this project.'}
          actions={
            <>
              {canWrite ? (
                <>
                  <Button icon="edit" onClick={openEdit}>
                    Edit
                  </Button>
                  <Button variant="primary" icon="rocket" onClick={deploy} loading={deploying}>
                    Deploy now
                  </Button>
                </>
              ) : null}
            </>
          }
        />
      </div>

      <div className="detail-grid">
        <div className="detail-main">
          <Card>
            <CardHead
              title="Deployment history"
              subtitle="Newest first · last 25 releases"
              icon="rocket"
              actions={<Badge tone="neutral">{project.stats.deployments} total</Badge>}
            />
            <div style={{ padding: '4px 18px 14px' }}>
              {project.deployments.length === 0 ? (
                <EmptyState icon="rocket" title="No deployments yet" description="Trigger the first release from the deploy button above." />
              ) : (
                project.deployments.map((deployment) => (
                  <div className="deploy-row" key={deployment.id}>
                    <span className={`deploy-dot ${deployment.status}`} />
                    <div className="grow">
                      <div className="row" style={{ gap: 8 }}>
                        <span className="deploy-version">{deployment.version}</span>
                        <StatusBadge value={deployment.status} />
                      </div>
                      <div className="deploy-meta">
                        <span>{deployment.environment}</span>
                        <span>·</span>
                        <span className="mono">{deployment.commit_sha}</span>
                        <span>·</span>
                        <span>{Math.round((deployment.duration_ms ?? 0) / 1000)}s</span>
                        <span>·</span>
                        <span>{deployment.created_by ?? 'System'}</span>
                      </div>
                      {deployment.notes ? <div className="deploy-meta">{deployment.notes}</div> : null}
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <div className="muted small nowrap">{relativeTime(deployment.created_at)}</div>
                      <div className="muted small nowrap">{formatDateTime(deployment.created_at)}</div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>

          <Card>
            <CardHead
              title="API keys"
              subtitle="Scoped credentials for this project"
              icon="key"
              actions={
                canWrite ? (
                  <Button size="sm" icon="plus" onClick={() => setKeyModal(true)}>
                    Issue key
                  </Button>
                ) : null
              }
            />
            {project.apiKeys.length === 0 ? (
              <EmptyState icon="key" title="No API keys" description="Issue a scoped key to let this project call the Woodex API." />
            ) : (
              <div className="table-wrap">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Label</th>
                      <th>Prefix</th>
                      <th>Scopes</th>
                      <th>Status</th>
                      <th className="num">Last used</th>
                      {canWrite ? <th /> : null}
                    </tr>
                  </thead>
                  <tbody>
                    {project.apiKeys.map((key) => (
                      <tr key={key.id}>
                        <td className="strong">{key.label}</td>
                        <td className="mono small">{key.prefix}…</td>
                        <td className="muted small">{key.scopes}</td>
                        <td>
                          <StatusBadge value={key.status} />
                        </td>
                        <td className="num muted small nowrap">{key.last_used_at ? relativeTime(key.last_used_at) : 'Never'}</td>
                        {canWrite ? (
                          <td>
                            <div className="row-actions">
                              {key.status === 'active' ? (
                                <IconButton icon="trash" label="Revoke key" variant="danger" onClick={() => setRevoking(key)} />
                              ) : null}
                            </div>
                          </td>
                        ) : null}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Card>
        </div>

        <aside className="detail-side">
          <Card className="card-padded">
            <CardHead title="Overview" icon="layers" />
            <div className="stack">
              <div className="row wrap">
                <StatusBadge value={project.status} />
                <Badge tone={project.environment === 'production' ? 'success' : 'info'}>{project.environment}</Badge>
              </div>
              <div>
                <div className="row-between" style={{ marginBottom: 6 }}>
                  <span className="muted small">Rollout</span>
                  <span className="strong small">{project.progress}%</span>
                </div>
                <ProgressBar value={project.progress} tone={project.status === 'live' ? 'success' : 'accent'} />
              </div>
              <dl className="kv">
                <dt>Client</dt>
                <dd>{project.client ?? 'Internal'}</dd>
                <dt>Region</dt>
                <dd>{project.region ?? '—'}</dd>
                <dt>API base</dt>
                <dd className="mono small" style={{ wordBreak: 'break-all' }}>{project.api_base ?? 'Not configured'}</dd>
                <dt>Created</dt>
                <dd>{formatDateTime(project.created_at)}</dd>
                <dt>Successful deploys</dt>
                <dd>{number(project.stats.successful)}</dd>
                <dt>Active keys</dt>
                <dd>{number(project.stats.active_keys)}</dd>
              </dl>
            </div>
          </Card>

          <Card className="card-padded">
            <CardHead title="Operations" icon="sliders" />
            <div className="stack-sm">
              <div className="notice notice-accent">
                <Icon name="alert" size={15} />
                <span>Deploys from this console write a release record and flip the project to live. Connect a CI provider for real artefacts.</span>
              </div>
              <p className="muted small">Last updated {relativeTime(project.updated_at)}.</p>
              {isAdmin ? (
                <Button variant="danger" icon="trash" disabled title="Project deletion is disabled in the demo workspace">
                  Delete project
                </Button>
              ) : null}
            </div>
          </Card>
        </aside>
      </div>

      <Modal
        open={keyModal}
        title="Issue an API key"
        subtitle="The secret is shown once — store it in the project's secret manager."
        onClose={() => setKeyModal(false)}
        footer={
          <>
            <Button onClick={() => setKeyModal(false)} disabled={savingKey}>
              Cancel
            </Button>
            <Button variant="primary" onClick={createKey} loading={savingKey}>
              Create key
            </Button>
          </>
        }
      >
        <Field label="Label" required>
          <Input value={keyLabel} onChange={(event) => setKeyLabel(event.target.value)} placeholder="Storefront production" />
        </Field>
        <Field label="Scopes">
          <Select
            value={keyScopes}
            onChange={(event) => setKeyScopes(event.target.value)}
            options={[
              { value: 'read', label: 'Read only' },
              { value: 'read,write', label: 'Read and write' },
            ]}
          />
        </Field>
      </Modal>

      <Modal
        open={Boolean(newSecret)}
        title="Copy your new API key"
        subtitle="This secret will not be shown again."
        onClose={() => setNewSecret('')}
        footer={
          <>
            <Button
              icon="copy"
              onClick={() => {
                navigator.clipboard?.writeText(newSecret);
                toast.info('Key copied to the clipboard.');
              }}
            >
              Copy key
            </Button>
            <Button variant="primary" onClick={() => setNewSecret('')}>
              Done
            </Button>
          </>
        }
      >
        <div className="secret">{newSecret}</div>
        <p className="muted small">Send this to the integration owner over a secure channel. Revoke it from the key table if it leaks.</p>
      </Modal>

      <Modal
        open={editing}
        title="Edit project"
        onClose={() => setEditing(false)}
        footer={
          <>
            <Button onClick={() => setEditing(false)} disabled={savingEdit}>
              Cancel
            </Button>
            <Button variant="primary" onClick={saveEdit} loading={savingEdit}>
              Save changes
            </Button>
          </>
        }
      >
        {editForm ? (
          <form className="form-grid" onSubmit={saveEdit}>
            <Field label="Name" required className="span-2">
              <Input value={editForm.name} onChange={(event) => setEditForm({ ...editForm, name: event.target.value })} required />
            </Field>
            <Field label="Client">
              <Input value={editForm.client} onChange={(event) => setEditForm({ ...editForm, client: event.target.value })} />
            </Field>
            <Field label="Region">
              <Input value={editForm.region} onChange={(event) => setEditForm({ ...editForm, region: event.target.value })} />
            </Field>
            <Field label="Environment">
              <Select value={editForm.environment} onChange={(event) => setEditForm({ ...editForm, environment: event.target.value })} options={ENVIRONMENTS} />
            </Field>
            <Field label="Status">
              <Select value={editForm.status} onChange={(event) => setEditForm({ ...editForm, status: event.target.value })} options={PROJECT_STATUSES} />
            </Field>
            <Field label="API base">
              <Input value={editForm.apiBase} onChange={(event) => setEditForm({ ...editForm, apiBase: event.target.value })} />
            </Field>
            <Field label="Rollout (%)">
              <Input type="number" min="0" max="100" value={editForm.progress} onChange={(event) => setEditForm({ ...editForm, progress: event.target.value })} />
            </Field>
            <Field label="Description" className="span-2">
              <Textarea value={editForm.description} onChange={(event) => setEditForm({ ...editForm, description: event.target.value })} />
            </Field>
            <button type="submit" hidden />
          </form>
        ) : null}
      </Modal>

      <ConfirmDialog
        open={Boolean(revoking)}
        title="Revoke this API key?"
        message={revoking ? `“${revoking.label}” will stop working immediately. This cannot be undone.` : ''}
        confirmLabel="Revoke key"
        onConfirm={revokeKey}
        onClose={() => setRevoking(null)}
      />
    </>
  );
}
