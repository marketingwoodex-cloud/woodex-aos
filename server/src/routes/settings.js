import { Router } from 'express';
import { all, get, run } from '../db/index.js';
import { asyncHandler, toInt } from '../lib/http.js';
import { requireAdmin } from '../lib/auth.js';
import { logFromRequest } from '../lib/activity.js';

const router = Router();

const EDITABLE_KEYS = [
  'org_name',
  'currency',
  'tax_rate',
  'support_email',
  'phone',
  'address',
  'timezone',
  'low_stock_threshold',
  'brand_color',
];

router.get(
  '/',
  asyncHandler(async (_req, res) => {
    const rows = all('SELECT key, value FROM settings');
    const settings = Object.fromEntries(rows.map((row) => [row.key, row.value]));
    const integrations = all('SELECT * FROM integrations ORDER BY name').map((row) => ({
      ...row,
      config: safeJson(row.config_json),
    }));
    res.json({ data: { settings, integrations } });
  }),
);

router.patch(
  '/',
  requireAdmin,
  asyncHandler(async (req, res) => {
    const body = req.body ?? {};
    const updates = Object.entries(body).filter(([key]) => EDITABLE_KEYS.includes(key));
    for (const [key, value] of updates) {
      const existing = get('SELECT key FROM settings WHERE key = ?', [key]);
      if (existing) run('UPDATE settings SET value = ? WHERE key = ?', [String(value), key]);
      else run('INSERT INTO settings (key, value) VALUES (?, ?)', [key, String(value)]);
    }
    if (updates.length) {
      logFromRequest(req, {
        action: 'update',
        entity: 'settings',
        entityId: null,
        summary: `Updated workspace settings (${updates.map(([key]) => key).join(', ')})`,
      });
    }
    const rows = all('SELECT key, value FROM settings');
    res.json({ data: Object.fromEntries(rows.map((row) => [row.key, row.value])) });
  }),
);

router.patch(
  '/integrations/:id',
  requireAdmin,
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM integrations WHERE id = ?', [id]);
    if (!existing) return res.status(404).json({ error: 'Integration not found.' });
    const status = ['connected', 'error', 'disabled'].includes(req.body?.status) ? req.body.status : existing.status;
    run('UPDATE integrations SET status = ?, updated_at = ? WHERE id = ?', [status, new Date().toISOString(), id]);
    logFromRequest(req, {
      action: 'update',
      entity: 'integration',
      entityId: id,
      summary: `${status === 'connected' ? 'Connected' : status === 'disabled' ? 'Disabled' : 'Flagged'} ${existing.name} integration`,
    });
    const updated = get('SELECT * FROM integrations WHERE id = ?', [id]);
    res.json({ data: { ...updated, config: safeJson(updated.config_json) } });
  }),
);

function safeJson(value) {
  try {
    return JSON.parse(value || '{}');
  } catch {
    return {};
  }
}

export default router;
