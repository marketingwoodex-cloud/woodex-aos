import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import StorefrontShell, { Price, ProductImage, StockTag } from '../../components/StorefrontShell.jsx';
import { useDebounced, useFetch } from '../../lib/hooks.js';
import { money, number } from '../../lib/format.js';
import Icon from '../../components/icons.jsx';
import { Button, EmptyState, ErrorState, Pagination, Select, Spinner } from '../../components/ui.jsx';

const SORTS = [
  { value: 'featured', label: 'Featured' },
  { value: 'newest', label: 'Newest first' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
  { value: 'name', label: 'Name A–Z' },
];

const FLAGS = [
  { value: '', label: 'Everything' },
  { value: 'best', label: 'Best sellers' },
  { value: 'sale', label: 'On sale' },
  { value: 'instock', label: 'In stock' },
];

function ProductCard({ product }) {
  return (
    <Link to={`/shop/p/${product.slug}`} className="product-card">
      <div className="product-media">
        <ProductImage product={product} url={product.imageUrl} className="product-img" />
        <div className="product-tags">
          {product.isBestSeller ? <span className="tag tag-best">Best seller</span> : null}
          {product.discountPercent > 0 ? <span className="tag tag-sale">-{product.discountPercent}%</span> : null}
        </div>
      </div>
      <div className="product-body">
        <p className="product-category">{product.categoryName ?? 'Woodex'}</p>
        <h3 className="product-name">{product.name}</h3>
        <div className="product-bottom">
          <Price product={product} />
          <StockTag product={product} />
        </div>
      </div>
    </Link>
  );
}

export default function Storefront() {
  const [params, setParams] = useSearchParams();
  const category = params.get('c') || 'all';
  const flag = params.get('flag') || '';
  const sort = params.get('sort') || 'featured';
  const page = Math.max(1, Number(params.get('page') || 1));
  const urlQuery = params.get('q') || '';

  const [search, setSearch] = useState(urlQuery);
  const debounced = useDebounced(search, 320);

  const home = useFetch('/shop/home');
  const { data: categoryData } = useFetch('/shop/categories');
  const { data: faqData } = useFetch('/shop/faqs?limit=6');

  useEffect(() => {
    setSearch(urlQuery);
  }, [urlQuery]);

  useEffect(() => {
    if (debounced === urlQuery) return;
    const next = new URLSearchParams(params);
    if (debounced) next.set('q', debounced);
    else next.delete('q');
    next.delete('page');
    setParams(next, { replace: true });
  }, [debounced]); // eslint-disable-line react-hooks/exhaustive-deps

  const query = new URLSearchParams({ limit: '12', page: String(page), sort });
  if (category !== 'all') query.set('category', category);
  if (flag) query.set('flag', flag);
  if (urlQuery) query.set('search', urlQuery);
  const products = useFetch(`/shop/products?${query.toString()}`);

  const flatCategories = categoryData?.flat ?? [];
  const browseCategories = useMemo(
    () => flatCategories.filter((item) => item.level <= 2 && item.product_count > 0),
    [flatCategories],
  );

  const setParam = (key, value) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value);
    else next.delete(key);
    if (key !== 'page') next.delete('page');
    setParams(next);
  };

  const loadingHome = home.loading && !home.data;
  if (loadingHome) return <div className="shop-loading"><Spinner label="Loading the Woodex catalogue…" /></div>;
  if (home.error) return <div className="shop-loading"><ErrorState message={home.error} onRetry={home.refresh} /></div>;

  const data = home.data ?? {};
  if (data.available === false) {
    return (
      <StorefrontShell>
        <EmptyState
          icon="box"
          title="Catalogue not imported yet"
          description="Run `npm run db:import` to load the woodexbaks database snapshot, then reload this page."
        />
      </StorefrontShell>
    );
  }

  const { hero = [], categories = [], shelves = {}, totals = {}, settings = {} } = data;
  const slide = hero[0];
  const isBrowsing = Boolean(urlQuery) || category !== 'all' || Boolean(flag) || page > 1;
  const activeCategory = products.data?.category;

  return (
    <StorefrontShell footer={settings} categories={categories}>
      {!isBrowsing && slide ? (
        <section className="hero">
          <div className="hero-copy">
            <p className="hero-kicker">{slide.kicker || 'Woodex Furniture'}</p>
            <h1 className="hero-title">
              {(slide.title || '').split('\n').map((line, index) => (
                <span key={index}>{line}</span>
              ))}
            </h1>
            {slide.description ? <p className="hero-desc">{slide.description}</p> : null}
            <div className="hero-actions">
              <Link to="/shop?flag=best" className="btn btn-primary btn-lg">
                Shop best sellers
              </Link>
              <Link to="/shop?c=office-furniture" className="btn btn-lg">
                Office collections
              </Link>
            </div>
            <div className="hero-stats">
              <div>
                <strong>{number(totals.products ?? 0)}</strong>
                <span>Pieces in catalogue</span>
              </div>
              <div>
                <strong>{number(totals.categories ?? 0)}</strong>
                <span>Categories</span>
              </div>
              <div>
                <strong>{number(totals.best_sellers ?? 0)}</strong>
                <span>Best sellers</span>
              </div>
            </div>
          </div>
          <div className="hero-media">
            <ProductImage url={slide.imageUrl} alt={slide.alt || 'Woodex furniture'} className="hero-img" />
          </div>
        </section>
      ) : null}

      {!isBrowsing ? (
        <section className="cat-rail">
          {categories.map((item) => (
            <Link key={item.id} to={`/shop?c=${item.slug}`} className="cat-card">
              <ProductImage url={item.imageUrl} product={{ name: item.name }} className="cat-img" />
              <div className="cat-body">
                <h3>{item.name}</h3>
                <p>{number(item.product_count)} pieces</p>
              </div>
            </Link>
          ))}
        </section>
      ) : null}

      {!isBrowsing && shelves.bestSellers?.length ? (
        <section className="shelf">
          <header className="shelf-head">
            <div>
              <p className="eyebrow">Curated by Woodex</p>
              <h2>Best sellers</h2>
            </div>
            <Link to="/shop?flag=best" className="shelf-link">
              View all <Icon name="chevronRight" size={15} />
            </Link>
          </header>
          <div className="product-grid">
            {shelves.bestSellers.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      ) : null}

      <section className="shelf" id="browse">
        <header className="shelf-head">
          <div>
            <p className="eyebrow">{activeCategory ? 'Collection' : 'Catalogue'}</p>
            <h2>{activeCategory ? activeCategory.name : 'Browse everything'}</h2>
            {activeCategory?.short_description ? (
              <p className="shelf-sub" dangerouslySetInnerHTML={{ __html: activeCategory.short_description }} />
            ) : null}
          </div>
          <div className="shop-toolbar">
            <div className="shop-search shop-search-inline">
              <Icon name="search" size={15} />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search the catalogue…"
                aria-label="Search products"
              />
            </div>
            <Select
              value={category}
              onChange={(event) => setParam('c', event.target.value === 'all' ? '' : event.target.value)}
              options={[{ value: 'all', label: 'All categories' }, ...browseCategories.map((item) => ({ value: item.slug, label: item.name }))]}
              style={{ minWidth: 190 }}
            />
            <Select
              value={sort}
              onChange={(event) => setParam('sort', event.target.value)}
              options={SORTS}
              style={{ minWidth: 170 }}
            />
          </div>
        </header>

        <div className="chips" style={{ marginBottom: 16 }}>
          {FLAGS.map((item) => (
            <button
              key={item.value || 'all'}
              type="button"
              className={`chip ${flag === item.value ? 'chip-active' : ''}`}
              onClick={() => setParam('flag', item.value)}
            >
              {item.label}
            </button>
          ))}
          {activeCategory ? (
            <button type="button" className="chip" onClick={() => setParam('c', '')}>
              Clear category ✕
            </button>
          ) : null}
          <span className="muted small" style={{ marginLeft: 'auto', alignSelf: 'center' }}>
            {products.data?.meta ? `${number(products.data.meta.total)} pieces` : ''}
          </span>
        </div>

        {products.loading && !products.data ? (
          <Spinner label="Loading products…" />
        ) : products.error ? (
          <ErrorState message={products.error} onRetry={products.refresh} />
        ) : (products.data?.data ?? []).length === 0 ? (
          <EmptyState
            icon="search"
            title="Nothing matches that"
            description="Try another category, clear the search, or browse the whole catalogue."
            action={
              <Button
                variant="primary"
                onClick={() => {
                  setSearch('');
                  setParams(new URLSearchParams());
                }}
              >
                Reset filters
              </Button>
            }
          />
        ) : (
          <>
            <div className="product-grid">
              {(products.data?.data ?? []).map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
            <Pagination meta={products.data?.meta} onPage={(next) => setParam('page', String(next))} />
          </>
        )}
      </section>

      {(faqData?.data ?? []).length ? (
        <section className="shelf">
          <header className="shelf-head">
            <div>
              <p className="eyebrow">From the showroom</p>
              <h2>Answers before you ask</h2>
            </div>
          </header>
          <div className="faq-list faq-grid">
            {faqData.data.map((faq) => (
              <details key={faq.id}>
                <summary>{faq.question}</summary>
                <div dangerouslySetInnerHTML={{ __html: faq.answer }} />
              </details>
            ))}
          </div>
        </section>
      ) : null}

      {!isBrowsing && shelves.onSale?.length ? (
        <section className="shelf">
          <header className="shelf-head">
            <div>
              <p className="eyebrow">Limited time</p>
              <h2>On sale</h2>
            </div>
            <Link to="/shop?flag=sale" className="shelf-link">
              View all <Icon name="chevronRight" size={15} />
            </Link>
          </header>
          <div className="product-grid">
            {shelves.onSale.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      ) : null}

      {!isBrowsing ? (
        <section className="shop-banner">
          <div>
            <h2>Design-to-delivery for offices, homes and hospitality</h2>
            <p>
              {number(totals.products ?? 0)} pieces across {number(totals.categories ?? 0)} categories, custom-built in the
              Woodex workshop and installed by our own team.
            </p>
          </div>
          <Link to="/shop?c=series" className="btn btn-primary btn-lg">
            Explore collections
          </Link>
        </section>
      ) : null}
    </StorefrontShell>
  );
}
