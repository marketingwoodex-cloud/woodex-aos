import { useEffect, useId } from 'react';
import Icon from './icons.jsx';
import { initials, statusMeta } from '../lib/format.js';

/* ------------------------------------------------------------------ buttons */

export function Button({ variant = 'secondary', size = 'md', icon, children, loading = false, className = '', ...rest }) {
  return (
    <button
      type="button"
      className={`btn btn-${variant} btn-${size} ${className}`.trim()}
      disabled={loading || rest.disabled}
      {...rest}
    >
      {loading ? <span className="spinner spinner-sm" /> : icon ? <Icon name={icon} size={16} /> : null}
      {children ? <span>{children}</span> : null}
    </button>
  );
}

export function IconButton({ icon, label, variant = 'ghost', ...rest }) {
  return (
    <button type="button" className={`icon-btn icon-btn-${variant}`} aria-label={label} title={label} {...rest}>
      <Icon name={icon} size={16} />
    </button>
  );
}

/* -------------------------------------------------------------------- cards */

export function Card({ className = '', children, padded = true, ...rest }) {
  return (
    <section className={`card ${padded ? 'card-padded' : ''} ${className}`.trim()} {...rest}>
      {children}
    </section>
  );
}

export function CardHead({ title, subtitle, actions, icon }) {
  return (
    <header className="card-head">
      <div className="card-head-text">
        <h2 className="card-title">
          {icon ? <Icon name={icon} size={17} /> : null}
          {title}
        </h2>
        {subtitle ? <p className="card-subtitle">{subtitle}</p> : null}
      </div>
      {actions ? <div className="card-actions">{actions}</div> : null}
    </header>
  );
}

export function PageHeader({ eyebrow, title, description, actions }) {
  return (
    <header className="page-head">
      <div>
        {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
        <h1 className="page-title">{title}</h1>
        {description ? <p className="page-description">{description}</p> : null}
      </div>
      {actions ? <div className="page-actions">{actions}</div> : null}
    </header>
  );
}

/* ------------------------------------------------------------------- badges */

export function Badge({ tone = 'neutral', children, dot = false }) {
  return (
    <span className={`badge badge-${tone}`}>
      {dot ? <span className="badge-dot" /> : null}
      {children}
    </span>
  );
}

export function StatusBadge({ value }) {
  const meta = statusMeta(value);
  return (
    <Badge tone={meta.tone} dot>
      {meta.label}
    </Badge>
  );
}

export function Avatar({ name, color = '#B4531F', size = 34 }) {
  return (
    <span
      className="avatar"
      style={{ background: color, width: size, height: size, fontSize: Math.round(size * 0.38) }}
      title={name}
    >
      {initials(name)}
    </span>
  );
}

/* -------------------------------------------------------------------- forms */

export function Field({ label, hint, required, children, className = '' }) {
  return (
    <label className={`field ${className}`.trim()}>
      <span className="field-label">
        {label}
        {required ? <span className="field-required">*</span> : null}
      </span>
      {children}
      {hint ? <span className="field-hint">{hint}</span> : null}
    </label>
  );
}

export function Input({ className = '', ...rest }) {
  return <input className={`input ${className}`.trim()} {...rest} />;
}

export function Select({ options = [], className = '', ...rest }) {
  return (
    <select className={`input select ${className}`.trim()} {...rest}>
      {options.map((option) => {
        const value = typeof option === 'string' ? option : option.value;
        const label = typeof option === 'string' ? statusMeta(option).label : option.label;
        return (
          <option key={value} value={value}>
            {label}
          </option>
        );
      })}
    </select>
  );
}

export function Textarea({ className = '', ...rest }) {
  return <textarea className={`input textarea ${className}`.trim()} {...rest} />;
}

export function SearchInput({ value, onChange, placeholder = 'Search…' }) {
  return (
    <div className="search">
      <Icon name="search" size={16} />
      <input
        className="search-input"
        type="search"
        value={value}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
      />
      {value ? (
        <button type="button" className="search-clear" onClick={() => onChange('')} aria-label="Clear search">
          <Icon name="close" size={14} />
        </button>
      ) : null}
    </div>
  );
}

export function FilterChips({ options, value, onChange, allLabel = 'All' }) {
  return (
    <div className="chips" role="tablist">
      <button
        type="button"
        role="tab"
        aria-selected={value === 'all'}
        className={`chip ${value === 'all' ? 'chip-active' : ''}`}
        onClick={() => onChange('all')}
      >
        {allLabel}
      </button>
      {options.map((option) => {
        const optionValue = typeof option === 'string' ? option : option.value;
        const label = typeof option === 'string' ? statusMeta(option).label : option.label;
        return (
          <button
            key={optionValue}
            type="button"
            role="tab"
            aria-selected={value === optionValue}
            className={`chip ${value === optionValue ? 'chip-active' : ''}`}
            onClick={() => onChange(optionValue)}
          >
            {label}
          </button>
        );
      })}
    </div>
  );
}

/* ------------------------------------------------------------------- modals */

export function Modal({ open, title, subtitle, onClose, children, footer, size = 'md' }) {
  useEffect(() => {
    if (!open) return undefined;
    const onKeyDown = (event) => {
      if (event.key === 'Escape') onClose?.();
    };
    document.addEventListener('keydown', onKeyDown);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = '';
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="overlay" onMouseDown={(event) => event.target === event.currentTarget && onClose?.()}>
      <div className={`modal modal-${size}`} role="dialog" aria-modal="true" aria-label={title}>
        <header className="modal-head">
          <div>
            <h2>{title}</h2>
            {subtitle ? <p>{subtitle}</p> : null}
          </div>
          <IconButton icon="close" label="Close" onClick={onClose} />
        </header>
        <div className="modal-body">{children}</div>
        {footer ? <footer className="modal-foot">{footer}</footer> : null}
      </div>
    </div>
  );
}

export function Drawer({ open, title, subtitle, onClose, children, footer }) {
  useEffect(() => {
    if (!open) return undefined;
    const onKeyDown = (event) => event.key === 'Escape' && onClose?.();
    document.addEventListener('keydown', onKeyDown);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = '';
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="overlay overlay-right" onMouseDown={(event) => event.target === event.currentTarget && onClose?.()}>
      <aside className="drawer" role="dialog" aria-modal="true" aria-label={title}>
        <header className="modal-head">
          <div>
            <h2>{title}</h2>
            {subtitle ? <p>{subtitle}</p> : null}
          </div>
          <IconButton icon="close" label="Close" onClick={onClose} />
        </header>
        <div className="drawer-body">{children}</div>
        {footer ? <footer className="modal-foot">{footer}</footer> : null}
      </aside>
    </div>
  );
}

export function ConfirmDialog({ open, title, message, confirmLabel = 'Delete', tone = 'danger', onConfirm, onClose, busy }) {
  return (
    <Modal
      open={open}
      title={title}
      onClose={onClose}
      size="sm"
      footer={
        <>
          <Button onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant={tone} onClick={onConfirm} loading={busy}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <p className="confirm-message">{message}</p>
    </Modal>
  );
}

/* --------------------------------------------------------------- misc states */

export function Spinner({ label }) {
  return (
    <div className="loading-block">
      <span className="spinner" />
      {label ? <span className="muted small">{label}</span> : null}
    </div>
  );
}

export function EmptyState({ icon = 'inbox', title, description, action }) {
  return (
    <div className="empty">
      <span className="empty-icon">
        <Icon name={icon} size={22} />
      </span>
      <h3>{title}</h3>
      {description ? <p>{description}</p> : null}
      {action}
    </div>
  );
}

export function ErrorState({ message, onRetry }) {
  return (
    <div className="empty empty-error">
      <span className="empty-icon">
        <Icon name="alert" size={22} />
      </span>
      <h3>Could not load this view</h3>
      <p>{message}</p>
      {onRetry ? (
        <Button variant="secondary" icon="refresh" onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </div>
  );
}

export function Pagination({ meta, onPage }) {
  if (!meta || meta.total === 0) return null;
  const from = (meta.page - 1) * meta.limit + 1;
  const to = Math.min(meta.total, meta.page * meta.limit);
  return (
    <div className="pagination">
      <span className="muted small">
        Showing <strong>{from}</strong>–<strong>{to}</strong> of <strong>{meta.total}</strong>
      </span>
      <div className="pagination-actions">
        <Button size="sm" icon="chevronLeft" onClick={() => onPage(meta.page - 1)} disabled={meta.page <= 1}>
          Prev
        </Button>
        <span className="pagination-page">
          {meta.page} / {meta.pages}
        </span>
        <Button size="sm" onClick={() => onPage(meta.page + 1)} disabled={meta.page >= meta.pages}>
          Next
        </Button>
      </div>
    </div>
  );
}

export function Toggle({ checked, onChange, label }) {
  const id = useId();
  return (
    <label className="toggle" htmlFor={id}>
      <input id={id} type="checkbox" checked={Boolean(checked)} onChange={(event) => onChange(event.target.checked)} />
      <span className="toggle-track">
        <span className="toggle-thumb" />
      </span>
      {label ? <span className="toggle-label">{label}</span> : null}
    </label>
  );
}
