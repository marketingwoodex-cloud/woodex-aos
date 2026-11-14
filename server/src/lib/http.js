/** Tiny HTTP helpers shared by every route module. */

export class ApiError extends Error {
  constructor(status, message, details = undefined) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

export const asyncHandler = (fn) => (req, res, next) =>
  Promise.resolve(fn(req, res, next)).catch(next);

/** Throw a 400 unless the condition holds. */
export function require_(condition, message) {
  if (!condition) throw new ApiError(400, message);
}

export function requireFields(body, fields) {
  const missing = fields.filter((field) => {
    const value = body?.[field];
    return value === undefined || value === null || String(value).trim() === '';
  });
  if (missing.length) {
    throw new ApiError(400, `Missing required field${missing.length > 1 ? 's' : ''}: ${missing.join(', ')}`);
  }
}

export function toInt(value, fallback = 0) {
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : fallback;
}

export function toNumber(value, fallback = 0) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

/** Read ?page & ?limit with sane bounds. */
export function pagination(req, defaultLimit = 12, maxLimit = 100) {
  const page = Math.max(1, toInt(req.query.page, 1));
  const limit = Math.min(maxLimit, Math.max(1, toInt(req.query.limit, defaultLimit)));
  return { page, limit, offset: (page - 1) * limit };
}

export function listResponse(rows, total, { page, limit }, extra = {}) {
  return {
    data: rows,
    meta: {
      total,
      page,
      limit,
      pages: Math.max(1, Math.ceil(total / limit)),
      ...extra,
    },
  };
}

export const nowIso = () => new Date().toISOString();
