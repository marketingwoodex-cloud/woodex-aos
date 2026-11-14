import { Link } from 'react-router-dom';
import { useFetch } from '../../lib/hooks.js';
import { formatDateTime, money, number, relativeTime } from '../../lib/format.js';
import { Badge, Card, CardHead, EmptyState, ErrorState, PageHeader, Spinner, StatusBadge } from '../../components/ui.jsx';
import { ProgressBar } from '../../components/charts.jsx';
import Icon from '../../components/icons.jsx';

function Stat({ label, value, hint, icon, tone = '' }) {
  return (
    <article className="stat-card">
      <div className="stat-top">
        <span className="stat-label">{label}</span>
        <span className="stat-icon" style={tone ? { background: `var(--${tone}-soft)`, color: `var(--${tone})` } : undefined}>
          <Icon name={icon} size={17} />
        </span>
      </div>
      <strong className="stat-value">{value}</strong>
      <span className="muted small">{hint}</span>
    </article>
  );
}

export default function LiveOverview() {
  const { data, loading, error, refresh } = useFetch('/live/overview');

  if (loading && !data) return <Spinner label="Loading live database…" />;
  if (error) return <ErrorState message={error} onRetry={refresh} />;

  if (data?.available === false) {
    return (
      <>
        <PageHeader eyebrow="Live data" title="Imported catalogue" description="Your cPanel database, previewed inside Woodex AOS." />
        <Card>
          <EmptyState
            icon="inbox"
            title="Live database not imported"
            description="Run `python3 tools/db-import/export_snapshot.py db-source/woodexbaks.sql` and then `npm run db:import` to load the snapshot."
          />
        </Card>
      </>
    );
  }

  const { totals, categories, recentOrders, recentQuotations, lowStock, meta } = data;
  const installed = categories.reduce((sum, item) => sum + item.product_count, 0);
  const orphanLines = totals.orphan_order_lines + totals.orphan_quote_lines;

  return (
    <>
      <PageHeader
        eyebrow="Live data · woodexbaks"
        title="Imported catalogue & commerce"
        description={`Read-write preview of your actual cPanel database — ${number(totals.products)} products, ${number(totals.orders)} orders and ${number(totals.quotations)} quotations imported from ${meta.database}.`}
        actions={
          <>
            <Link to="/shop" className="btn btn-secondary" target="_blank" rel="noreferrer">
              <Icon name="external" size={15} /> Open storefront
            </Link>
            <Link to="/live/catalogue" className="btn btn-primary">
              Manage catalogue
            </Link>
          </>
        }
      />

      <div className="kpi-grid">
        <Stat label="Orders booked" value={money(totals.order_value)} hint={`${totals.orders} orders · all payments pending`} icon="receipt" />
        <Stat label="Quotation pipeline" value={money(totals.quotation_value)} hint={`${totals.quotations} open requests`} icon="edit" />
        <Stat
          label="Out of stock"
          value={`${number(totals.out_of_stock)} / ${number(totals.products)}`}
          hint={`${Math.round((totals.out_of_stock / Math.max(totals.products, 1)) * 100)}% of the catalogue`}
          icon="alert"
          tone="danger"
        />
        <Stat label="Zero-priced lines" value={number(totals.zero_price)} hint="No sell price set in the source data" icon="alert" tone="warning" />
      </div>

      {orphanLines > 0 ? (
        <div className="notice">
          <Icon name="alert" size={16} />
          <span>
            <strong>{orphanLines} historical line items reference products deleted during a re-import</strong> — {totals.orphan_order_lines} order
            lines and {totals.orphan_quote_lines} quotation lines. They keep their recorded name and price, so receipts still
            render, but product links and stock reconciliation cannot resolve them. See{' '}
            <span className="mono">docs/database-audit.md</span> (finding H1).
          </span>
        </div>
      ) : null}

      <div className="dash-grid">
        <Card>
          <CardHead
            title="Recent orders"
            subtitle="Imported from the web checkout"
            icon="receipt"
            actions={<Link to="/live/orders" className="btn btn-ghost btn-sm">View all</Link>}
          />
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th className="num">Total</th>
                  <th className="num">Placed</th>
                </tr>
              </thead>
              <tbody>
                {recentOrders.map((order) => (
                  <tr key={order.id}>
                    <td className="mono small strong">{order.order_number}</td>
                    <td className="truncate">{order.customer_name}</td>
                    <td><StatusBadge value={order.status} /></td>
                    <td className="num">{money(order.total)}</td>
                    <td className="num muted small nowrap">{relativeTime(order.order_date)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <Card>
          <CardHead title="Top categories" subtitle="Products per top-level collection" icon="layers" />
          <div style={{ padding: '10px 18px 16px' }} className="stack-sm">
            {categories.map((category) => (
              <div key={category.id}>
                <div className="row-between" style={{ marginBottom: 5 }}>
                  <span className="small strong">{category.name}</span>
                  <span className="muted small">{number(category.product_count)} products</span>
                </div>
                <ProgressBar value={(category.product_count / Math.max(installed, 1)) * 100 * 3} />
              </div>
            ))}
            <div className="row-between" style={{ marginTop: 6 }}>
              <span className="muted small">{number(totals.categories)} categories in total</span>
              <Badge tone="neutral">{number(totals.faqs)} FAQs</Badge>
            </div>
          </div>
        </Card>
      </div>

      <div className="dash-grid-even">
        <Card>
          <CardHead
            title="Recent quotations"
            subtitle="Trade and project enquiries"
            icon="edit"
            actions={<Link to="/live/quotations" className="btn btn-ghost btn-sm">View all</Link>}
          />
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Quote</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th className="num">Total</th>
                </tr>
              </thead>
              <tbody>
                {recentQuotations.map((quote) => (
                  <tr key={quote.id}>
                    <td className="mono small strong">{quote.quote_no}</td>
                    <td className="truncate">{quote.customer_name}</td>
                    <td><Badge tone={quote.status === 'new' ? 'info' : 'neutral'}>{quote.status}</Badge></td>
                    <td className="num">{money(quote.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <Card>
          <CardHead title="Stock to resolve" subtitle="Products at or below 2 units" icon="alert" />
          <div style={{ padding: '4px 18px 14px' }}>
            {lowStock.length === 0 ? (
              <p className="muted small">No low-stock lines.</p>
            ) : (
              lowStock.map((item) => (
                <div key={item.id} className="row-between" style={{ padding: '10px 0', borderBottom: '1px dashed var(--border)' }}>
                  <div className="grow">
                    <div className="cell-title truncate">{item.name}</div>
                    <div className="cell-sub mono">{item.sku}</div>
                  </div>
                  <Badge tone={item.stock <= 0 ? 'danger' : 'warning'}>{item.stock} left</Badge>
                </div>
              ))
            )}
          </div>
          <div className="card-head" style={{ borderTop: '1px solid var(--border)', borderBottom: 0, paddingTop: 10, paddingBottom: 10 }}>
            <span className="muted small">
              Imported from {meta.source} · {formatDateTime(meta.importedAt)}
            </span>
          </div>
        </Card>
      </div>
    </>
  );
}
