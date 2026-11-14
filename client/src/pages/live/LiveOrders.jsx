import { useEffect, useState } from 'react';
import { useFetch } from '../../lib/hooks.js';
import { formatDate, formatDateTime, money, number, statusMeta } from '../../lib/format.js';
import {
  Badge, Card, Drawer, EmptyState, ErrorState, FilterChips, PageHeader, Pagination,
  SearchInput, Spinner, StatusBadge,
} from '../../components/ui.jsx';
import Icon from '../../components/icons.jsx';

const STATUSES = ['Pending', 'Processing', 'Completed', 'Delivered', 'Cancelled'];

export default function LiveOrders() {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState(null);

  useEffect(() => setPage(1), [search, status]);

  const query = new URLSearchParams({ page: String(page), limit: '10' });
  if (search) query.set('search', search);
  if (status !== 'all') query.set('status', status);

  const { data, loading, error, refresh } = useFetch(`/live/orders?${query.toString()}`);
  const detail = useFetch(selectedId ? `/live/orders/${selectedId}` : null);

  const orders = data?.data ?? [];
  const order = detail.data?.data;
  const itemsTotal = (order?.items ?? []).reduce((sum, item) => sum + item.subtotal, 0);

  return (
    <>
      <PageHeader
        eyebrow="Live data"
        title="Orders"
        description="The five orders recorded in your cPanel database — customer details, line items and payment state exactly as imported."
      />

      <Card>
        <div className="toolbar">
          <SearchInput value={search} onChange={setSearch} placeholder="Search order number, customer or email…" />
          <div style={{ marginLeft: 'auto' }} />
          <FilterChips options={STATUSES} value={status} onChange={setStatus} allLabel="All statuses" />
        </div>

        {loading && !data ? (
          <Spinner label="Loading live orders…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : orders.length === 0 ? (
          <EmptyState icon="receipt" title="No orders match" description="Try a different status or clear the search." />
        ) : (
          <>
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th className="num">Items</th>
                    <th className="num">Total</th>
                    <th className="num">Placed</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {orders.map((row) => (
                    <tr key={row.id} className="clickable" onClick={() => setSelectedId(row.id)}>
                      <td className="mono small strong">{row.order_number}</td>
                      <td>
                        <div className="cell-title truncate">{row.customer_name}</div>
                        <div className="cell-sub truncate">{row.customer_email ?? row.customer_phone ?? '—'}</div>
                      </td>
                      <td>
                        <Badge tone={row.payment_status === 'Succeeded' ? 'success' : row.payment_status === 'Failed' ? 'danger' : 'warning'}>
                          {row.payment_status}
                        </Badge>
                      </td>
                      <td><StatusBadge value={row.status} /></td>
                      <td className="num">{row.item_count}</td>
                      <td className="num strong">{money(row.total)}</td>
                      <td className="num muted small nowrap">{formatDate(row.order_date)}</td>
                      <td className="num"><Icon name="chevronRight" size={16} /></td>
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
        title={order?.order_number ?? 'Order'}
        subtitle={order ? `${order.customer_name} · ${formatDateTime(order.order_date)}` : 'Loading…'}
        onClose={() => setSelectedId(null)}
        footer={<span className="muted small">Read-only view of the imported order</span>}
      >
        {detail.loading && !order ? <Spinner label="Loading order…" /> : null}
        {detail.error ? <ErrorState message={detail.error} onRetry={detail.refresh} /> : null}
        {order ? (
          <>
            <div className="row wrap">
              <StatusBadge value={order.status} />
              <Badge tone={order.payment_status === 'Succeeded' ? 'success' : 'warning'}>{order.payment_status}</Badge>
              <Badge tone="neutral">{order.payment_method ?? 'Stripe'}</Badge>
            </div>

            <div className="stack-sm">
              <p className="field-label">Customer</p>
              <div className="row" style={{ gap: 10 }}>
                <span className="cell-thumb"><Icon name="users" size={17} /></span>
                <div>
                  <div className="cell-title">{order.customer_name}</div>
                  <div className="cell-sub">{[order.customer_email, order.customer_phone].filter(Boolean).join(' · ')}</div>
                  <div className="cell-sub">{order.shipping_address}</div>
                </div>
              </div>
            </div>

            <div className="stack-sm">
              <p className="field-label">Line items</p>
              {order.items.map((item) => (
                <div key={item.id} className="row-between" style={{ padding: '8px 0', borderBottom: '1px dashed var(--border)', gap: 10 }}>
                  <div className="grow">
                    <div className="cell-title truncate">{item.product_name}</div>
                    <div className="cell-sub">
                      {item.product_missing ? (
                        <Badge tone="danger">product deleted</Badge>
                      ) : (
                        <span className="mono">#{item.product_id}</span>
                      )}
                    </div>
                  </div>
                  <span className="muted small nowrap">{item.quantity} × {money(item.price)}</span>
                  <strong className="nowrap" style={{ minWidth: 90, textAlign: 'right' }}>{money(item.subtotal)}</strong>
                </div>
              ))}
            </div>

            <div className="totals">
              <div className="totals-row"><span>Items</span><span>{money(itemsTotal)}</span></div>
              <div className="totals-row"><span>Delivery</span><span>{order.shipping ? money(order.shipping) : 'Free'}</span></div>
              <div className="totals-row"><span>Discount</span><span>{order.discount ? `− ${money(order.discount)}` : '—'}</span></div>
              <div className="totals-row grand"><span>Total</span><span>{money(order.total)}</span></div>
            </div>

            {order.tracking_code ? (
              <p className="muted small">
                Tracking: <span className="mono">{order.tracking_code}</span> · {order.delivery_company}
              </p>
            ) : null}
          </>
        ) : null}
      </Drawer>
    </>
  );
}
