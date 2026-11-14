import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { patch } from '../../lib/api.js';
import { useDebounced, useFetch } from '../../lib/hooks.js';
import { useAuth } from '../../state/AuthContext.jsx';
import { useToast } from '../../state/ToastContext.jsx';
import { money, number, relativeTime } from '../../lib/format.js';
import {
  Badge, Button, Card, EmptyState, ErrorState, Field, IconButton, Input, Modal, PageHeader,
  Pagination, SearchInput, Select, Spinner, Toggle,
} from '../../components/ui.jsx';
import Icon from '../../components/icons.jsx';

const FILTERS = [
  { value: 'all', label: 'All products' },
  { value: 'out', label: 'Out of stock' },
  { value: 'zero-price', label: 'Zero price' },
  { value: 'best', label: 'Best sellers' },
  { value: 'hidden', label: 'Hidden' },
];

export default function LiveCatalogue() {
  const { canWrite } = useAuth();
  const toast = useToast();

  const [search, setSearch] = useState('');
  const debounced = useDebounced(search);
  const [category, setCategory] = useState('all');
  const [filter, setFilter] = useState('all');
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ price: '', oldPrice: '', stock: '', isActive: true, isBestSeller: false, isSaleItem: false });
  const [saving, setSaving] = useState(false);

  useEffect(() => setPage(1), [debounced, category, filter]);

  const query = new URLSearchParams({ page: String(page), limit: '12' });
  if (debounced) query.set('search', debounced);
  if (category !== 'all') query.set('category', category);
  if (filter !== 'all') query.set('filter', filter);

  const { data, loading, error, refresh } = useFetch(`/live/products?${query.toString()}`);
  const { data: categoryData } = useFetch('/shop/categories');
  const categories = (categoryData?.flat ?? []).filter((item) => item.level <= 2 && item.product_count > 0);

  const products = data?.data ?? [];

  function openEdit(product) {
    setEditing(product);
    setForm({
      price: String(product.price),
      oldPrice: String(product.oldPrice ?? 0),
      stock: String(product.stock),
      isActive: product.isActive,
      isBestSeller: product.isBestSeller,
      isSaleItem: Boolean(product.isSaleItem),
    });
  }

  async function save() {
    setSaving(true);
    try {
      await patch(`/live/products/${editing.id}`, {
        price: Number(form.price),
        oldPrice: Number(form.oldPrice),
        stock: Number(form.stock),
        isActive: form.isActive,
        isBestSeller: form.isBestSeller,
        isSaleItem: form.isSaleItem,
      });
      toast.success(`Updated “${editing.name}” in the live catalogue.`);
      setEditing(null);
      refresh();
    } catch (err) {
      toast.error(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <PageHeader
        eyebrow="Live data"
        title="Catalogue"
        description="The real product table from your cPanel database. Edits here change the imported store, not your live server."
        actions={
          <Link to="/shop" target="_blank" rel="noreferrer" className="btn btn-secondary">
            <Icon name="external" size={15} /> View storefront
          </Link>
        }
      />

      <Card>
        <div className="toolbar">
          <SearchInput value={search} onChange={setSearch} placeholder="Search name or SKU…" />
          <Select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
            options={[{ value: 'all', label: 'All categories' }, ...categories.map((item) => ({ value: item.slug, label: item.name }))]}
            style={{ width: 200 }}
          />
          <div className="chips">
            {FILTERS.map((item) => (
              <button
                key={item.value}
                type="button"
                className={`chip ${filter === item.value ? 'chip-active' : ''}`}
                onClick={() => setFilter(item.value)}
              >
                {item.label}
              </button>
            ))}
          </div>
          <span className="muted small" style={{ marginLeft: 'auto' }}>
            {data?.meta ? `${number(data.meta.total)} products` : ' '}
          </span>
        </div>

        {loading && !data ? (
          <Spinner label="Loading live catalogue…" />
        ) : error ? (
          <ErrorState message={error} onRetry={refresh} />
        ) : products.length === 0 ? (
          <EmptyState icon="box" title="No products match" description="Clear the filters or search for something else." />
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
                    <th>Flags</th>
                    <th className="num">Updated</th>
                    {canWrite ? <th className="num">Edit</th> : null}
                  </tr>
                </thead>
                <tbody>
                  {products.map((product) => (
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
                      <td className="muted truncate">{product.categoryName ?? '—'}</td>
                      <td className="num">
                        {product.price > 0 ? money(product.price) : <span className="badge badge-danger">No price</span>}
                      </td>
                      <td className="num">
                        <Badge tone={product.stock > 0 ? 'success' : 'danger'}>{product.stock > 0 ? `${product.stock}` : '0'}</Badge>
                      </td>
                      <td>
                        <div className="row" style={{ gap: 4 }}>
                          {product.isBestSeller ? <Badge tone="accent">Best</Badge> : null}
                          {product.isSaleItem ? <Badge tone="info">Sale</Badge> : null}
                          {!product.isActive ? <Badge tone="neutral">Hidden</Badge> : null}
                          {!product.isBestSeller && !product.isSaleItem && product.isActive ? <span className="muted small">—</span> : null}
                        </div>
                      </td>
                      <td className="num muted small nowrap">{relativeTime(product.updatedAt)}</td>
                      {canWrite ? (
                        <td>
                          <div className="row-actions">
                            <IconButton icon="edit" label="Edit product" onClick={() => openEdit(product)} />
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
        title={editing ? editing.name : 'Edit product'}
        subtitle={editing ? `${editing.sku} · imported from ${editing.categoryName ?? 'the live catalogue'}` : ''}
        onClose={() => setEditing(null)}
        footer={
          <>
            <Button onClick={() => setEditing(null)} disabled={saving}>
              Cancel
            </Button>
            <Button variant="primary" onClick={save} loading={saving}>
              Save to live catalogue
            </Button>
          </>
        }
      >
        <div className="form-grid">
          <Field label="Selling price (PKR)" required>
            <Input type="number" min="0" value={form.price} onChange={(event) => setForm({ ...form, price: event.target.value })} />
          </Field>
          <Field label="Compare-at price" hint="Shown struck through when higher than the selling price.">
            <Input type="number" min="0" value={form.oldPrice} onChange={(event) => setForm({ ...form, oldPrice: event.target.value })} />
          </Field>
          <Field label="Stock on hand">
            <Input type="number" min="0" value={form.stock} onChange={(event) => setForm({ ...form, stock: event.target.value })} />
          </Field>
          <div className="stack-sm" style={{ alignSelf: 'end' }}>
            <Toggle checked={form.isActive} onChange={(value) => setForm({ ...form, isActive: value })} label="Visible on the storefront" />
            <Toggle checked={form.isBestSeller} onChange={(value) => setForm({ ...form, isBestSeller: value })} label="Best seller" />
            <Toggle checked={form.isSaleItem} onChange={(value) => setForm({ ...form, isSaleItem: value })} label="On sale" />
          </div>
        </div>
        <div className="notice notice-accent">
          <Icon name="alert" size={15} />
          <span>
            These edits are stored in the imported preview database. Your cPanel MySQL tables are not touched — export the SQL
            from <span className="mono">docs/database-audit.md</span> to apply them upstream.
          </span>
        </div>
      </Modal>
    </>
  );
}
