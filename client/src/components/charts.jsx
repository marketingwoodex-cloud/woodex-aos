import { useId } from 'react';
import { money, number, statusMeta } from '../lib/format.js';

/** Small inline trend line. */
export function Sparkline({ values = [], height = 40, stroke = 'var(--accent)' }) {
  if (values.length < 2) return null;
  const max = Math.max(...values);
  const min = Math.min(...values);
  const span = max - min || 1;
  const points = values.map((value, index) => {
    const x = (index / (values.length - 1)) * 100;
    const y = height - ((value - min) / span) * (height - 6) - 3;
    return `${x.toFixed(2)},${y.toFixed(2)}`;
  });
  return (
    <svg className="sparkline" viewBox={`0 0 100 ${height}`} preserveAspectRatio="none" height={height} aria-hidden="true">
      <polyline points={points.join(' ')} fill="none" stroke={stroke} strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}

/** Revenue area chart with axis labels and hover dots. */
export function AreaChart({ data = [], currency = 'PKR', height = 240 }) {
  const gradientId = useId().replace(/:/g, '');
  if (!data.length) return null;

  const width = 640;
  const padding = { top: 18, right: 8, bottom: 28, left: 52 };
  const plotWidth = width - padding.left - padding.right;
  const plotHeight = height - padding.top - padding.bottom;
  const max = Math.max(...data.map((d) => d.value), 1);
  const stepX = data.length > 1 ? plotWidth / (data.length - 1) : plotWidth;

  const points = data.map((item, index) => ({
    ...item,
    x: padding.left + index * stepX,
    y: padding.top + plotHeight - (item.value / max) * plotHeight,
  }));

  const line = points.map((point) => `${point.x},${point.y}`).join(' ');
  const area = `${padding.left},${padding.top + plotHeight} ${line} ${padding.left + plotWidth},${padding.top + plotHeight}`;
  const ticks = [0, 0.5, 1];

  return (
    <svg className="area-chart" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Revenue trend">
      <defs>
        <linearGradient id={`area-${gradientId}`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="var(--accent)" stopOpacity="0.28" />
          <stop offset="100%" stopColor="var(--accent)" stopOpacity="0.02" />
        </linearGradient>
      </defs>

      {ticks.map((tick) => {
        const y = padding.top + plotHeight * tick;
        return (
          <g key={tick}>
            <line x1={padding.left} y1={y} x2={width - padding.right} y2={y} stroke="var(--border)" strokeDasharray="3 5" />
            <text x={padding.left - 10} y={y + 4} textAnchor="end" className="chart-axis">
              {money(max * (1 - tick), currency, true)}
            </text>
          </g>
        );
      })}

      <polygon points={area} fill={`url(#area-${gradientId})`} />
      <polyline points={line} fill="none" stroke="var(--accent)" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />

      {points.map((point) => (
        <g key={point.key ?? point.label}>
          <circle cx={point.x} cy={point.y} r="3.4" fill="var(--surface)" stroke="var(--accent)" strokeWidth="2" />
          <circle cx={point.x} cy={point.y} r="12" fill="transparent">
            <title>{`${point.label}: ${money(point.value, currency)} · ${number(point.orders)} orders`}</title>
          </circle>
          <text x={point.x} y={height - 8} textAnchor="middle" className="chart-axis">
            {point.label}
          </text>
        </g>
      ))}
    </svg>
  );
}

/** Donut showing the order pipeline. */
export function Donut({ segments = [], total, currency = 'PKR' }) {
  const active = segments.filter((segment) => segment.count > 0);
  const sum = total ?? active.reduce((acc, segment) => acc + segment.count, 0);
  const radius = 54;
  const circumference = 2 * Math.PI * radius;
  const tones = {
    pending: 'var(--warning)',
    confirmed: 'var(--info)',
    in_production: 'var(--accent)',
    shipped: 'var(--info)',
    delivered: 'var(--success)',
    cancelled: 'var(--danger)',
  };

  let offset = 0;
  return (
    <div className="donut-wrap">
      <svg viewBox="0 0 140 140" className="donut" role="img" aria-label="Orders by status">
        <circle cx="70" cy="70" r={radius} fill="none" stroke="var(--border)" strokeWidth="14" opacity="0.6" />
        {active.map((segment) => {
          const fraction = sum ? segment.count / sum : 0;
          const dash = fraction * circumference;
          const circle = (
            <circle
              key={segment.status}
              cx="70"
              cy="70"
              r={radius}
              fill="none"
              stroke={tones[segment.status] || 'var(--muted)'}
              strokeWidth="14"
              strokeDasharray={`${Math.max(dash - 2, 0)} ${circumference - Math.max(dash - 2, 0)}`}
              strokeDashoffset={-offset}
              strokeLinecap="round"
              transform="rotate(-90 70 70)"
            >
              <title>{`${statusMeta(segment.status).label}: ${segment.count} orders`}</title>
            </circle>
          );
          offset += dash;
          return circle;
        })}
        <text x="70" y="66" textAnchor="middle" className="donut-value">
          {number(sum)}
        </text>
        <text x="70" y="84" textAnchor="middle" className="donut-label">
          orders
        </text>
      </svg>
      <ul className="donut-legend">
        {segments.map((segment) => (
          <li key={segment.status}>
            <span className="legend-dot" style={{ background: tones[segment.status] || 'var(--muted)' }} />
            <span className="legend-label">{statusMeta(segment.status).label}</span>
            <span className="legend-value">{segment.count}</span>
          </li>
        ))}
      </ul>
      <p className="donut-foot muted small">Pipeline value {money(segments.reduce((acc, s) => acc + (s.value || 0), 0), currency)}</p>
    </div>
  );
}

export function ProgressBar({ value = 0, tone = 'accent', label }) {
  const clamped = Math.max(0, Math.min(100, Number(value) || 0));
  return (
    <div className="progress" title={label || `${clamped}%`}>
      <span className={`progress-fill progress-${tone}`} style={{ width: `${clamped}%` }} />
    </div>
  );
}

export function BarList({ items = [], valueKey = 'units', labelKey = 'name', formatter = (v) => number(v) }) {
  const max = Math.max(...items.map((item) => Number(item[valueKey]) || 0), 1);
  return (
    <ul className="bar-list">
      {items.map((item) => {
        const value = Number(item[valueKey]) || 0;
        return (
          <li key={item.id ?? item[labelKey]}>
            <div className="bar-list-head">
              <span className="bar-list-label">{item[labelKey]}</span>
              <span className="bar-list-value">{formatter(value)}</span>
            </div>
            <div className="bar-list-track">
              <span className="bar-list-fill" style={{ width: `${(value / max) * 100}%` }} />
            </div>
          </li>
        );
      })}
    </ul>
  );
}
