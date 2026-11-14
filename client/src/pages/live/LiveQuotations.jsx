import { useEffect, useState } from 'react';
import { useFetch } from '../../lib/hooks.js';
import { formatDate, formatDateTime, money, number, statusMeta } from '../../lib/format.js';
import {
  Badge, Card, Drawer, EmptyState, ErrorState, FilterChips, PageHeader, Pagination,
  SearchInput, Spinner,
} from '../../components/ui.jsx';
import Icon from '../../components/icons.jsx';

const STATUSES = ['new', 'sent', 'won', 'lost'];

export default function LiveQuotations() {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState(null);

  useEffect(() => setPage(1), [search, status]);

  const query = new URLSearchParams({ page: String(page), limit: '10' });
  if (search) query.set('search', search);
  if (status !== 'all') query.set('status', status);

  const { data, loading, error, refresh } = useFetch(`/live/quotations?${query.toString()}`);
  const detail = useFetch(selectedId ? `/live/quotations/${selectedId}` : null);

  const quotes = data?.data ?? [];
  const quote = detail.data?.data;
  const itemsTotal = (quote?.items ?? []).reduce((sum, item) => sum + item.line_total, 0);
  const missing = (quote?.items ?? []).filter((item) => item.product_missing).length;

  return (
    <>
      <PageHeader
        eyebrow="Live data"
        title="Quotation requests"
        description="Project and trade enquiries captured by the storefront — 11 open requests worth Rs 1.79M in your database."
      />

      <Card>
        <div className="toolbar">
          <SearchInput value={search} onChange={setSearch} placeholder="Search quote number, customer or email…" />
          <div style={{ marginLeft: 'auto' }} />
          <FilterChips options={STATUSES} value={status} onChange={setStatus} allLabel="All statuses" />
        </div>

        {loading && !data ? (
          <Spinner label="Loading quotations…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : quotes.length === 0 ? (
          <EmptyState icon="edit" title="No quotations match" description="Try a different status or clear the search." />
        ) : (
          <>
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Quote</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th className="num">Lines</th>
                    <th className="num">Total</th>
                    <th className="num">Created</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {quotes.map((row) => (
                    <tr key={row.id} className="clickable" onClick={() => setSelectedId(row.id)}>
                      <td className="mono small strong">{row.quote_no}</td>
                      <td>
                        <div className="cell-title truncate">{row.customer_name}</div>
                        <div className="cell-sub truncate">{row.customer_email ?? row.customer_phone ?? '—'}</div>
                      </td>
                      <td>
                        <Badge tone={row.status === 'new' ? 'info' : row.status === 'won' ? 'success' : 'neutral'}>{row.status}</Badge>
                      </td>
                      <td className="num">{row.item_count}</td>
                      <td className="num strong">{money(row.total)}</td>
                      <td className="num muted small nowrap">{formatDate(row.created_at)}</td>
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
        title={quote?.quote_no ?? 'Quotation'}
        subtitle={quote ? `${quote.customer_name} · ${formatDateTime(quote.created_at)}` : 'Loading…'}
        onClose={() => setSelectedId(null)}
        footer={<span className="muted small">Read-only view of the imported quotation</span>}
      >
        {detail.loading && !quote ? <Spinner label="Loading quotation…" /> : null}
        {detail.error ? <ErrorState message={detail.error} onRetry={detail.refresh} /> : null}
        {quote ? (
          <>
            <div className="row wrap">
              <Badge tone={quote.status === 'new' ? 'info' : 'neutral'}>{quote.status}</Badge>
              <span className="muted small">{quote.items.length} line items</span>
              {missing ? <Badge tone="danger">{missing} unresolved product links</Badge> : null}
            </div>

            <div className="stack-sm">
              <p className="field-label">Customer</p>
              <div className="row" style={{ gap: 10 }}>
                <span className="cell-thumb"><Icon name="users" size={17} /></span>
                <div>
                  <div className="cell-title">{quote.customer_name}</div>
                  <div className="cell-sub">{[quote.customer_email, quote.customer_phone].filter(Boolean).join(' · ')}</div>
                  <div className="cell-sub">{quote.customer_address}</div>
                </div>
              </div>
            </div>

            <div className="stack-sm">
              <p className="field-label">Requested items</p>
              {quote.items.map((item) => (
                <div key={item.id} className="row-between" style={{ padding: '8px 0', borderBottom: '1px dashed var(--border)', gap: 10 }}>
                  <div className="grow">
                    <div className="cell-title truncate">{item.product_name}</div>
                    <div className="cell-sub mono">{item.sku || '—'}</div>
                  </div>
                  <span className="muted small nowrap">{item.quantity} × {money(item.unit_price)}</span>
                  <strong className="nowrap" style={{ minWidth: 90, textAlign: 'right' }}>{money(item.line_total)}</strong>
                </div>
              ))}
            </div>

            <div className="totals">
              <div className="totals-row"><span>Line total</span><span>{money(itemsTotal)}</span></div>
              <div className="totals-row grand"><span>Quoted total</span><span>{money(quote.total)}</span></div>
            </div>

            {missing ? (
              <div className="notice">
                <Icon name="alert" size={15} />
                <span>{missing} lines point at products deleted in the re-import — names and prices are preserved from the snapshot.</span>
              </div>
            ) : null}
          </>
        ) : null}
      </Drawer>
    </>
  );
}
