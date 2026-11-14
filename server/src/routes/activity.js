import { Router } from 'express';
import { all, get } from '../db/index.js';
import { asyncHandler, toInt } from '../lib/http.js';

const router = Router();

router.get(
  '/',
  asyncHandler(async (req, res) => {
    const limit = Math.min(200, Math.max(1, toInt(req.query.limit, 50)));
    const filters = [];
    const params = [];
    if (req.query.entity && req.query.entity !== 'all') {
      filters.push('entity = ?');
      params.push(String(req.query.entity));
    }
    if (req.query.search) {
      filters.push('(summary LIKE ? OR user_name LIKE ?)');
      const term = `%${String(req.query.search).trim()}%`;
      params.push(term, term);
    }
    const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
    const rows = all(`SELECT * FROM activity ${where} ORDER BY created_at DESC LIMIT ?`, [...params, limit]);
    const total = get(`SELECT COUNT(*) AS c FROM activity ${where}`, params).c;
    res.json({ data: rows, meta: { total } });
  }),
);

export default router;
