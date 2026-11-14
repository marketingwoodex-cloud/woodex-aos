import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Icon from './icons.jsx';
import { money } from '../lib/format.js';

export function ProductImage({ product, url, alt, className = '' }) {
  const [failed, setFailed] = useState(false);
  const src = url || null;

  if (!src || failed) {
    const hue = [...(product?.name || 'Woodex')].reduce((sum, char) => sum + char.charCodeAt(0), 0) % 360;
    return (
      <div className={`img-fallback ${className}`.trim()} style={{ background: `linear-gradient(140deg, hsl(${hue} 28% 88%), hsl(${(hue + 28) % 360} 22% 94%))` }}>
        <Icon name="box" size={26} />
        <span>{product?.name ?? 'Woodex'}</span>
      </div>
    );
  }

  return (
    <img
      className={className}
      src={src}
      alt={alt || product?.name || ''}
      loading="lazy"
      onError={() => setFailed(true)}
    />
  );
}

export function Price({ product, currency = 'PKR', large = false }) {
  const showOld = product.oldPrice > product.price && product.oldPrice > 0;
  return (
    <div className={`price ${large ? 'price-lg' : ''}`}>
      <span className="price-now">{product.price > 0 ? money(product.price, currency) : 'Price on request'}</span>
      {showOld ? <span className="price-old">{money(product.oldPrice, currency)}</span> : null}
      {product.discountPercent > 0 ? <span className="tag tag-sale">-{product.discountPercent}%</span> : null}
    </div>
  );
}

export function StockTag({ product }) {
  if (!product.isActive) return <span className="tag tag-muted">Hidden</span>;
  if (product.stock > 0) return <span className="tag tag-stock">In stock</span>;
  return <span className="tag tag-out">Made to order</span>;
}

export default function StorefrontShell({ children, footer = {}, categories = [] }) {
  const navigate = useNavigate();
  const [term, setTerm] = useState('');

  const submitSearch = (event) => {
    event.preventDefault();
    navigate(`/shop?q=${encodeURIComponent(term.trim())}`);
  };

  const roots = categories.filter((category) => category.level === 1).slice(0, 5);

  return (
    <div className="shop-shell">
      <header className="shop-top">
        <div className="shop-top-inner">
          <Link to="/shop" className="shop-brand">
            <span className="shop-brand-mark">W</span>
            <span className="shop-brand-text">
              <strong>Woodex</strong>
              <small>Furniture · {footer.tagline || 'Make your space work®'}</small>
            </span>
          </Link>

          <nav className="shop-nav">
            <Link to="/shop">Shop all</Link>
            {roots.map((category) => (
              <Link key={category.id} to={`/shop?c=${category.slug}`}>
                {category.name}
              </Link>
            ))}
          </nav>

          <form className="shop-search" onSubmit={submitSearch}>
            <Icon name="search" size={16} />
            <input
              value={term}
              onChange={(event) => setTerm(event.target.value)}
              placeholder="Search the catalogue…"
              aria-label="Search the catalogue"
            />
          </form>

          <Link to="/live" className="shop-admin-link">
            <Icon name="grid" size={15} /> Admin
          </Link>
        </div>
      </header>

      <main className="shop-main">{children}</main>

      <footer className="shop-footer">
        <div className="shop-footer-grid">
          <div>
            <h3>{footer.tagline || 'Make your space work®'}</h3>
            <div className="shop-footer-about" dangerouslySetInnerHTML={{ __html: footer.about || '' }} />
            {footer.phone ? (
              <p className="shop-footer-line">
                <Icon name="phone" size={14} /> {footer.phone}
              </p>
            ) : null}
            {footer.hours ? <p className="shop-footer-line">{footer.hours}</p> : null}
          </div>
          <div>
            <h4>Shop</h4>
            <ul>
              {roots.map((category) => (
                <li key={category.id}>
                  <Link to={`/shop?c=${category.slug}`}>{category.name}</Link>
                </li>
              ))}
              <li>
                <Link to="/shop?flag=best">Best sellers</Link>
              </li>
              <li>
                <Link to="/shop?flag=sale">On sale</Link>
              </li>
            </ul>
          </div>
          <div>
            <h4>More</h4>
            <ul>
              <li>
                <Link to="/shop">Catalogue</Link>
              </li>
              <li>
                <Link to="/live">Admin panel</Link>
              </li>
              <li>
                <Link to="/dashboard">Operations workspace</Link>
              </li>
            </ul>
          </div>
          <div>
            <h4>Visit</h4>
            <p className="shop-footer-line">{footer.address || 'Lahore, Pakistan'}</p>
            <p className="shop-footer-line">{footer.email || ''}</p>
          </div>
        </div>
        <div className="shop-footer-base">
          <span>{footer.copyright || '© Woodex Furniture'}</span>
          <span className="shop-footer-note">
            Preview rendered from the imported cPanel database · prices in PKR
          </span>
        </div>
      </footer>
    </div>
  );
}
