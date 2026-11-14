import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { patch, post } from '../lib/api.js';
import { useDebounced, useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import {
  CHANNELS, ORDER_STATUSES, formatDate, formatDateTime, money, number, relativeTime, statusMeta,
} from '../lib/format.js';
import {
  Badge, Button, Card, Drawer, EmptyState, ErrorState, Field, FilterChips, IconButton, Input,
  Modal, PageHeader, Pagination, SearchInput, Select, Spinner, StatusBadge, Textarea,
} from '../components/ui.jsx';
import Icon from '../components/icons.jsx';

const TAX_RATE = 5;

export default function Orders() {
  const { canWrite } = useAuth();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounced(search);
  const [status, setStatus] = useState('all');
  const [channel, setChannel] = useState('all');
  const [page, setPage] = useState(1);

  const [selectedId, setSelectedId] = useState(null);
  const [creating, setCreating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [updatingStatus, setUpdatingStatus] = useState(false);
  const [draft, setDraft] = useState({ customerId: '', channel: 'showroom', notes: '', lines: [{ productId: '', qty: 1 }] });

  useEffect(() => setPage(1), [debouncedSearch, status, channel]);

  const query = new URLSearchParams({ page: String(page), limit: '10' });
  if (debouncedSearch) query.set('search', debouncedSearch);
  if (status !== 'all') query.set('status', status);
  if (channel !== 'all') query.set('channel', channel);

  const { data, loading, error, refresh } = useFetch(`/orders?${query.toString()}`);
  const detail = useFetch(selectedId ? `/orders/${selectedId}` : null);
  const { data: customerData } = useFetch(creating ? '/customers?limit=100' : null);
  const { data: productData } = useFetch(creating ? '/products?limit=100&status=active' : null);

  const orders = data?.data ?? [];
  const customers = customerData?.data ?? [];
  const products = productData?.data ?? [];

  useEffect(() => {
    if (searchParams.get('new') === '1') {
      setCreating(true);
      searchParams.delete('new');
      setSearchParams(searchParams, { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]);

  const productMap = useMemo(() => new Map(products.map((product) => [String(product.id), product])), [products]);

  const preview = useMemo(() => {
    let subtotal = 0;
    for (const line of draft.lines) {
      const product = productMap.get(String(line.productId));
      if (product) subtotal += product.price * Math.max(1, Number(line.qty) || 1);
    }
    const tax = Math.round(subtotal * (TAX_RATE / 100));
    return { subtotal, tax, total: subtotal + tax };
  }, [draft.lines, productMap]);

  async function createOrder() {
    if (!draft.customerId) return toast.error('Choose a customer for this order.');
    const items = draft.lines.filter((line) => line.productId);
    if (!items.length) return toast.error('Add at least one product line.');

    setSaving(true);
    try {
      await post('/orders', {
        customerId: Number(draft.customerId),
        channel: draft.channel,
        notes: draft.notes || null,
        taxRate: TAX_RATE,
        items: items.map((line) => ({ productId: Number(line.productId), qty: Math.max(1, Number(line.qty) || 1) })),
      });
      toast.success('Order created.');
      setCreating(false);
      setDraft({ customerId: '', channel: 'showroom', notes: '', lines: [{ productId: '', qty: 1 }] });
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function changeStatus(nextStatus) {
    setUpdatingStatus(true);
    try {
      await patch(`/orders/${selectedId}`, { status: nextStatus });
      toast.success(`Order moved to ${statusMeta(nextStatus).label.toLowerCase()}.`);
      detail.refresh();
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setUpdatingStatus(false);
    }
  }

  const order = detail.data?.data;
  const summary = data?.meta?.summary;

  return (
    <>
      <PageHeader
        eyebrow="Commerce"
        title="Order book"
        description="Every booking from the showroom floor, the website and trade partners — with production status."
        actions={
          canWrite ? (
            <Button variant="primary" icon="plus" onClick={() => setCreating(true)}>
              New order
            </Button>
          ) : null
        }
      />

      <div className="kpi-grid kpi-grid-3">
        <div className="stat-card">
          <span className="stat-label">Orders in view</span>
          <strong className="stat-value">{number(data?.meta?.total ?? 0)}</strong>
          <span className="muted small">Matching the current filters</span>
        </div>
        <div className="stat-card">
          <span className="stat-label">Value in view</span>
          <strong className="stat-value">{money(summary?.value ?? 0)}</strong>
          <span className="muted small">Excludes cancelled lines</span>
        </div>
        <div className="stat-card">
          <span className="stat-label">Open production</span>
          <strong className="stat-value">
            {number(orders.filter((item) => ['pending', 'confirmed', 'in_production'].includes(item.status)).length)}
          </strong>
          <span className="muted small">On this page</span>
        </div>
      </div>

      <Card>
        <div className="toolbar">
          <SearchInput value={search} onChange={setSearch} placeholder="Search order number, customer or company…" />
          <Select
            value={channel}
            onChange={(event) => setChannel(event.target.value)}
            options={[{ value: 'all', label: 'All channels' }, ...CHANNELS.map((value) => ({ value, label: statusMeta(value).label }))]}
            style={{ width: 160 }}
          />
          <div style={{ marginLeft: 'auto' }} />
          <FilterChips options={ORDER_STATUSES} value={status} onChange={setStatus} allLabel="All statuses" />
        </div>

        {loading && !data ? (
          <Spinner label="Loading the order book…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : orders.length === 0 ? (
          <EmptyState
            icon="receipt"
            title="No orders match this view"
            description="Try a different status or clear the search to see the full order book."
            action={canWrite ? <Button variant="primary" icon="plus" onClick={() => setCreating(true)}>New order</Button> : null}
          />
        ) : (
          <>
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th className="num">Items</th>
                    <th className="num">Total</th>
                    <th className="num">Placed</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {orders.map((item) => (
                    <tr key={item.id} className="clickable" onClick={() => setSelectedId(item.id)}>
                      <td>
                        <span className="mono small strong">{item.order_no}</span>
                      </td>
                      <td>
                        <div className="cell-title truncate">{item.customer ?? 'Walk-in'}</div>
                        <div className="cell-sub">{item.company ?? '—'}</div>
                      </td>
                      <td>
                        <Badge tone={statusMeta(item.channel).tone}>{statusMeta(item.channel).label}</Badge>
                      </td>
                      <td>
                        <StatusBadge value={item.status} />
                      </td>
                      <td className="num">{item.item_count}</td>
                      <td className="num strong">{money(item.total, item.currency)}</td>
                      <td className="num muted small nowrap">{relativeTime(item.placed_at)}</td>
                      <td className="num">
                        <Icon name="chevronRight" size={16} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination meta={data.meta} onPage={setPage} />
          </>
        )}
      </Card>

      <Drawer
        open={Boolean(selectedId)}
        title={order?.order_no ?? 'Order'}
        subtitle={order ? `${order.customer ?? 'Walk-in'} · ${formatDate(order.placed_at)}` : 'Loading order…'}
        onClose={() => setSelectedId(null)}
        footer={
          <>
            <Button onClick={() => setSelectedId(null)}>Close</Button>
            {canWrite ? (
              <Button variant="primary" icon="check" onClick={() => changeStatus('delivered')} loading={updatingStatus} disabled={order?.status === 'delivered'}>
                Mark delivered
              </Button>
            ) : null}
          </>
        }
      >
        {detail.loading && !order ? <Spinner label="Loading order…" /> : null}
        {detail.error ? <ErrorState message={detail.error} onRetry={detail.refresh} /> : null}
        {order ? (
          <>
            <div className="row wrap">
              <StatusBadge value={order.status} />
              <Badge tone={statusMeta(order.channel).tone}>{statusMeta(order.channel).label}</Badge>
              <span className="muted small">Placed {formatDateTime(order.placed_at)}</span>
            </div>

            <div className="stack-sm">
              <p className="field-label">Customer</p>
              <div className="row" style={{ gap: 10 }}>
                <span className="cell-thumb">
                  <Icon name="users" size={17} />
                </span>
                <div>
                  <div className="cell-title">{order.customer ?? 'Walk-in customer'}</div>
                  <div className="cell-sub">
                    {[order.company, order.customer_city].filter(Boolean).join(' · ') || '—'}
                  </div>
                  <div className="cell-sub">{order.customer_email ?? order.customer_phone ?? 'No contact on file'}</div>
                </div>
              </div>
            </div>

            <div className="stack-sm">
              <p className="field-label">Line items</p>
              {order.items.map((line) => (
                <div key={line.id} className="row-between" style={{ padding: '8px 0', borderBottom: '1px dashed var(--border)' }}>
                  <div className="grow">
                    <div className="cell-title truncate">{line.name}</div>
                    <div className="cell-sub mono">{line.sku}</div>
                  </div>
                  <span className="muted small nowrap">
                    {line.qty} × {money(line.unit_price, order.currency)}
                  </span>
                  <strong className="nowrap" style={{ minWidth: 90, textAlign: 'right' }}>
                    {money(line.qty * line.unit_price, order.currency)}
                  </strong>
                </div>
              ))}
            </div>

            <div className="totals">
              <div className="totals-row">
                <span>Subtotal</span>
                <span>{money(order.subtotal, order.currency)}</span>
              </div>
              <div className="totals-row">
                <span>Sales tax</span>
                <span>{money(order.tax, order.currency)}</span>
              </div>
              <div className="totals-row">
                <span>Delivery</span>
                <span>{order.shipping ? money(order.shipping, order.currency) : 'Free'}</span>
              </div>
              <div className="totals-row grand">
                <span>Total</span>
                <span>{money(order.total, order.currency)}</span>
              </div>
            </div>

            {order.notes ? <p className="muted small">{order.notes}</p> : null}

            {canWrite ? (
              <div className="stack-sm">
                <p className="field-label">Move to another stage</p>
                <div className="chips">
                  {ORDER_STATUSES.map((value) => (
                    <button
                      key={value}
                      type="button"
                      className={`chip ${order.status === value ? 'chip-active' : ''}`}
                      disabled={updatingStatus || order.status === value}
                      onClick={() => changeStatus(value)}
                    >
                      {statusMeta(value).label}
                    </button>
                  ))}
                </div>
              </div>
            ) : null}
          </>
        ) : null}
      </Drawer>

      <Modal
        open={creating}
        title="New order"
        subtitle="Book a showroom, online or trade order against the live catalogue."
        onClose={() => setCreating(false)}
        size="lg"
        footer={
          <>
            <Button onClick={() => setCreating(false)} disabled={saving}>
              Cancel
            </Button>
            <Button variant="primary" onClick={createOrder} loading={saving}>
              Create order
            </Button>
          </>
        }
      >
        <div className="form-grid">
          <Field label="Customer" required>
            <Select
              value={draft.customerId}
              onChange={(event) => setDraft({ ...draft, customerId: event.target.value })}
              options={[{ value: '', label: 'Select a customer…' }, ...customers.map((customer) => ({ value: customer.id, label: `${customer.name}${customer.company ? ` — ${customer.company}` : ''}` }))]}
            />
          </Field>
          <Field label="Channel">
            <Select
              value={draft.channel}
              onChange={(event) => setDraft({ ...draft, channel: event.target.value })}
              options={CHANNELS.map((value) => ({ value, label: statusMeta(value).label }))}
            />
          </Field>
        </div>

        <div className="stack-sm">
          <p className="field-label">Line items</p>
          {draft.lines.map((line, index) => (
            <div className="line-item" key={index}>
              <Select
                value={line.productId}
                onChange={(event) => {
                  const lines = [...draft.lines];
                  lines[index] = { ...line, productId: event.target.value };
                  setDraft({ ...draft, lines });
                }}
                options={[
                  { value: '', label: 'Select a product…' },
                  ...products.map((product) => ({ value: product.id, label: `${product.name} · ${money(product.price)}` })),
                ]}
              />
              <Input
                type="number"
                min="1"
                value={line.qty}
                onChange={(event) => {
                  const lines = [...draft.lines];
                  lines[index] = { ...line, qty: event.target.value };
                  setDraft({ ...draft, lines });
                }}
              />
              <IconButton
                icon="trash"
                label="Remove line"
                variant="danger"
                onClick={() => setDraft({ ...draft, lines: draft.lines.filter((_, i) => i !== index) })}
              />
            </div>
          ))}
          <Button
            size="sm"
            icon="plus"
            onClick={() => setDraft({ ...draft, lines: [...draft.lines, { productId: '', qty: 1 }] })}
          >
            Add line
          </Button>
        </div>

        <div className="totals">
          <div className="totals-row">
            <span>Subtotal</span>
            <span>{money(preview.subtotal)}</span>
          </div>
          <div className="totals-row">
            <span>Sales tax ({TAX_RATE}%)</span>
            <span>{money(preview.tax)}</span>
          </div>
          <div className="totals-row grand">
            <span>Total</span>
            <span>{money(preview.total)}</span>
          </div>
        </div>

        <Field label="Internal notes">
          <Textarea value={draft.notes} onChange={(event) => setDraft({ ...draft, notes: event.target.value })} placeholder="Delivery window, custom finish, assembly instructions…" />
        </Field>
      </Modal>
    </>
  );
}
