/**
 * Database layer — SQLite via the built-in `node:sqlite` driver.
 * Exposes small helpers so routes never touch driver internals.
 */
import { DatabaseSync } from 'node:sqlite';
import { mkdirSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

export const DATA_DIR = process.env.DATA_DIR
  ? resolve(process.env.DATA_DIR)
  : join(__dirname, '..', '..', 'data');

export const DB_PATH = process.env.DB_PATH
  ? resolve(process.env.DB_PATH)
  : join(DATA_DIR, 'woodex.db');

mkdirSync(DATA_DIR, { recursive: true });

const db = new DatabaseSync(DB_PATH);
db.exec('PRAGMA journal_mode = WAL;');
db.exec('PRAGMA foreign_keys = ON;');
db.exec(readFileSync(join(__dirname, 'schema.sql'), 'utf8'));

/** Run a SELECT and return every row as a plain object. */
export function all(sql, params = []) {
  return db.prepare(sql).all(...params).map((row) => ({ ...row }));
}

/** Run a SELECT and return the first row (or null). */
export function get(sql, params = []) {
  const row = db.prepare(sql).get(...params);
  return row ? { ...row } : null;
}

/** Run an INSERT/UPDATE/DELETE. Returns { changes, lastInsertRowid }. */
export function run(sql, params = []) {
  return db.prepare(sql).run(...params);
}

/** Wrap a function in a transaction. */
export function tx(fn) {
  db.exec('BEGIN');
  try {
    const result = fn();
    db.exec('COMMIT');
    return result;
  } catch (error) {
    db.exec('ROLLBACK');
    throw error;
  }
}

export function count(table) {
  return get(`SELECT COUNT(*) AS c FROM ${table}`).c;
}

export default db;
