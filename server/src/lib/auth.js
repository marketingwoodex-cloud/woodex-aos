/**
 * Authentication: bcrypt password hashing + signed JWT session cookies.
 */
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { get } from '../db/index.js';
import { ApiError } from './http.js';

const SECRET = process.env.JWT_SECRET || 'woodex-aos-local-dev-secret-change-me';
export const COOKIE_NAME = 'woodex_session';
const TOKEN_TTL = '7d';

export const hashPassword = (plain) => bcrypt.hashSync(String(plain), 10);
export const verifyPassword = (plain, hash) => bcrypt.compareSync(String(plain), String(hash));

export function signToken(user) {
  return jwt.sign({ sub: user.id, role: user.role, email: user.email }, SECRET, { expiresIn: TOKEN_TTL });
}

export function setSessionCookie(res, token) {
  res.cookie(COOKIE_NAME, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: false, // the preview proxy terminates TLS in front of the app
    maxAge: 7 * 24 * 60 * 60 * 1000,
    path: '/',
  });
}

export function clearSessionCookie(res) {
  res.clearCookie(COOKIE_NAME, { path: '/' });
}

export function publicUser(user) {
  if (!user) return null;
  const { password_hash: _ignored, ...rest } = user;
  return rest;
}

function readToken(req) {
  const header = req.headers.authorization || '';
  if (header.toLowerCase().startsWith('bearer ')) return header.slice(7).trim();
  return req.cookies?.[COOKIE_NAME] || null;
}

/** Attaches req.user when a valid session is present. */
export function attachUser(req, _res, next) {
  const token = readToken(req);
  if (!token) return next();
  try {
    const payload = jwt.verify(token, SECRET);
    const user = get('SELECT * FROM users WHERE id = ?', [Number(payload.sub)]);
    if (user && user.status === 'active') req.user = user;
  } catch {
    /* invalid or expired token — treated as anonymous */
  }
  next();
}

export function requireAuth(req, _res, next) {
  if (!req.user) return next(new ApiError(401, 'Authentication required. Please sign in.'));
  next();
}

export function requireRole(...roles) {
  return (req, _res, next) => {
    if (!req.user) return next(new ApiError(401, 'Authentication required. Please sign in.'));
    if (!roles.includes(req.user.role)) {
      return next(new ApiError(403, 'Your role does not allow this action.'));
    }
    next();
  };
}

export const requireWrite = requireRole('admin', 'manager');
export const requireAdmin = requireRole('admin');

export const ROLE_LABELS = { admin: 'Administrator', manager: 'Manager', viewer: 'Viewer' };
