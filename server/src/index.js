/**
 * Woodex AOS API entrypoint.
 */
import './lib/env.js';
import app from './app.js';
import { ensureSeeded } from './db/seed.js';
import { DB_PATH } from './db/index.js';

const PORT = Number(process.env.PORT || 3000);
const HOST = process.env.HOST || '0.0.0.0';

if (process.env.AUTO_SEED !== 'false') {
  ensureSeeded();
}

const server = app.listen(PORT, HOST, () => {
  console.log('');
  console.log('  Woodex AOS — operations platform');
  console.log(`  ▸ API + client   http://${HOST}:${PORT}`);
  console.log(`  ▸ Database       ${DB_PATH}`);
  console.log(`  ▸ Health check   http://${HOST}:${PORT}/api/health`);
  console.log('');
});

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    console.log(`\n[woodex] ${signal} received — shutting down.`);
    server.close(() => process.exit(0));
  });
}
