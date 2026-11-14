/** Presentation helpers: currency, dates, statuses, initials. */

const currencyFormatters = new Map();

function formatter(currency, compact = false) {
  const key = `${currency}:${compact}`;
  if (!currencyFormatters.has(key)) {
    currencyFormatters.set(
      key,
      new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: currency || 'PKR',
        currencyDisplay: 'narrowSymbol',
        maximumFractionDigits: 0,
        notation: compact ? 'compact' : 'standard',
      }),
    );
  }
  return currencyFormatters.get(key);
}

export function money(value, currency = 'PKR', compact = false) {
  const amount = Number(value) || 0;
  try {
    return formatter(currency, compact).format(amount);
  } catch {
    return `${currency} ${Math.round(amount).toLocaleString('en-US')}`;
  }
}

export function number(value) {
  return (Number(value) || 0).toLocaleString('en-US');
}

export function formatDate(iso, options = {}) {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', ...options });
}

export function formatDateTime(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '—';
  return `${formatDate(iso)} · ${date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
}

export function relativeTime(iso) {
  if (!iso) return '—';
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return '—';
  const seconds = Math.round((Date.now() - then) / 1000);
  if (seconds < 60) return 'just now';
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  if (days < 30) return `${days}d ago`;
  const months = Math.round(days / 30);
  if (months < 12) return `${months}mo ago`;
  return `${Math.round(months / 12)}y ago`;
}

export function initials(name = '') {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('');
}

export function titleCase(value = '') {
  return String(value).replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

const STATUS_META = {
  // orders
  pending: { label: 'Pending', tone: 'warning' },
  confirmed: { label: 'Confirmed', tone: 'info' },
  in_production: { label: 'In production', tone: 'accent' },
  shipped: { label: 'Shipped', tone: 'info' },
  delivered: { label: 'Delivered', tone: 'success' },
  cancelled: { label: 'Cancelled', tone: 'danger' },
  // products / projects / deployments
  active: { label: 'Active', tone: 'success' },
  draft: { label: 'Draft', tone: 'neutral' },
  archived: { label: 'Archived', tone: 'neutral' },
  live: { label: 'Live', tone: 'success' },
  staging: { label: 'Staging', tone: 'info' },
  building: { label: 'Building', tone: 'accent' },
  paused: { label: 'Paused', tone: 'neutral' },
  success: { label: 'Success', tone: 'success' },
  failed: { label: 'Failed', tone: 'danger' },
  running: { label: 'Running', tone: 'info' },
  rolled_back: { label: 'Rolled back', tone: 'warning' },
  connected: { label: 'Connected', tone: 'success' },
  error: { label: 'Needs attention', tone: 'danger' },
  disabled: { label: 'Disabled', tone: 'neutral' },
  revoked: { label: 'Revoked', tone: 'danger' },
  suspended: { label: 'Suspended', tone: 'danger' },
  inactive: { label: 'Inactive', tone: 'neutral' },
  // roles
  admin: { label: 'Administrator', tone: 'accent' },
  manager: { label: 'Manager', tone: 'info' },
  viewer: { label: 'Viewer', tone: 'neutral' },
  // channels
  showroom: { label: 'Showroom', tone: 'neutral' },
  online: { label: 'Online', tone: 'info' },
  trade: { label: 'Trade', tone: 'accent' },
  phone: { label: 'Phone', tone: 'neutral' },
  // customer types
  retail: { label: 'Retail', tone: 'neutral' },
  wholesale: { label: 'Wholesale', tone: 'info' },
  hospitality: { label: 'Hospitality', tone: 'accent' },
  production: { label: 'Production', tone: 'success' },
  development: { label: 'Development', tone: 'neutral' },
};

export function statusMeta(value) {
  return STATUS_META[value] || { label: titleCase(value || '—'), tone: 'neutral' };
}

export const ORDER_STATUSES = ['pending', 'confirmed', 'in_production', 'shipped', 'delivered', 'cancelled'];
export const CHANNELS = ['showroom', 'online', 'trade', 'phone'];
export const CUSTOMER_TYPES = ['retail', 'trade', 'wholesale', 'hospitality'];
export const ROLES = ['admin', 'manager', 'viewer'];
export const ENVIRONMENTS = ['production', 'staging', 'development'];
export const PROJECT_STATUSES = ['live', 'staging', 'building', 'paused'];
