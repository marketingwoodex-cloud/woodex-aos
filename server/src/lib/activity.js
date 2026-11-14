import { run } from '../db/index.js';

/** Records an audit entry. Never throws — auditing must not break a request. */
export function logActivity({ userId = null, userName = 'System', action, entity, entityId = null, summary }) {
  try {
    run(
      `INSERT INTO activity (user_id, user_name, action, entity, entity_id, summary, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [userId, userName, action, entity, entityId === null ? null : String(entityId), summary, new Date().toISOString()],
    );
  } catch (error) {
    console.warn('[activity] failed to write entry:', error.message);
  }
}

/** Convenience wrapper bound to the current request's user. */
export function logFromRequest(req, { action, entity, entityId = null, summary }) {
  logActivity({
    userId: req.user?.id ?? null,
    userName: req.user?.name ?? 'System',
    action,
    entity,
    entityId,
    summary,
  });
}

export { nowIso } from './http.js';
