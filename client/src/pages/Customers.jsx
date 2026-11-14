import { useEffect, useState } from 'react';
import { del, patch, post } from '../lib/api.js';
import { useDebounced, useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import { CUSTOMER_TYPES, formatDate, money, number, statusMeta } from '../lib/format.js';
import {
  Avatar, Badge, Button, Card, ConfirmDialog, EmptyState, ErrorState, Field, FilterChips,
  IconButton, Input, Modal, PageHeader, Pagination, SearchInput, Select, Spinner, StatusBadge, Textarea,
} from '../components/ui.jsx';

const BLANK = { name: '', email: '', phone: '', company: '', city: '', country: 'Pakistan', type: 'retail', status: 'active', notes: '' };

const TONES = ['#B4531F', '#2C5D8F', '#2F7A5A', '#8A5A2B', '#7C3F5D', '#3C6E71'];

export default function Customers() {
  const { canWrite } = useAuth();
  const toast = useToast();

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounced(search);
  const [type, setType] = useState('all');
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(BLANK);
  const [saving, setSaving] = useState(false);
  const [toDelete, setToDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => setPage(1), [debouncedSearch, type]);

  const query = new URLSearchParams({ page: String(page), limit: '10' });
  if (debouncedSearch) query.set('search', debouncedSearch);
  if (type !== 'all') query.set('type', type);

  const { data, loading, error, refresh } = useFetch(`/customers?${query.toString()}`);
  const customers = data?.data ?? [];

  function openCreate() {
    setEditing({ id: null });
    setForm(BLANK);
  }

  function openEdit(customer) {
    setEditing(customer);
    setForm({
      name: customer.name,
      email: customer.email ?? '',
      phone: customer.phone ?? '',
      company: customer.company ?? '',
      city: customer.city ?? '',
      country: customer.country ?? 'Pakistan',
      type: customer.type,
      status: customer.status,
      notes: customer.notes ?? '',
    });
  }

  async function save(event) {
    event?.preventDefault();
    setSaving(true);
    try {
      if (editing?.id) {
        await patch(`/customers/${editing.id}`, form);
        toast.success('Customer updated.');
      } else {
        await post('/customers', form);
        toast.success('Customer added.');
      }
      setEditing(null);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function confirmDelete() {
    setDeleting(true);
    try {
      await del(`/customers/${toDelete.id}`);
      toast.success(`Removed “${toDelete.name}”.`);
      setToDelete(null);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setDeleting(false);
    }
  }

  return (
    <>
      <PageHeader
        eyebrow="Commerce"
        title="Customers & trade partners"
        description="Retail buyers, hospitality projects and wholesale accounts — with lifetime value at a glance."
        actions={
          canWrite ? (
            <Button variant="primary" icon="plus" onClick={openCreate}>
              Add customer
            </Button>
          ) : null
        }
      />

      <Card>
        <div className="toolbar">
          <SearchInput value={search} onChange={setSearch} placeholder="Search name, company or city…" />
          <FilterChips options={CUSTOMER_TYPES} value={type} onChange={setType} allLabel="Everyone" />
          <span className="muted small" style={{ marginLeft: 'auto' }}>
            {number(data?.meta?.total ?? 0)} accounts
          </span>
        </div>

        {loading && !data ? (
          <Spinner label="Loading customers…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : customers.length === 0 ? (
          <EmptyState
            icon="users"
            title="No customers yet"
            description="Add your first retail buyer or trade partner to start booking orders."
            action={canWrite ? <Button variant="primary" icon="plus" onClick={openCreate}>Add customer</Button> : null}
          />
        ) : (
          <>
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th className="num">Orders</th>
                    <th className="num">Lifetime value</th>
                    <th>Status</th>
                    {canWrite ? <th className="num">Actions</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {customers.map((customer, index) => (
                    <tr key={customer.id}>
                      <td>
                        <div className="cell-main">
                          <Avatar name={customer.name} color={TONES[index % TONES.length]} size={36} />
                          <div className="grow">
                            <div className="cell-title truncate">{customer.name}</div>
                            <div className="cell-sub truncate">{customer.company ?? customer.email ?? '—'}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <Badge tone={statusMeta(customer.type).tone}>{statusMeta(customer.type).label}</Badge>
                      </td>
                      <td className="muted nowrap">{customer.city ?? '—'}</td>
                      <td className="num">{customer.order_count}</td>
                      <td className="num strong">{money(customer.lifetime_value)}</td>
                      <td>
                        <StatusBadge value={customer.status} />
                      </td>
                      {canWrite ? (
                        <td>
                          <div className="row-actions">
                            <IconButton icon="edit" label="Edit customer" onClick={() => openEdit(customer)} />
                            <IconButton icon="trash" label="Delete customer" variant="danger" onClick={() => setToDelete(customer)} />
                          </div>
                        </td>
                      ) : null}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination meta={data.meta} onPage={setPage} />
          </>
        )}
      </Card>

      <Modal
        open={Boolean(editing)}
        title={editing?.id ? 'Edit customer' : 'Add customer'}
        subtitle={editing?.id ? `Joined ${formatDate(editing.created_at)}` : 'Create a retail, trade or wholesale account.'}
        onClose={() => setEditing(null)}
        footer={
          <>
            <Button onClick={() => setEditing(null)} disabled={saving}>
              Cancel
            </Button>
            <Button variant="primary" onClick={save} loading={saving}>
              {editing?.id ? 'Save changes' : 'Create customer'}
            </Button>
          </>
        }
      >
        <form className="form-grid" onSubmit={save}>
          <Field label="Full name" required>
            <Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required />
          </Field>
          <Field label="Company / project">
            <Input value={form.company} onChange={(event) => setForm({ ...form, company: event.target.value })} />
          </Field>
          <Field label="Email">
            <Input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
          </Field>
          <Field label="Phone">
            <Input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} placeholder="+92 300 0000000" />
          </Field>
          <Field label="City">
            <Input value={form.city} onChange={(event) => setForm({ ...form, city: event.target.value })} />
          </Field>
          <Field label="Country">
            <Input value={form.country} onChange={(event) => setForm({ ...form, country: event.target.value })} />
          </Field>
          <Field label="Account type">
            <Select value={form.type} onChange={(event) => setForm({ ...form, type: event.target.value })} options={CUSTOMER_TYPES} />
          </Field>
          <Field label="Status">
            <Select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} options={['active', 'inactive']} />
          </Field>
          <Field label="Notes" className="span-2">
            <Textarea value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} placeholder="Preferred finishes, delivery constraints, account terms…" />
          </Field>
          <button type="submit" hidden />
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(toDelete)}
        title="Delete this customer?"
        message={toDelete ? `“${toDelete.name}” will be removed. Their existing orders stay in the book but will show as walk-in.` : ''}
        confirmLabel="Delete customer"
        busy={deleting}
        onConfirm={confirmDelete}
        onClose={() => setToDelete(null)}
      />
    </>
  );
}
