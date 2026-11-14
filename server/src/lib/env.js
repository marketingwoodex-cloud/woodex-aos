/**
 * Zero-dependency .env loader.
 * Imported first by the server entrypoint so configuration is in place before
 * any other module reads process.env.
 */
import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const candidates = [
  resolve(process.cwd(), '.env'),
  join(__dirname, '..', '..', '..', '.env'),
];

for (const file of candidates) {
  let raw;
  try {
    raw = readFileSync(file, 'utf8');
  } catch {
    continue;
  }
  for (const line of raw.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const separator = trimmed.indexOf('=');
    if (separator === -1) continue;
    const key = trimmed.slice(0, separator).trim();
    let value = trimmed.slice(separator + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (key && process.env[key] === undefined) process.env[key] = value;
  }
  break;
}
