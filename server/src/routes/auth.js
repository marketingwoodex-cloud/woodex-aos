import { Router } from 'express';
import { get, run } from '../db/index.js';
import { ApiError, asyncHandler, requireFields, nowIso } from '../lib/http.js';
import {
  hashPassword,
  verifyPassword,
  signToken,
  setSessionCookie,
  clearSessionCookie,
  publicUser,
  requireAuth,
} from '../lib/auth.js';
import { logActivity, logFromRequest } from '../lib/activity.js';

const router = Router();

router.post(
  '/login',
  asyncHandler(async (req, res) => {
    const { email, password } = req.body ?? {};
    requireFields(req.body ?? {}, ['email', 'password']);

    const user = get('SELECT * FROM users WHERE lower(email) = lower(?)', [String(email).trim()]);
    if (!user || !verifyPassword(password, user.password_hash)) {
      throw new ApiError(401, 'Incorrect email or password.');
    }
    if (user.status !== 'active') {
      throw new ApiError(403, 'This account is suspended. Contact an administrator.');
    }

    const token = signToken(user);
    run('UPDATE users SET last_login_at = ? WHERE id = ?', [new Date().toISOString(), user.id]);
    setSessionCookie(res, token);
    logActivity({
      userId: user.id,
      userName: user.name,
      action: 'login',
      entity: 'session',
      entityId: user.id,
      summary: `${user.name} signed in`,
    });

    res.json({ user: publicUser(user), token });
  }),
);

router.post('/logout', (req, res) => {
  if (req.user) logFromRequest(req, { action: 'logout', entity: 'session', entityId: req.user.id, summary: `${req.user.name} signed out` });
  clearSessionCookie(res);
  res.json({ ok: true });
});

router.get('/me', (req, res) => {
  res.json({ user: publicUser(req.user) });
});

router.post(
  '/password',
  requireAuth,
  asyncHandler(async (req, res) => {
    const { currentPassword, newPassword } = req.body ?? {};
    requireFields(req.body ?? {}, ['currentPassword', 'newPassword']);
    if (String(newPassword).length < 8) throw new ApiError(400, 'New password must be at least 8 characters.');
    if (!verifyPassword(currentPassword, req.user.password_hash)) {
      throw new ApiError(400, 'Current password is incorrect.');
    }
    run('UPDATE users SET password_hash = ? WHERE id = ?', [hashPassword(newPassword), req.user.id]);
    logFromRequest(req, { action: 'update', entity: 'user', entityId: req.user.id, summary: `${req.user.name} changed their password` });
    res.json({ ok: true, message: 'Password updated.' });
  }),
);

export default router;
