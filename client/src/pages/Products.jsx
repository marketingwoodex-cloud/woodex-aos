import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { del, patch, post } from '../lib/api.js';
import { useDebounced, useFetch } from '../lib/hooks.js';
import { useAuth } from '../state/AuthContext.jsx';
import { useToast } from '../state/ToastContext.jsx';
import { money, number, statusMeta } from '../lib/format.js';
import {
  Badge, Button, Card, ConfirmDialog, EmptyState, ErrorState, Field, IconButton, Input,
  Modal, PageHeader, Pagination, SearchInput, Select, Spinner, StatusBadge, Textarea,
} from '../components/ui.jsx';
import Icon from '../components/icons.jsx';

const BLANK = {
  name: '', sku: '', categoryId: '', price: '', cost: '', stock: '0',
  reorderLevel: '5', status: 'active', material: '', finish: '', description: '',
};

export default function Products() {
  const { canWrite } = useAuth();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounced(search);
  const [category, setCategory] = useState('all');
  const [status, setStatus] = useState('all');
  const [stock, setStock] = useState('all');
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(BLANK);
  const [saving, setSaving] = useState(false);
  const [toDelete, setToDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => setPage(1), [debouncedSearch, category, status, stock]);

  const query = new URLSearchParams({ page: String(page), limit: '10' });
  if (debouncedSearch) query.set('search', debouncedSearch);
  if (category !== 'all') query.set('category', category);
  if (status !== 'all') query.set('status', status);
  if (stock === 'low') query.set('stock', 'low');

  const { data, loading, error, refresh } = useFetch(`/products?${query.toString()}`);
  const { data: categoryData } = useFetch('/products/categories');

  const categories = categoryData?.data ?? [];
  const products = data?.data ?? [];

  useEffect(() => {
    if (searchParams.get('new') === '1' && canWrite) {
      openCreate();
      searchParams.delete('new');
      setSearchParams(searchParams, { replace: true });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]);

  function openCreate() {
    setEditing({ id: null });
    setForm({ ...BLANK, categoryId: categories[0]?.id ? String(categories[0].id) : '' });
  }

  function openEdit(product) {
    setEditing(product);
    setForm({
      name: product.name,
      sku: product.sku,
      categoryId: product.category_id ? String(product.category_id) : '',
      price: String(product.price),
      cost: String(product.cost),
      stock: String(product.stock),
      reorderLevel: String(product.reorder_level),
      status: product.status,
      material: product.material ?? '',
      finish: product.finish ?? '',
      description: product.description ?? '',
    });
  }

  async function save(event) {
    event.preventDefault();
    setSaving(true);
    try {
      const body = {
        ...form,
        categoryId: form.categoryId || null,
        price: Number(form.price),
        cost: Number(form.cost),
        stock: Number(form.stock),
        reorderLevel: Number(form.reorderLevel),
      };
      if (editing?.id) {
        await patch(`/products/${editing.id}`, body);
        toast.success('Product updated.');
      } else {
        await post('/products', body);
        toast.success('Product added to the catalogue.');
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
      await del(`/products/${toDelete.id}`);
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
        title="Product catalogue"
        description="Everything Woodex builds and sells — pricing, stock position and production status in one table."
        actions={
          canWrite ? (
            <Button variant="primary" icon="plus" onClick={openCreate}>
              Add product
            </Button>
          ) : null
        }
      />

      <Card>
        <div className="toolbar">
          <SearchInput value={search} onChange={setSearch} placeholder="Search name, SKU or material…" />
          <Select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
            options={[{ value: 'all', label: 'All categories' }, ...categories.map((item) => ({ value: item.id, label: `${item.name} (${item.product_count})` }))]}
            style={{ width: 200 }}
          />
          <Select
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            options={[
              { value: 'all', label: 'All statuses' },
              { value: 'active', label: 'Active' },
              { value: 'draft', label: 'Draft' },
              { value: 'archived', label: 'Archived' },
            ]}
            style={{ width: 150 }}
          />
          <Button
            variant={stock === 'low' ? 'primary' : 'secondary'}
            icon="alert"
            onClick={() => setStock(stock === 'low' ? 'all' : 'low')}
          >
            Low stock
          </Button>
          <span className="muted small" style={{ marginLeft: 'auto' }}>
            {data?.meta ? `${number(data.meta.total)} products` : ' '}
          </span>
        </div>

        {loading && !data ? (
          <Spinner label="Loading catalogue…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : products.length === 0 ? (
          <EmptyState
            icon="box"
            title="No products match this view"
            description="Adjust your filters, or add a new piece to the catalogue."
            action={canWrite ? <Button variant="primary" icon="plus" onClick={openCreate}>Add product</Button> : null}
          />
        ) : (
          <>
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th className="num">Price</th>
                    <th className="num">Stock</th>
                    <th>Status</th>
                    {canWrite ? <th className="num">Actions</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {products.map((product) => {
                    const low = product.stock <= product.reorder_level;
                    return (
                      <tr key={product.id}>
                        <td>
                          <div className="cell-main">
                            <span className="cell-thumb">
                              <Icon name="box" size={17} />
                            </span>
                            <div className="grow">
                              <div className="cell-title truncate">{product.name}</div>
                              <div className="cell-sub mono">{product.sku}</div>
                            </div>
                          </div>
                        </td>
                        <td className="muted">{product.category ?? 'Uncategorised'}</td>
                        <td className="num">{money(product.price)}</td>
                        <td className="num">
                          <Badge tone={low ? 'danger' : 'neutral'}>
                            {product.stock} {low ? '· low' : 'in stock'}
                          </Badge>
                        </td>
                        <td>
                          <StatusBadge value={product.status} />
                        </td>
                        {canWrite ? (
                          <td>
                            <div className="row-actions">
                              <IconButton icon="edit" label="Edit product" onClick={() => openEdit(product)} />
                              <IconButton icon="trash" label="Delete product" variant="danger" onClick={() => setToDelete(product)} />
                            </div>
                          </td>
                        ) : null}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <Pagination meta={data.meta} onPage={setPage} />
          </>
        )}
      </Card>

      <Modal
        open={Boolean(editing)}
        title={editing?.id ? 'Edit product' : 'Add product'}
        subtitle={editing?.id ? `Updating ${editing.sku}` : 'Add a piece to the Woodex catalogue.'}
        onClose={() => setEditing(null)}
        size="lg"
        footer={
          <>
            <Button onClick={() => setEditing(null)} disabled={saving}>
              Cancel
            </Button>
            <Button variant="primary" onClick={save} loading={saving}>
              {editing?.id ? 'Save changes' : 'Create product'}
            </Button>
          </>
        }
      >
        <form className="form-grid" onSubmit={save}>
          <Field label="Product name" required className="span-2">
            <Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Alder Lounge Chair" required />
          </Field>
          <Field label="SKU" required>
            <Input value={form.sku} onChange={(event) => setForm({ ...form, sku: event.target.value })} placeholder="WD-SOF-101" required />
          </Field>
          <Field label="Category">
            <Select
              value={form.categoryId}
              onChange={(event) => setForm({ ...form, categoryId: event.target.value })}
              options={[{ value: '', label: 'Uncategorised' }, ...categories.map((item) => ({ value: item.id, label: item.name }))]}
            />
          </Field>
          <Field label="Retail price (PKR)" required>
            <Input type="number" min="0" value={form.price} onChange={(event) => setForm({ ...form, price: event.target.value })} required />
          </Field>
          <Field label="Unit cost (PKR)">
            <Input type="number" min="0" value={form.cost} onChange={(event) => setForm({ ...form, cost: event.target.value })} />
          </Field>
          <Field label="Stock on hand">
            <Input type="number" min="0" value={form.stock} onChange={(event) => setForm({ ...form, stock: event.target.value })} />
          </Field>
          <Field label="Reorder level" hint="Flags the line when stock drops to this number.">
            <Input type="number" min="0" value={form.reorderLevel} onChange={(event) => setForm({ ...form, reorderLevel: event.target.value })} />
          </Field>
          <Field label="Material">
            <Input value={form.material} onChange={(event) => setForm({ ...form, material: event.target.value })} placeholder="Solid sheesham" />
          </Field>
          <Field label="Finish">
            <Input value={form.finish} onChange={(event) => setForm({ ...form, finish: event.target.value })} placeholder="Natural oil" />
          </Field>
          <Field label="Status">
            <Select
              value={form.status}
              onChange={(event) => setForm({ ...form, status: event.target.value })}
              options={['active', 'draft', 'archived']}
            />
          </Field>
          <Field label="Description" className="span-2">
            <Textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="Short buyer-facing description…" />
          </Field>
          <button type="submit" hidden />
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(toDelete)}
        title="Delete this product?"
        message={toDelete ? `“${toDelete.name}” (${toDelete.sku}) will be removed from the catalogue. Past order lines keep their recorded name and price.` : ''}
        confirmLabel="Delete product"
        busy={deleting}
        onConfirm={confirmDelete}
        onClose={() => setToDelete(null)}
      />
    </>
  );
}
