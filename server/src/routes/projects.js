import { Router } from 'express';
import { createHash, randomBytes } from 'node:crypto';
import { all, get, run, tx } from '../db/index.js';
import { ApiError, asyncHandler, requireFields, toInt, nowIso } from '../lib/http.js';
import { requireWrite, requireAdmin } from '../lib/auth.js';
import { logFromRequest } from '../lib/activity.js';

const router = Router();

const ENVIRONMENTS = ['production', 'staging', 'development'];
const STATUSES = ['live', 'staging', 'building', 'paused'];

function projectDetail(id) {
  const project = get('SELECT * FROM projects WHERE id = ?', [id]);
  if (!project) throw new ApiError(404, 'Project not found.');
  project.deployments = all(
    'SELECT * FROM deployments WHERE project_id = ? ORDER BY created_at DESC LIMIT 25',
    [id],
  );
  project.apiKeys = all(
    'SELECT id, label, prefix, scopes, status, last_used_at, created_at FROM api_keys WHERE project_id = ? ORDER BY created_at DESC',
    [id],
  );
  project.stats = get(
    `SELECT
       (SELECT COUNT(*) FROM deployments WHERE project_id = ?) AS deployments,
       (SELECT COUNT(*) FROM deployments WHERE project_id = ? AND status = 'success') AS successful,
       (SELECT COUNT(*) FROM api_keys WHERE project_id = ? AND status = 'active') AS active_keys`,
    [id, id, id],
  );
  return project;
}

router.get(
  '/',
  asyncHandler(async (_req, res) => {
    const rows = all(`
      SELECT p.*,
             (SELECT COUNT(*) FROM deployments d WHERE d.project_id = p.id) AS deployment_count,
             (SELECT COUNT(*) FROM api_keys k WHERE k.project_id = p.id AND k.status = 'active') AS key_count,
             (SELECT MAX(created_at) FROM deployments d WHERE d.project_id = p.id) AS last_deploy_at
        FROM projects p ORDER BY p.created_at DESC
    `);
    res.json({ data: rows });
  }),
);

router.get(
  '/:id',
  asyncHandler(async (req, res) => {
    res.json({ data: projectDetail(toInt(req.params.id)) });
  }),
);

router.post(
  '/',
  requireWrite,
  asyncHandler(async (req, res) => {
    const body = req.body ?? {};
    requireFields(body, ['name', 'code']);
    const code = String(body.code).trim().toUpperCase();
    if (get('SELECT id FROM projects WHERE code = ?', [code])) {
      throw new ApiError(409, 'A project with that code already exists.');
    }
    const stamp = new Date().toISOString();
    const result = run(
      `INSERT INTO projects (name, code, client, description, environment, status, api_base, progress, region, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        String(body.name).trim(), code, body.client ?? null, body.description ?? null,
        ENVIRONMENTS.includes(body.environment) ? body.environment : 'staging',
        STATUSES.includes(body.status) ? body.status : 'building',
        body.apiBase ?? null, Math.min(100, Math.max(0, toInt(body.progress, 0))), body.region ?? null, stamp, stamp,
      ],
    );
    const created = projectDetail(Number(result.lastInsertRowid));
    logFromRequest(req, { action: 'create', entity: 'project', entityId: created.id, summary: `Created project “${created.name}”` });
    res.status(201).json({ data: created });
  }),
);

router.patch(
  '/:id',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM projects WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Project not found.');
    const body = req.body ?? {};
    run(
      `UPDATE projects SET name = ?, client = ?, description = ?, environment = ?, status = ?, api_base = ?, progress = ?, region = ?, updated_at = ?
       WHERE id = ?`,
      [
        body.name ?? existing.name,
        body.client ?? existing.client,
        body.description ?? existing.description,
        ENVIRONMENTS.includes(body.environment) ? body.environment : existing.environment,
        STATUSES.includes(body.status) ? body.status : existing.status,
        body.apiBase ?? existing.api_base,
        body.progress !== undefined ? Math.min(100, Math.max(0, toInt(body.progress))) : existing.progress,
        body.region ?? existing.region,
        new Date().toISOString(),
        id,
      ],
    );
    const updated = projectDetail(id);
    logFromRequest(req, { action: 'update', entity: 'project', entityId: id, summary: `Updated project “${updated.name}”` });
    res.json({ data: updated });
  }),
);

router.delete(
  '/:id',
  requireAdmin,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM projects WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'Project not found.');
    run('DELETE FROM projects WHERE id = ?', [id]);
    logFromRequest(req, { action: 'delete', entity: 'project', entityId: id, summary: `Deleted project “${existing.name}”` });
    res.json({ ok: true });
  }),
);

/* ---------------------------------------------------------------- deployments */

router.post(
  '/:id/deployments',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const project = get('SELECT * FROM projects WHERE id = ?', [id]);
    if (!project) throw new ApiError(404, 'Project not found.');

    const environment = ENVIRONMENTS.includes(req.body?.environment) ? req.body.environment : project.environment;
    const stamp = new Date().toISOString();
    const version = req.body?.version || `v1.${Math.floor(Date.now() / 1000) % 1000}.0`;
    const commit = randomBytes(4).toString('hex');
    const duration = 24000 + Math.floor(Math.random() * 90000);

    const result = tx(() => {
      const insert = run(
        `INSERT INTO deployments (project_id, version, environment, status, commit_sha, duration_ms, notes, created_by, created_at)
         VALUES (?, ?, ?, 'success', ?, ?, ?, ?, ?)`,
        [
          id, version, environment, commit, duration,
          req.body?.notes ?? 'Triggered from Woodex AOS deployment console',
          req.user?.name ?? 'System', stamp,
        ],
      );
      run('UPDATE projects SET status = ?, updated_at = ? WHERE id = ?', ['live', stamp, id]);
      return insert;
    });

    const deployment = get('SELECT * FROM deployments WHERE id = ?', [Number(result.lastInsertRowid)]);
    logFromRequest(req, {
      action: 'deploy',
      entity: 'deployment',
      entityId: deployment.id,
      summary: `Deployed ${version} of “${project.name}” to ${environment}`,
    });
    res.status(201).json({ data: deployment, project: projectDetail(id) });
  }),
);

router.patch(
  '/:id/deployments/:deploymentId',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const deploymentId = toInt(req.params.deploymentId);
    const deployment = get('SELECT * FROM deployments WHERE id = ? AND project_id = ?', [deploymentId, id]);
    if (!deployment) throw new ApiError(404, 'Deployment not found.');
    const status = ['success', 'failed', 'running', 'rolled_back'].includes(req.body?.status) ? req.body.status : deployment.status;
    run('UPDATE deployments SET status = ?, notes = ? WHERE id = ?', [status, req.body?.notes ?? deployment.notes, deploymentId]);
    logFromRequest(req, {
      action: 'update',
      entity: 'deployment',
      entityId: deploymentId,
      summary: `Marked deployment ${deployment.version} as ${status}`,
    });
    res.json({ data: get('SELECT * FROM deployments WHERE id = ?', [deploymentId]) });
  }),
);

/* ------------------------------------------------------------------- api keys */

router.post(
  '/:id/keys',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const project = get('SELECT * FROM projects WHERE id = ?', [id]);
    if (!project) throw new ApiError(404, 'Project not found.');
    requireFields(req.body ?? {}, ['label']);

    const secret = `wk_live_${randomBytes(24).toString('hex')}`;
    const prefix = secret.slice(0, 16);
    const tokenHash = createHash('sha256').update(secret).digest('hex');
    const scopes = Array.isArray(req.body.scopes) && req.body.scopes.length ? req.body.scopes.join(',') : 'read';

    const result = run(
      `INSERT INTO api_keys (project_id, label, prefix, token_hash, scopes, status, created_at)
       VALUES (?, ?, ?, ?, ?, 'active', ?)`,
      [id, String(req.body.label).trim(), prefix, tokenHash, scopes, new Date().toISOString()],
    );

    logFromRequest(req, {
      action: 'create',
      entity: 'api_key',
      entityId: Number(result.lastInsertRowid),
      summary: `Issued API key “${req.body.label}” for ${project.name}`,
    });

    // The plaintext secret is returned exactly once.
    res.status(201).json({
      data: get('SELECT id, label, prefix, scopes, status, last_used_at, created_at FROM api_keys WHERE id = ?', [
        Number(result.lastInsertRowid),
      ]),
      secret,
    });
  }),
);

router.delete(
  '/:id/keys/:keyId',
  requireWrite,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const keyId = toInt(req.params.keyId);
    const key = get('SELECT * FROM api_keys WHERE id = ? AND project_id = ?', [keyId, id]);
    if (!key) throw new ApiError(404, 'API key not found.');
    run("UPDATE api_keys SET status = 'revoked' WHERE id = ?", [keyId]);
    logFromRequest(req, { action: 'revoke', entity: 'api_key', entityId: keyId, summary: `Revoked API key “${key.label}”` });
    res.json({ ok: true });
  }),
);

export default router;
