import { useEffect, useState } from 'react';
import { useDebounced, useFetch } from '../lib/hooks.js';
import { formatDateTime, relativeTime, statusMeta } from '../lib/format.js';
import {
  Badge, Card, EmptyState, ErrorState, FilterChips, PageHeader, SearchInput, Select, Spinner,
} from '../components/ui.jsx';
import Icon from '../components/icons.jsx';

const ENTITIES = ['order', 'product', 'customer', 'project', 'deployment', 'api_key', 'user', 'settings', 'integration', 'session'];

const ACTION_ICONS = {
  create: 'plus',
  update: 'edit',
  status: 'refresh',
  delete: 'trash',
  deploy: 'rocket',
  revoke: 'key',
  login: 'eye',
  logout: 'logout',
  export: 'download',
  error: 'alert',
};

export default function Activity() {
  const [entity, setEntity] = useState('all');
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounced(search);
  const [limit, setLimit] = useState('60');

  const query = new URLSearchParams({ limit });
  if (entity !== 'all') query.set('entity', entity);
  if (debouncedSearch) query.set('search', debouncedSearch);

  const { data, loading, error, refresh } = useFetch(`/activity?${query.toString()}`);
  const entries = data?.data ?? [];

  return (
    <>
      <PageHeader
        eyebrow="Platform"
        title="Activity & audit trail"
        description="Every change made in the workspace, who made it and when — useful for reconciliation and client reviews."
      />

      <Card>
        <div className="toolbar">
          <SearchInput value={search} onChange={setSearch} placeholder="Search the audit trail…" />
          <Select
            value={limit}
            onChange={(event) => setLimit(event.target.value)}
            options={[
              { value: '30', label: 'Last 30 events' },
              { value: '60', label: 'Last 60 events' },
              { value: '120', label: 'Last 120 events' },
            ]}
            style={{ width: 170 }}
          />
          <div style={{ marginLeft: 'auto' }} />
          <FilterChips options={ENTITIES} value={entity} onChange={setEntity} allLabel="Everything" />
        </div>

        {loading && !data ? (
          <Spinner label="Loading activity…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : entries.length === 0 ? (
          <EmptyState icon="pulse" title="Nothing logged here yet" description="Actions in the workspace appear here as soon as they happen." />
        ) : (
          <>
            <ul className="activity-list" style={{ padding: '6px 20px 16px' }}>
              {entries.map((entry) => (
                <li className="activity-item" key={entry.id}>
                  <span className={`activity-icon ${entry.action}`}>
                    <Icon name={ACTION_ICONS[entry.action] ?? 'pulse'} size={14} />
                  </span>
                  <div>
                    <p className="activity-text">{entry.summary}</p>
                    <p className="activity-meta">
                      <span className="strong">{entry.user_name ?? 'System'}</span>
                      <span>·</span>
                      <Badge tone={statusMeta(entry.entity).tone}>{statusMeta(entry.entity).label}</Badge>
                      <span>·</span>
                      <span>{formatDateTime(entry.created_at)}</span>
                    </p>
                  </div>
                  <span className="activity-time">{relativeTime(entry.created_at)}</span>
                </li>
              ))}
            </ul>
            <div className="pagination">
              <span className="muted small">
                Showing {entries.length} of {data?.meta?.total ?? entries.length} events
              </span>
            </div>
          </>
        )}
      </Card>
    </>
  );
}
