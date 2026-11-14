# Woodex AOS — Operations, Commerce & Deployment Platform

A complete, working full-stack admin system for **Woodex Furniture**: catalogue and stock,
order book, trade customers, multi-project integrations, deployment history, API keys,
team roles and a full audit trail — running on one server with one command.

> **Background:** this repository was created from the intent described in
> `marketingwoodex-cloud/reimagin-os` (“creative Laravel-based admin with a master plan for
> different modules, APIs, database, multi-project integration and deployment”). Both source
> repositories were empty, and PHP/Composer are unavailable in this environment, so the same
> architecture is implemented on a portable stack: **Node 22 + Express + SQLite + React**.
> The module boundaries, role model and API surface are designed to map 1:1 onto a Laravel
> service later — see [Porting notes](#porting-notes).

---

## Quick start

```bash
# 1. Install server + client dependencies, reset the database, build the UI
npm run setup

# 2. Start the app (API + built client on one port)
npm start
```

Open **http://localhost:3000** and sign in with a demo account below.

For development with hot reload (Vite on :5173 proxying `/api` to :3000):

```bash
npm run dev
```

| Command | What it does |
| --- | --- |
| `npm run setup` | Installs `server/` and `client/` deps, seeds the DB, builds the client |
| `npm start` | Production-style run: Express serves the API **and** `client/dist` |
| `npm run dev` | API (watch mode) + Vite dev server together |
| `npm run build` | Rebuilds the React client into `client/dist` |
| `npm run db:reset` | Wipes and reseeds the SQLite database |
| `npm test` | Full API integration suite (18 tests) against a throwaway DB |
| `npm run test:ui` | UI smoke suite (28 checks) — needs `npm start` running in another shell |

Requirements: **Node.js ≥ 22.5** (uses the built-in `node:sqlite` driver — no native build step,
no external database server).

---

## Demo accounts (seeded and tested)

| Role | Email | Password | Can do |
| --- | --- | --- | --- |
| Administrator | `admin@woodex.pk` | `Woodex@2026` | Everything, incl. team, settings, projects |
| Manager | `manager@woodex.pk` | `Manager@2026` | Create/edit commerce data + deployments |
| Viewer | `viewer@woodex.pk` | `Viewer@2026` | Read-only access to all modules |

The login screen also lists these accounts — click one to fill the form.
Passwords are hashed with bcrypt; sessions are httpOnly signed cookies valid for 7 days.

---

## Modules

| Module | What it covers |
| --- | --- |
| **Dashboard** | Revenue trend (6 months), order pipeline donut, best sellers, low-stock alerts, project health, live activity feed |
| **Products** | 30-piece furniture catalogue, search/filter by category & status, low-stock filter, create/edit/delete, cost & margin fields |
| **Orders** | Order book with status pipeline, channel filters, detail drawer with line items and totals, status transitions, new-order builder with live totals |
| **Customers** | Retail / trade / wholesale / hospitality accounts, lifetime value and order counts, CRUD |
| **Projects** | Multi-project registry (env, region, API base, rollout %) with deployment history and API key issuance/revocation |
| **Activity** | Searchable audit trail of every mutation, filterable by entity |
| **Team & access** | User management, three roles, suspension, password resets (admin only) |
| **Settings** | Organisation profile, currency/tax/low-stock defaults, integrations with connect/disable |

Every mutation is written to the audit trail with the acting user, entity and timestamp.

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│  Browser — React 19 SPA (Vite build)                             │
│  routes: /login /dashboard /products /orders /customers           │
│          /projects/:id /users /activity /settings /account       │
└───────────────────────────┬──────────────────────────────────────┘
                            │  fetch(credentials: 'include')
                            │  same origin · httpOnly session cookie
┌───────────────────────────▼──────────────────────────────────────┐
│  Express API (server/src)                                        │
│  /api/auth · /api/dashboard · /api/products · /api/orders        │
│  /api/customers · /api/projects · /api/users · /api/activity     │
│  /api/settings · /api/health                                     │
│  middleware: CORS → cookies → attachUser → requireAuth/Role      │
│  also serves client/dist with SPA fallback (single port)         │
└───────────────────────────┬──────────────────────────────────────┘
                            │
┌───────────────────────────▼──────────────────────────────────────┐
│  SQLite (node:sqlite, WAL)  server/data/woodex.db                │
│  users · categories · products · customers · orders · order_items │
│  projects · deployments · api_keys · integrations · activity      │
│  settings                                                         │
└──────────────────────────────────────────────────────────────────┘
```

**Why one port:** the production server serves the built SPA and the API from the same origin, so
cookies, CORS and preview proxies all behave predictably. In development, Vite proxies `/api`,
keeping the browser on a single origin too.

---

## Project structure

```
woodex-aos/
├── package.json                 # workspace scripts (setup / dev / build / start / test)
├── .env.example                 # documented configuration
├── scripts/
│   └── dev.mjs                  # runs API + Vite together
├── server/
│   ├── package.json
│   ├── data/                    # SQLite database (git-ignored)
│   ├── tests/
│   │   └── api.test.js          # 18 integration tests (node:test)
│   └── src/
│       ├── index.js             # entrypoint: env → seed → listen
│       ├── app.js               # Express app, middleware, SPA fallback, errors
│       ├── db/
│       │   ├── schema.sql       # idempotent schema, 13 tables
│       │   ├── index.js         # driver wrapper (all / get / run / tx)
│       │   └── seed.js          # deterministic demo data + CLI (--reset)
│       ├── lib/
│       │   ├── auth.js          # bcrypt, JWT cookies, requireAuth/Role/Write
│       │   ├── http.js          # ApiError, asyncHandler, pagination helpers
│       │   ├── activity.js      # audit writer
│       │   └── env.js           # zero-dependency .env loader
│       └── routes/
│           ├── auth.js  dashboard.js  products.js  orders.js
│           ├── customers.js  projects.js  users.js  activity.js  settings.js
└── client/
    ├── vite.config.js
    ├── index.html
    └── src/
        ├── main.jsx  App.jsx  styles.css
        ├── lib/       api.js  format.js  hooks.js
        ├── state/     AuthContext.jsx  ToastContext.jsx
        ├── components/ AppShell.jsx  ui.jsx  charts.jsx  icons.jsx
        └── pages/     Login  Dashboard  Products  Orders  Customers
                        Projects  ProjectDetail  Users  Activity  Settings
                        Account  NotFound
```

---

## API reference

All endpoints are prefixed with `/api` and require a session cookie except `health` and `login`.
`W` = admin or manager; `A` = admin only.

### Auth
| Method | Path | Access | Description |
| --- | --- | --- | --- |
| GET | `/health` | public | Liveness probe |
| POST | `/auth/login` | public | `{ email, password }` → user + token, sets cookie |
| POST | `/auth/logout` | any | Clears the session cookie |
| GET | `/auth/me` | any | Current user or `null` |
| POST | `/auth/password` | any | `{ currentPassword, newPassword }` |

### Dashboard
| Method | Path | Access | Description |
| --- | --- | --- | --- |
| GET | `/dashboard` | any | KPIs, 6-month revenue series, pipeline, best sellers, low stock, recent orders/activity, project health |

### Catalogue
| Method | Path | Access | Description |
| --- | --- | --- | --- |
| GET | `/products?search=&category=&status=&stock=low&page=&limit=` | any | Paginated catalogue with category names |
| GET | `/products/:id` | any | Single product |
| POST | `/products` | W | Create (unique SKU enforced → 409) |
| PATCH | `/products/:id` | W | Partial update |
| DELETE | `/products/:id` | W | Delete |
| GET | `/products/categories` | any | Categories with product counts |
| POST | `/products/categories` | W | Create category |

### Orders
| Method | Path | Access | Description |
| --- | --- | --- | --- |
| GET | `/orders?status=&channel=&search=&page=` | any | Paginated order book + value summary |
| GET | `/orders/:id` | any | Order with customer and line items |
| POST | `/orders` | W | Create from `{ customerId, channel, items:[{productId, qty}], taxRate }`; decrements stock atomically |
| PATCH | `/orders/:id` | W | Update status/channel/notes |

### Customers
| Method | Path | Access | Description |
| --- | --- | --- | --- |
| GET | `/customers?search=&type=&page=` | any | Paginated with order count + lifetime value |
| GET | `/customers/:id` | any | Customer with their orders |
| POST / PATCH / DELETE | `/customers` `/customers/:id` | W | CRUD |

### Projects, deployments & API keys
| Method | Path | Access | Description |
| --- | --- | --- | --- |
| GET | `/projects` | any | Registry with deploy/key counts |
| GET | `/projects/:id` | any | Detail incl. deployments, keys, stats |
| POST | `/projects` | W | Register project (unique code) |
| PATCH | `/projects/:id` | W | Update |
| DELETE | `/projects/:id` | A | Delete (cascades) |
| POST | `/projects/:id/deployments` | W | Record a release, flips project to live |
| PATCH | `/projects/:id/deployments/:deploymentId` | W | Change deployment status |
| POST | `/projects/:id/keys` | W | Issue key — plaintext secret returned **once** |
| DELETE | `/projects/:id/keys/:keyId` | W | Revoke key |

### Workspace
| Method | Path | Access | Description |
| --- | --- | --- | --- |
| GET | `/users` | A | List team |
| POST | `/users` | A | Invite (`password` ≥ 8 chars) |
| PATCH | `/users/:id` | A | Update role/title/status/password |
| DELETE | `/users/:id` | A | Remove (cannot delete self or last admin) |
| GET | `/activity?entity=&search=&limit=` | any | Audit trail |
| GET | `/settings` | any | Settings + integrations |
| PATCH | `/settings` | A | Update editable keys |
| PATCH | `/settings/integrations/:id` | A | Connect / disable / flag |

Errors always return `{ "error": "human readable message" }` with 400/401/403/404/409/500.

---

## Roles & permissions

| Capability | Admin | Manager | Viewer |
| --- | :---: | :---: | :---: |
| View dashboard, catalogue, orders, customers, projects, activity | ✅ | ✅ | ✅ |
| Create/edit products, customers, orders | ✅ | ✅ | — |
| Create orders, trigger deployments, issue/revoke API keys | ✅ | ✅ | — |
| Edit workspace settings & integrations | ✅ | — | — |
| Manage team members, delete projects | ✅ | — | — |

The UI hides unavailable actions *and* the API enforces the same matrix — never one without
the other.

---

## Database

Thirteen tables, seeded with realistic Woodex data: 30 catalogue pieces across 8 categories,
12 customers, 34 orders spread over six months (with line items), 5 projects, 9 deployments,
4 API keys, 6 integrations and an activity trail.

```
users ──┐
categories ──< products ──< order_items >── orders ──> customers
projects ──< deployments
        └──< api_keys
activity (audit log)      integrations      settings
```

* Passwords: bcrypt (cost 10). API keys: SHA-256 hashed, only the prefix is listed.
* Orders are created inside a transaction (order + items + stock decrement).
* Schema is applied idempotently at boot; `.sql` file is the single source of truth.
* Location: `server/data/woodex.db` (WAL mode). Deleting it re-seeds on next start.

---

## Testing

Two suites, 46 checks in total.

```bash
npm test          # API — 18 integration tests, throwaway database
npm start &       # then, in another shell:
npm run test:ui   # UI — 28 checks against the running app
```

Boots the real Express app against a temporary database and covers 18 cases:
health, unauthenticated access, wrong/missing credentials, login/logout/session, product
pagination/filtering/CRUD, duplicate SKU conflict, order creation with stock decrement,
order detail and status change, dashboard payload shape, project deployment, API key
issue + revoke, viewer write-blocking, manager admin-blocking, self-deletion guard, and the
audit trail.

```
# tests 18   # pass 18   # fail 0
```

The UI suite bundles the real React app with esbuild, mounts it in jsdom and verifies:
the login form (including the rejected-password path), every route rendering with live data
from the API, the new-order modal, the theme toggle, and role-based visibility (viewers have no
create/delete controls and no Team nav; managers can write but cannot manage the team).

```
Login page        ✓ renders the sign-in form   ✓ lists the demo accounts
Form login        ✓ stores the session cookie  ✓ lands on the dashboard
Routes (admin)    ✓ 10/10 routes render        ✓ new-order modal   ✓ theme toggle
Role visibility   ✓ viewer read-only           ✓ manager can write  ✓ nav filtered
28/28 UI checks passed
```

Manual smoke test of the running server:

```bash
curl -s localhost:3000/api/health
curl -s -c jar -X POST localhost:3000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@woodex.pk","password":"Woodex@2026"}'
curl -s -b jar localhost:3000/api/dashboard | head -c 400
```

---

## Configuration

Copy `.env.example` to `.env` (loaded by the server, no dependency required):

| Variable | Default | Purpose |
| --- | --- | --- |
| `PORT` | `3000` | HTTP port |
| `HOST` | `0.0.0.0` | Bind address |
| `JWT_SECRET` | dev fallback | **Change in production** |
| `DB_PATH` / `DATA_DIR` | `server/data/woodex.db` | Database location |
| `AUTO_SEED` | `true` | Seed demo data when the DB is empty |

---

## Deployment notes

* **Single service:** build the client (`npm run build`), then run `npm start` behind any
  Node host or container. The server binds `0.0.0.0` and serves everything from one port.
* **Health check:** `GET /api/health`.
* **Persistence:** mount a volume for `server/data` (or point `DB_PATH` at one).
* **Vercel:** suitable for the static client and as a preview, but serverless functions have an
  ephemeral filesystem — SQLite writes need a volume-backed host (Railway/Fly/render/VPS), or
  swap the data layer for Postgres. The data layer is isolated in `server/src/db/` to make that
  swap small.
* **Secrets:** set `JWT_SECRET` in the host's environment, not in git.
* Set `AUTO_SEED=false` for a clean production database, then create your first admin manually
  (or run `npm run db:seed` once).

---

## Porting notes

The Laravel intent from `reimagin-os` maps cleanly:

| Here | Laravel equivalent |
| --- | --- |
| `server/src/routes/*.js` | `routes/api.php` + controllers |
| `server/src/lib/auth.js` | Sanctum / JWT guards + policies |
| `server/src/db/schema.sql` | migrations |
| `server/src/lib/activity.js` | model observers / events |
| `client/src/pages/*` | Blade + Livewire or Inertia pages |

The API contract (paths, payloads, status codes) is framework-agnostic, so the React client can
be pointed at a Laravel implementation without changes.

## What is real vs demo

* **Real:** authentication, RBAC, all CRUD, order totals and stock movement, pagination,
  filtering, audit logging, API key hashing, deployment records.
* **Simulated:** deployment executions write a release record rather than shipping artefacts;
  integrations toggle their status but hold placeholder credentials; payments and courier
  bookings are represented by data, not third-party calls.

---

Built for Woodex Furniture · Lahore · demo data resets with `npm run db:reset`.
