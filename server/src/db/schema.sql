-- Woodex AOS database schema (SQLite)
-- Every statement is idempotent so the schema can be applied on each boot.

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT    NOT NULL,
  email         TEXT    NOT NULL,
  password_hash TEXT    NOT NULL,
  role          TEXT    NOT NULL DEFAULT 'viewer' CHECK (role IN ('admin','manager','viewer')),
  title         TEXT,
  phone         TEXT,
  accent        TEXT,
  status        TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended')),
  last_login_at TEXT,
  created_at    TEXT    NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users(lower(email));

CREATE TABLE IF NOT EXISTS categories (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT    NOT NULL UNIQUE,
  slug        TEXT    NOT NULL UNIQUE,
  description TEXT,
  created_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS products (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  sku           TEXT    NOT NULL UNIQUE,
  name          TEXT    NOT NULL,
  category_id   INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  description   TEXT,
  material      TEXT,
  finish        TEXT,
  price         REAL    NOT NULL DEFAULT 0,
  cost          REAL    NOT NULL DEFAULT 0,
  stock         INTEGER NOT NULL DEFAULT 0,
  reorder_level INTEGER NOT NULL DEFAULT 5,
  status        TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','draft','archived')),
  created_at    TEXT    NOT NULL,
  updated_at    TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);

CREATE TABLE IF NOT EXISTS customers (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT    NOT NULL,
  email      TEXT,
  phone      TEXT,
  company    TEXT,
  city       TEXT,
  country    TEXT    NOT NULL DEFAULT 'Pakistan',
  type       TEXT    NOT NULL DEFAULT 'retail' CHECK (type IN ('retail','trade','wholesale','hospitality')),
  status     TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
  notes      TEXT,
  created_at TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  order_no    TEXT    NOT NULL UNIQUE,
  customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
  status      TEXT    NOT NULL DEFAULT 'pending'
              CHECK (status IN ('pending','confirmed','in_production','shipped','delivered','cancelled')),
  channel     TEXT    NOT NULL DEFAULT 'showroom' CHECK (channel IN ('showroom','online','trade','phone')),
  subtotal    REAL    NOT NULL DEFAULT 0,
  tax         REAL    NOT NULL DEFAULT 0,
  shipping    REAL    NOT NULL DEFAULT 0,
  total       REAL    NOT NULL DEFAULT 0,
  currency    TEXT    NOT NULL DEFAULT 'PKR',
  notes       TEXT,
  placed_at   TEXT    NOT NULL,
  created_at  TEXT    NOT NULL,
  updated_at  TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_placed ON orders(placed_at);

CREATE TABLE IF NOT EXISTS order_items (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id   INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
  name       TEXT    NOT NULL,
  sku        TEXT,
  qty        INTEGER NOT NULL DEFAULT 1,
  unit_price REAL    NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);

CREATE TABLE IF NOT EXISTS projects (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT    NOT NULL,
  code        TEXT    NOT NULL UNIQUE,
  client      TEXT,
  description TEXT,
  environment TEXT    NOT NULL DEFAULT 'staging' CHECK (environment IN ('production','staging','development')),
  status      TEXT    NOT NULL DEFAULT 'building' CHECK (status IN ('live','staging','building','paused')),
  api_base    TEXT,
  progress    INTEGER NOT NULL DEFAULT 0,
  region      TEXT,
  created_at  TEXT    NOT NULL,
  updated_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS deployments (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id  INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  version     TEXT    NOT NULL,
  environment TEXT    NOT NULL DEFAULT 'production',
  status      TEXT    NOT NULL DEFAULT 'success' CHECK (status IN ('success','failed','running','rolled_back')),
  commit_sha  TEXT,
  duration_ms INTEGER,
  notes       TEXT,
  created_by  TEXT,
  created_at  TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_deployments_project ON deployments(project_id);

CREATE TABLE IF NOT EXISTS api_keys (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id   INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  label        TEXT    NOT NULL,
  prefix       TEXT    NOT NULL,
  token_hash   TEXT    NOT NULL,
  scopes       TEXT    NOT NULL DEFAULT 'read',
  status       TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','revoked')),
  last_used_at TEXT,
  created_at   TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_api_keys_project ON api_keys(project_id);

CREATE TABLE IF NOT EXISTS integrations (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT    NOT NULL,
  category    TEXT    NOT NULL,
  status      TEXT    NOT NULL DEFAULT 'disabled' CHECK (status IN ('connected','error','disabled')),
  description TEXT,
  config_json TEXT    NOT NULL DEFAULT '{}',
  updated_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS activity (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER,
  user_name  TEXT,
  action     TEXT    NOT NULL,
  entity     TEXT    NOT NULL,
  entity_id  TEXT,
  summary    TEXT    NOT NULL,
  created_at TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_activity_created ON activity(created_at);

CREATE TABLE IF NOT EXISTS settings (
  key   TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
