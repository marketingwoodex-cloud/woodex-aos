import { Link, useNavigate } from 'react-router-dom';
import { useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { formatDateTime, money, number, relativeTime, statusMeta } from '../lib/format.js';
import { Button, Card, CardHead, EmptyState, ErrorState, PageHeader, Spinner, StatusBadge } from '../components/ui.jsx';
import { AreaChart, BarList, Donut, ProgressBar, Sparkline } from '../components/charts.jsx';
import Icon from '../components/icons.jsx';

function greeting() {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

function StatCard({ label, value, icon, footnote, delta, deltaTone = 'muted', children }) {
  return (
    <article className="stat-card">
      <div className="stat-top">
        <span className="stat-label">{label}</span>
        <span className="stat-icon">
          <Icon name={icon} size={17} />
        </span>
      </div>
      <strong className="stat-value">{value}</strong>
      <div className="stat-foot">
        <span className={`stat-delta ${deltaTone === 'up' ? 'stat-delta-up' : deltaTone === 'down' ? 'stat-delta-down' : ''}`}>
          {deltaTone === 'up' ? <Icon name="arrowUp" size={12} /> : null}
          {deltaTone === 'down' ? <Icon name="arrowDown" size={12} /> : null}
          {delta}
        </span>
        {children}
      </div>
      {footnote ? <span className="muted small">{footnote}</span> : null}
    </article>
  );
}

export default function Dashboard() {
  const { data, loading, error, refresh } = useFetch('/dashboard');
  const { user } = useAuth();
  const navigate = useNavigate();

  if (loading && !data) return <Spinner label="Loading your workspace…" />;
  if (error) return <ErrorState message={error} onRetry={refresh} />;
  if (!data) return null;

  const { kpis, revenueSeries, ordersByStatus, topProducts, recentOrders, recentActivity, projects, lowStockItems } = data;
  const revenueValues = revenueSeries.map((point) => point.value || 0);
  const lastMonth = revenueSeries.at(-1)?.value ?? 0;
  const priorMonth = revenueSeries.at(-2)?.value ?? 0;
  const trend = priorMonth > 0 ? Math.round(((lastMonth - priorMonth) / priorMonth) * 100) : 0;
  const months = revenueSeries.map((point) => point.label).join(' · ');

  return (
    <>
      <PageHeader
        eyebrow={`Overview · ${new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })}`}
        title={`${greeting()}, ${user?.name?.split(' ')[0] ?? 'there'}`}
        description="Live position across the catalogue, the order book and every deployment in the Woodex estate."
        actions={
          <>
            <Button icon="receipt" onClick={() => navigate('/orders?new=1')}>
              New order
            </Button>
            <Button icon="plus" variant="primary" onClick={() => navigate('/products?new=1')}>
              Add product
            </Button>
          </>
        }
      />

      <div className="kpi-grid">
        <StatCard
          label="Revenue booked"
          value={money(kpis.revenue)}
          icon="trendUp"
          delta={trend === 0 ? 'Flat on last month' : `${trend > 0 ? '+' : ''}${trend}% on last month`}
          deltaTone={trend > 0 ? 'up' : trend < 0 ? 'down' : 'muted'}
        >
          <Sparkline values={revenueValues} height={30} />
        </StatCard>
        <StatCard
          label="Orders placed"
          value={number(kpis.orders)}
          icon="receipt"
          delta={`${kpis.openOrders} still open in production`}
        />
        <StatCard
          label="Average order value"
          value={money(kpis.avgOrder)}
          icon="sparkle"
          delta={`${number(kpis.customers)} active customers`}
        />
        <StatCard
          label="Low stock lines"
          value={number(kpis.lowStock)}
          icon="alert"
          delta={kpis.lowStock ? 'Needs a purchase order' : 'Stock levels healthy'}
          deltaTone={kpis.lowStock ? 'down' : 'up'}
        />
      </div>

      <div className="dash-grid">
        <Card>
          <CardHead
            title="Revenue trend"
            subtitle={`Last six months · ${months}`}
            icon="trendUp"
            actions={<span className="badge badge-accent">{money(lastMonth, 'PKR', true)} this month</span>}
          />
          <div className="chart-body">
            <AreaChart data={revenueSeries} height={252} />
          </div>
        </Card>

        <Card>
          <CardHead title="Order pipeline" subtitle="Every order by current stage" icon="layers" />
          <Donut segments={ordersByStatus} />
        </Card>
      </div>

      <div className="dash-grid">
        <Card>
          <CardHead
            title="Recent orders"
            subtitle="Newest bookings from all channels"
            icon="receipt"
            actions={
              <Link to="/orders" className="btn btn-ghost btn-sm">
                View all
              </Link>
            }
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
                  <tr key={order.id} className="clickable" onClick={() => navigate('/orders')}>
                    <td>
                      <span className="mono small strong">{order.order_no}</span>
                    </td>
                    <td className="truncate">{order.customer ?? '—'}</td>
                    <td>
                      <StatusBadge value={order.status} />
                    </td>
                    <td className="num">{money(order.total, order.currency)}</td>
                    <td className="num muted small">{relativeTime(order.placed_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <div className="stack">
          <Card>
            <CardHead title="Best sellers" subtitle="By revenue across the order book" icon="sparkle" />
            <div style={{ padding: '10px 18px 16px' }}>
              <BarList
                items={topProducts}
                valueKey="revenue"
                formatter={(value) => money(value, 'PKR', true)}
              />
            </div>
          </Card>
          <Card>
            <CardHead title="Low stock" subtitle="At or below reorder level" icon="alert" />
            <div style={{ padding: '10px 18px 16px' }}>
              {lowStockItems.length === 0 ? (
                <p className="muted small">Every line is above its reorder level.</p>
              ) : (
                <ul className="bar-list">
                  {lowStockItems.map((item) => (
                    <li key={item.id}>
                      <div className="bar-list-head">
                        <span className="bar-list-label">{item.name}</span>
                        <span className="bar-list-value">
                          {item.stock} / {item.reorder_level}
                        </span>
                      </div>
                      <div className="bar-list-track">
                        <span
                          className="bar-list-fill"
                          style={{
                            width: `${Math.min(100, Math.round((item.stock / Math.max(item.reorder_level, 1)) * 100))}%`,
                            background: 'var(--danger)',
                          }}
                        />
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </Card>
        </div>
      </div>

      <div className="dash-grid">
        <Card>
          <CardHead
            title="Project health"
            subtitle="Integration and deployment status across the estate"
            icon="layers"
            actions={
              <Link to="/projects" className="btn btn-ghost btn-sm">
                Open projects
              </Link>
            }
          />
          <div style={{ padding: '6px 18px 16px' }}>
            {projects.length === 0 ? (
              <EmptyState icon="layers" title="No projects yet" description="Register your first project to track deployments." />
            ) : (
              projects.map((project) => (
                <Link
                  key={project.id}
                  to={`/projects/${project.id}`}
                  style={{ display: 'grid', gap: 8, padding: '12px 0', borderBottom: '1px dashed var(--border)', textDecoration: 'none', color: 'inherit' }}
                >
                  <div className="row-between">
                    <div className="row" style={{ gap: 9 }}>
                      <span className="cell-title">{project.name}</span>
                      <StatusBadge value={project.status} />
                    </div>
                    <span className="muted small">{project.deployment_count} deploys</span>
                  </div>
                  <ProgressBar
                    value={project.progress}
                    tone={project.status === 'live' ? 'success' : project.status === 'paused' ? 'warning' : 'accent'}
                  />
                </Link>
              ))
            )}
          </div>
        </Card>

        <Card>
          <CardHead
            title="Activity"
            subtitle="Latest changes across the workspace"
            icon="pulse"
            actions={
              <Link to="/activity" className="btn btn-ghost btn-sm">
                Full log
              </Link>
            }
          />
          <ul className="activity-list" style={{ padding: '4px 18px 14px' }}>
            {recentActivity.map((entry) => (
              <li className="activity-item" key={entry.id}>
                <span className={`activity-icon ${entry.action}`}>
                  <Icon name={entry.action === 'deploy' ? 'rocket' : entry.action === 'delete' ? 'trash' : entry.action === 'create' ? 'plus' : 'pulse'} size={14} />
                </span>
                <div>
                  <p className="activity-text">{entry.summary}</p>
                  <p className="activity-meta">
                    <span>{entry.user_name}</span>
                    <span>·</span>
                    <span>{statusMeta(entry.entity).label}</span>
                  </p>
                </div>
                <span className="activity-time">{relativeTime(entry.created_at)}</span>
              </li>
            ))}
          </ul>
          <div className="card-head" style={{ borderTop: '1px solid var(--border)', borderBottom: 0, paddingTop: 10, paddingBottom: 10 }}>
            <span className="muted small">Snapshot generated {formatDateTime(data.generatedAt)}</span>
          </div>
        </Card>
      </div>
    </>
  );
}
