import { useState } from 'react';
import { Link } from 'react-router-dom';
import { post } from '../lib/api.js';
import { useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import { ENVIRONMENTS, PROJECT_STATUSES, formatDate, number, relativeTime, statusMeta } from '../lib/format.js';
import {
  Badge, Button, Card, EmptyState, ErrorState, Field, Input, Modal, PageHeader, Select, Spinner, StatusBadge, Textarea,
} from '../components/ui.jsx';
import { ProgressBar } from '../components/charts.jsx';
import Icon from '../components/icons.jsx';

const BLANK = {
  name: '', code: '', client: '', description: '', environment: 'staging',
  status: 'building', apiBase: '', progress: '0', region: 'ap-south-1',
};

export default function Projects() {
  const { canWrite } = useAuth();
  const toast = useToast();
  const { data, loading, error, refresh } = useFetch('/projects');
  const [creating, setCreating] = useState(false);
  const [form, setForm] = useState(BLANK);
  const [saving, setSaving] = useState(false);

  const projects = data?.data ?? [];
  const live = projects.filter((project) => project.status === 'live').length;
  const deploys = projects.reduce((sum, project) => sum + project.deployment_count, 0);

  async function save(event) {
    event?.preventDefault();
    setSaving(true);
    try {
      await post('/projects', { ...form, progress: Number(form.progress) });
      toast.success('Project registered.');
      setCreating(false);
      setForm(BLANK);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <PageHeader
        eyebrow="Platform"
        title="Projects & integrations"
        description="Every client system running on the Woodex platform — with deployment history and API access."
        actions={
          canWrite ? (
            <Button variant="primary" icon="plus" onClick={() => setCreating(true)}>
              New project
            </Button>
          ) : null
        }
      />

      <div className="kpi-grid kpi-grid-3">
        <div className="stat-card">
          <span className="stat-label">Projects</span>
          <strong className="stat-value">{number(projects.length)}</strong>
          <span className="muted small">Across production and staging</span>
        </div>
        <div className="stat-card">
          <span className="stat-label">Live</span>
          <strong className="stat-value">{number(live)}</strong>
          <span className="muted small">Serving customers right now</span>
        </div>
        <div className="stat-card">
          <span className="stat-label">Deployments</span>
          <strong className="stat-value">{number(deploys)}</strong>
          <span className="muted small">Recorded in the audit trail</span>
        </div>
      </div>

      {loading && !data ? (
        <Spinner label="Loading projects…" />
      ) : error ? (
        <ErrorState message={error} onRetry={refresh} />
      ) : projects.length === 0 ? (
        <Card>
          <EmptyState
            icon="layers"
            title="No projects registered"
            description="Add the first Woodex integration to start tracking deployments and API keys."
            action={canWrite ? <Button variant="primary" icon="plus" onClick={() => setCreating(true)}>New project</Button> : null}
          />
        </Card>
      ) : (
        <div className="project-grid">
          {projects.map((project) => (
            <Link key={project.id} to={`/projects/${project.id}`} className="card project-card">
              <div className="project-head">
                <div>
                  <h2 className="project-name">{project.name}</h2>
                  <p className="project-code">{project.code}</p>
                </div>
                <StatusBadge value={project.status} />
              </div>

              <p className="project-client">
                {project.client ?? 'Internal'} · {project.region ?? 'No region'}
              </p>

              <div className="project-stats">
                <div className="project-stat">
                  <strong>{project.deployment_count}</strong>
                  <span>Deploys</span>
                </div>
                <div className="project-stat">
                  <strong>{project.key_count}</strong>
                  <span>API keys</span>
                </div>
                <div className="project-stat">
                  <strong>{project.progress}%</strong>
                  <span>Rollout</span>
                </div>
              </div>

              <div>
                <ProgressBar
                  value={project.progress}
                  tone={project.status === 'live' ? 'success' : project.status === 'paused' ? 'warning' : 'accent'}
                />
                <div className="project-foot">
                  <span className="muted small">
                    <Badge tone={statusMeta(project.environment).tone}>{statusMeta(project.environment).label}</Badge>
                  </span>
                  <span className="muted small">
                    {project.last_deploy_at ? `Deployed ${relativeTime(project.last_deploy_at)}` : 'No deploys yet'}
                  </span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}

      <Modal
        open={creating}
        title="Register a project"
        subtitle="Give this integration a code, an environment and a rollout target."
        onClose={() => setCreating(false)}
        footer={
          <>
            <Button onClick={() => setCreating(false)} disabled={saving}>
              Cancel
            </Button>
            <Button variant="primary" onClick={save} loading={saving}>
              Create project
            </Button>
          </>
        }
      >
        <form className="form-grid" onSubmit={save}>
          <Field label="Project name" required className="span-2">
            <Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Woodex Retail OS" required />
          </Field>
          <Field label="Project code" required hint="Uppercase identifier used by the API.">
            <Input value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value })} placeholder="WOODEX-RETAIL" required />
          </Field>
          <Field label="Client">
            <Input value={form.client} onChange={(event) => setForm({ ...form, client: event.target.value })} placeholder="Woodex HQ" />
          </Field>
          <Field label="Environment">
            <Select value={form.environment} onChange={(event) => setForm({ ...form, environment: event.target.value })} options={ENVIRONMENTS} />
          </Field>
          <Field label="Status">
            <Select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} options={PROJECT_STATUSES} />
          </Field>
          <Field label="API base URL">
            <Input value={form.apiBase} onChange={(event) => setForm({ ...form, apiBase: event.target.value })} placeholder="https://api.example.pk/v1" />
          </Field>
          <Field label="Region">
            <Input value={form.region} onChange={(event) => setForm({ ...form, region: event.target.value })} />
          </Field>
          <Field label="Rollout progress (%)">
            <Input type="number" min="0" max="100" value={form.progress} onChange={(event) => setForm({ ...form, progress: event.target.value })} />
          </Field>
          <Field label="Description" className="span-2">
            <Textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="What does this integration do?" />
          </Field>
          <button type="submit" hidden />
        </form>
      </Modal>
    </>
  );
}
