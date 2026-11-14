/**
 * Express application — API first, then the built single-page client.
 */
import express from 'express';
import cookieParser from 'cookie-parser';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import './db/index.js';
import { attachUser, requireAuth } from './lib/auth.js';
import { ApiError } from './lib/http.js';

import authRoutes from './routes/auth.js';
import dashboardRoutes from './routes/dashboard.js';
import productRoutes from './routes/products.js';
import customerRoutes from './routes/customers.js';
import orderRoutes from './routes/orders.js';
import projectRoutes from './routes/projects.js';
import userRoutes from './routes/users.js';
import activityRoutes from './routes/activity.js';
import settingsRoutes from './routes/settings.js';
import shopRoutes from './routes/shop.js';
import liveRoutes from './routes/live.js';
import { logActivity } from './lib/activity.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const app = express();

app.disable('x-powered-by');
app.use(express.json({ limit: '1mb' }));
app.use(cookieParser());

// Permissive CORS for the dev front-end / preview proxy (cookie auth stays SameSite=Lax).
app.use((req, res, next) => {
  const origin = req.headers.origin;
  if (origin) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Vary', 'Origin');
    res.setHeader('Access-Control-Allow-Credentials', 'true');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
  }
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

app.use(attachUser);

app.get('/api/health', (_req, res) => {
  res.json({ status: 'ok', service: 'woodex-aos-api', time: new Date().toISOString() });
});

app.use('/api/auth', authRoutes);

// Customer-facing storefront — public, no session required.
app.use('/api/shop', shopRoutes);

// Everything below requires a session.
app.use('/api/dashboard', requireAuth, dashboardRoutes);
app.use('/api/products', requireAuth, productRoutes);
app.use('/api/customers', requireAuth, customerRoutes);
app.use('/api/orders', requireAuth, orderRoutes);
app.use('/api/projects', requireAuth, projectRoutes);
app.use('/api/users', requireAuth, userRoutes);
app.use('/api/activity', requireAuth, activityRoutes);
app.use('/api/settings', requireAuth, settingsRoutes);
app.use('/api/live', requireAuth, liveRoutes);

app.use('/api', (_req, res) => {
  res.status(404).json({ error: 'Endpoint not found.' });
});

// Serve the production build of the React client.
const clientDist = join(__dirname, '..', '..', 'client', 'dist');
if (existsSync(join(clientDist, 'index.html'))) {
  app.use(express.static(clientDist, { index: false, maxAge: '1h' }));
  app.get(/^(?!\/api).*/, (_req, res) => {
    res.sendFile(join(clientDist, 'index.html'));
  });
} else {
  app.get('/', (_req, res) => {
    res
      .status(200)
      .type('html')
      .send(`<!doctype html><html><body style="font-family:system-ui;padding:40px;background:#F6F4F1;color:#1B1815">
        <h1>Woodex AOS API is running</h1>
        <p>The client bundle has not been built yet. Run <code>npm run build</code> and reload.</p>
        <p>API health: <a href="/api/health">/api/health</a></p>
      </body></html>`);
  });
}

// Central error handler.
app.use((error, req, res, _next) => {
  if (error instanceof ApiError) {
    return res.status(error.status).json({ error: error.message, details: error.details });
  }
  if (error?.type === 'entity.parse.failed') {
    return res.status(400).json({ error: 'Malformed JSON body.' });
  }
  console.error('[api] unhandled error:', error);
  if (req.user) {
    logActivity({
      userId: req.user.id,
      userName: req.user.name,
      action: 'error',
      entity: 'system',
      entityId: null,
      summary: `Request failed: ${req.method} ${req.originalUrl}`,
    });
  }
  res.status(500).json({ error: 'Something went wrong on our side.' });
});

export default app;
