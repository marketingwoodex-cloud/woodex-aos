import { Router } from 'express';
import { all, get, run } from '../db/index.js';
import { ApiError, asyncHandler, requireFields, toInt } from '../lib/http.js';
import { hashPassword, publicUser, requireAdmin } from '../lib/auth.js';
import { logFromRequest } from '../lib/activity.js';

const router = Router();
router.use(requireAdmin);

const ROLES = ['admin', 'manager', 'viewer'];

router.get(
  '/',
  asyncHandler(async (_req, res) => {
    const rows = all('SELECT * FROM users ORDER BY created_at DESC').map(publicUser);
    res.json({ data: rows });
  }),
);

router.post(
  '/',
  asyncHandler(async (req, res) => {
    const body = req.body ?? {};
    requireFields(body, ['name', 'email', 'password', 'role']);
    if (!ROLES.includes(body.role)) throw new ApiError(400, 'Role must be admin, manager or viewer.');
    if (String(body.password).length < 8) throw new ApiError(400, 'Password must be at least 8 characters.');
    if (get('SELECT id FROM users WHERE lower(email) = lower(?)', [String(body.email).trim()])) {
      throw new ApiError(409, 'A user with that email already exists.');
    }
    const result = run(
      `INSERT INTO users (name, email, password_hash, role, title, phone, accent, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)`,
      [
        String(body.name).trim(), String(body.email).trim().toLowerCase(), hashPassword(body.password),
        body.role, body.title ?? null, body.phone ?? null, body.accent ?? '#B4531F', new Date().toISOString(),
      ],
    );
    const created = publicUser(get('SELECT * FROM users WHERE id = ?', [Number(result.lastInsertRowid)]));
    logFromRequest(req, { action: 'create', entity: 'user', entityId: created.id, summary: `Invited ${created.name} as ${created.role}` });
    res.status(201).json({ data: created });
  }),
);

router.patch(
  '/:id',
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    const existing = get('SELECT * FROM users WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'User not found.');
    const body = req.body ?? {};

    if (id === req.user.id && body.role && body.role !== existing.role) {
      throw new ApiError(400, 'You cannot change your own role.');
    }
    if (body.password !== undefined && body.password !== '' && String(body.password).length < 8) {
      throw new ApiError(400, 'Password must be at least 8 characters.');
    }

    run(
      `UPDATE users SET name = ?, role = ?, title = ?, phone = ?, accent = ?, status = ?, password_hash = ? WHERE id = ?`,
      [
        body.name ?? existing.name,
        ROLES.includes(body.role) ? body.role : existing.role,
        body.title ?? existing.title,
        body.phone ?? existing.phone,
        body.accent ?? existing.accent,
        body.status === 'suspended' ? 'suspended' : body.status === 'active' ? 'active' : existing.status,
        body.password ? hashPassword(body.password) : existing.password_hash,
        id,
      ],
    );
    const updated = publicUser(get('SELECT * FROM users WHERE id = ?', [id]));
    logFromRequest(req, { action: 'update', entity: 'user', entityId: id, summary: `Updated ${updated.name}'s account` });
    res.json({ data: updated });
  }),
);

router.delete(
  '/:id',
  asyncHandler(async (req, res) => {
    const id = toInt(req.params.id);
    if (id === req.user.id) throw new ApiError(400, 'You cannot delete your own account.');
    const existing = get('SELECT * FROM users WHERE id = ?', [id]);
    if (!existing) throw new ApiError(404, 'User not found.');
    const admins = get("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND status = 'active'");
    if (existing.role === 'admin' && admins.c <= 1) throw new ApiError(400, 'At least one administrator must remain.');
    run('DELETE FROM users WHERE id = ?', [id]);
    logFromRequest(req, { action: 'delete', entity: 'user', entityId: id, summary: `Removed ${existing.name}'s account` });
    res.json({ ok: true });
  }),
);

export default router;
