import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import StorefrontShell, { Price, ProductImage, StockTag } from '../../components/StorefrontShell.jsx';
import { useFetch } from '../../lib/hooks.js';
import { money, number } from '../../lib/format.js';
import Icon from '../../components/icons.jsx';
import { Button, ErrorState, Spinner } from '../../components/ui.jsx';

export default function ProductPage() {
  const { slug } = useParams();
  const { data, loading, error, refresh } = useFetch(`/shop/products/${slug}`);
  const content = useFetch('/shop/content');
  const [activeImage, setActiveImage] = useState(0);

  if (loading && !data) {
    return (
      <StorefrontShell>
        <div className="shop-loading"><Spinner label="Loading product…" /></div>
      </StorefrontShell>
    );
  }

  if (error) {
    return (
      <StorefrontShell footer={content.data?.footer}>
        <ErrorState message={error} onRetry={refresh} />
      </StorefrontShell>
    );
  }

  const product = data?.data;
  if (!product) return null;

  const gallery = product.gallery ?? [];
  const current = gallery[activeImage] ?? null;
  const faqs = data.faqs ?? [];
  const categoryFaqs = data.categoryFaqs ?? [];
  const specs = product.specifications ?? [];
  const footer = content.data?.footer;

  return (
    <StorefrontShell footer={footer}>
      <nav className="breadcrumb">
        <Link to="/shop">Shop</Link>
        {(product.breadcrumb ?? []).map((crumb) => (
          <span key={crumb.id}>
            <Icon name="chevronRight" size={12} />
            <Link to={`/shop?c=${crumb.slug}`}>{crumb.name}</Link>
          </span>
        ))}
        <span>
          <Icon name="chevronRight" size={12} />
          <span className="breadcrumb-current">{product.name}</span>
        </span>
      </nav>

      <div className="pd-layout">
        <div className="pd-gallery">
          <div className="pd-main-image">
            <ProductImage product={product} url={current?.url} className="pd-img" />
            {product.isBestSeller ? <span className="tag tag-best pd-badge">Best seller</span> : null}
          </div>
          {gallery.length > 1 ? (
            <div className="pd-thumbs">
              {gallery.map((image, index) => (
                <button
                  key={image.path}
                  type="button"
                  className={`pd-thumb ${index === activeImage ? 'pd-thumb-active' : ''}`}
                  onClick={() => setActiveImage(index)}
                  aria-label={`Image ${index + 1}`}
                >
                  <ProductImage product={product} url={image.url} className="pd-thumb-img" />
                </button>
              ))}
            </div>
          ) : null}
        </div>

        <div className="pd-info">
          <p className="product-category">{product.categoryName ?? 'Woodex'}</p>
          <h1 className="pd-title">{product.name}</h1>
          <div className="pd-meta">
            <span className="mono small">SKU {product.sku}</span>
            {product.rating ? (
              <span className="pd-rating">
                <Icon name="sparkle" size={14} /> {product.rating} ({number(product.ratingCount)} reviews)
              </span>
            ) : null}
          </div>

          <div className="pd-price-row">
            <Price product={product} large />
            <StockTag product={product} />
          </div>

          {product.shortDescription ? (
            <div className="pd-short" dangerouslySetInnerHTML={{ __html: product.shortDescription }} />
          ) : null}

          <div className="pd-actions">
            <Link to="/live" className="btn btn-primary btn-lg">
              <Icon name="receipt" size={16} /> Request a quotation
            </Link>
            <a href={`mailto:${footer?.email || 'care@woodex.pk'}?subject=${encodeURIComponent(`Enquiry: ${product.name}`)}`} className="btn btn-lg">
              <Icon name="mail" size={16} /> Email the showroom
            </a>
          </div>

          <dl className="pd-facts">
            <div>
              <dt>Availability</dt>
              <dd>{product.stock > 0 ? `${number(product.stock)} in stock` : 'Made to order'}</dd>
            </div>
            <div>
              <dt>Brand</dt>
              <dd>{product.brand ?? 'Woodex'}</dd>
            </div>
            <div>
              <dt>Delivery</dt>
              <dd>3–5 weeks, installed by Woodex</dd>
            </div>
            <div>
              <dt>Warranty</dt>
              <dd>2 years on material and manufacturing</dd>
            </div>
          </dl>
        </div>
      </div>

      <div className="pd-detail-grid">
        <section className="pd-panel">
          <h2>Product details</h2>
          <div className="pd-rich" dangerouslySetInnerHTML={{ __html: product.details || '<p>No description recorded.</p>' }} />
        </section>

        <section className="pd-panel">
          <h2>Specifications</h2>
          {specs.length === 0 ? (
            <p className="muted small">No specifications recorded for this piece.</p>
          ) : (
            <table className="spec-table">
              <tbody>
                {specs.map((spec, index) => (
                  <tr key={index}>
                    <th>{spec.heading ?? `Spec ${index + 1}`}</th>
                    <td dangerouslySetInnerHTML={{ __html: spec.description ?? '' }} />
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          {product.videoEmbed ? (
            <p className="muted small">
              Video: <a href={product.videoEmbed} target="_blank" rel="noreferrer">watch the product film</a>
            </p>
          ) : null}
        </section>
      </div>

      {faqs.length ? (
        <section className="pd-panel">
          <h2>Questions about this piece</h2>
          <div className="faq-list">
            {faqs.map((faq) => (
              <details key={faq.id}>
                <summary>{faq.question}</summary>
                <div dangerouslySetInnerHTML={{ __html: faq.answer }} />
              </details>
            ))}
          </div>
        </section>
      ) : null}

      {categoryFaqs.length ? (
        <section className="pd-panel">
          <h2>About {product.categoryName} </h2>
          <div className="faq-list">
            {categoryFaqs.map((faq) => (
              <details key={faq.id}>
                <summary>{faq.question}</summary>
                <div dangerouslySetInnerHTML={{ __html: faq.answer }} />
              </details>
            ))}
          </div>
        </section>
      ) : null}

      {data.related?.length ? (
        <section className="shelf">
          <header className="shelf-head">
            <div>
              <p className="eyebrow">You may also like</p>
              <h2>More from {product.categoryName ?? 'Woodex'}</h2>
            </div>
          </header>
          <div className="product-grid">
            {data.related.map((item) => (
              <Link key={item.id} to={`/shop/p/${item.slug}`} className="product-card">
                <div className="product-media">
                  <ProductImage product={item} url={item.imageUrl} className="product-img" />
                </div>
                <div className="product-body">
                  <p className="product-category">{item.categoryName ?? 'Woodex'}</p>
                  <h3 className="product-name">{item.name}</h3>
                  <div className="product-bottom">
                    <Price product={item} />
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </section>
      ) : null}
    </StorefrontShell>
  );
}
