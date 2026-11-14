/**
 * Public storefront API — serves the imported woodexbaks catalogue.
 * No session required: this is the customer-facing surface.
 */
import { Router } from 'express';
import { asyncHandler, pagination, listResponse, toInt } from '../lib/http.js';
import {
  categoryBreadcrumb, categorySubtree, liveAll, liveAvailable, liveGet, liveMeta,
  liveSettings, mediaUrl, parseJsonColumn, presentProduct,
} from '../db/live.js';

const router = Router();

const PRODUCT_SELECT = `
  SELECT p.*, c.name AS category_name, c.slug AS category_slug
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
`;

function emptyResponse(res, extra = {}) {
  res.json({
    available: false,
    message: 'The live catalogue has not been imported yet. Run: npm run db:import',
    data: [],
    meta: { total: 0, page: 1, limit: 12, pages: 1 },
    ...extra,
  });
}

router.get(
  '/meta',
  asyncHandler(async (_req, res) => {
    res.json({ available: liveAvailable(), ...liveMeta() });
  }),
);

/** Home page payload: hero, category rail, merchandising shelves. */
router.get(
  '/home',
  asyncHandler(async (_req, res) => {
    if (!liveAvailable()) return emptyResponse(res);

    const hero = liveAll(
      'SELECT id, kicker, title, description, cta_label, cta_url, image, alt FROM hero_slides WHERE published = 1 ORDER BY id',
    ).map((slide) => ({ ...slide, imageUrl: mediaUrl(slide.image) }));

    const categories = liveAll(`
      SELECT c.id, c.name, c.slug, c.level, c.main_image, c.short_description,
             (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) AS product_count
        FROM categories c
       WHERE c.is_active = 1 AND c.level = 1
       ORDER BY c.sort_order, c.name
    `).map((category) => ({ ...category, imageUrl: mediaUrl(category.main_image) }));

    const shelves = {
      bestSellers: liveAll(
        `${PRODUCT_SELECT} WHERE p.is_active = 1 AND p.is_best_seller = 1 ORDER BY p.id LIMIT 8`,
      ).map(presentProduct),
      onSale: liveAll(
        `${PRODUCT_SELECT} WHERE p.is_active = 1 AND (p.is_sale_item = 1 OR p.old_price > p.price) ORDER BY p.id LIMIT 8`,
      ).map(presentProduct),
      inStock: liveAll(
        `${PRODUCT_SELECT} WHERE p.is_active = 1 AND p.stock > 0 ORDER BY p.id DESC LIMIT 8`,
      ).map(presentProduct),
    };

    const totals = liveGet(`
      SELECT (SELECT COUNT(*) FROM products WHERE is_active = 1) AS products,
             (SELECT COUNT(*) FROM categories WHERE is_active = 1) AS categories,
             (SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock > 0) AS in_stock,
             (SELECT COUNT(*) FROM products WHERE is_active = 1 AND is_best_seller = 1) AS best_sellers
    `);

    res.json({
      available: true,
      hero,
      categories,
      shelves,
      totals,
      settings: liveSettings(),
      footerLinks: liveAll('SELECT section, title, url FROM footer_links ORDER BY section, sort_order'),
      meta: liveMeta(),
    });
  }),
);

router.get(
  '/categories',
  asyncHandler(async (_req, res) => {
    if (!liveAvailable()) return emptyResponse(res);
    const rows = liveAll(`
      SELECT c.id, c.parent_id, c.level, c.name, c.slug, c.path, c.main_image, c.short_description,
             (SELECT COUNT(*) FROM product_categories pc WHERE pc.category_id = c.id) AS product_count
        FROM categories c
       WHERE c.is_active = 1
       ORDER BY c.level, c.sort_order, c.name
    `).map((category) => ({ ...category, imageUrl: mediaUrl(category.main_image) }));

    const byId = new Map(rows.map((row) => [row.id, { ...row, children: [] }]));
    const tree = [];
    for (const node of byId.values()) {
      if (node.parent_id && byId.has(node.parent_id)) byId.get(node.parent_id).children.push(node);
      else if (!node.parent_id) tree.push(node);
    }
    res.json({ data: tree, flat: rows });
  }),
);

/** Product listing with category subtree, search, merchandising flags and sorting. */
router.get(
  '/products',
  asyncHandler(async (req, res) => {
    if (!liveAvailable()) return emptyResponse(res);

    const { page, limit, offset } = pagination(req, 12, 48);
    const filters = ['p.is_active = 1'];
    const params = [];

    if (req.query.category && req.query.category !== 'all') {
      const slug = String(req.query.category);
      const ids = categorySubtree(slug);
      if (!ids.length) {
        return res.json(listResponse([], 0, { page, limit }));
      }
      const list = ids.join(',');
      filters.push(`(p.category_id IN (${list}) OR EXISTS (
        SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN (${list})
      ))`);
    }

    if (req.query.search) {
      const term = `%${String(req.query.search).trim()}%`;
      filters.push('(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)');
      params.push(term, term, term);
    }

    if (req.query.flag === 'best') filters.push('p.is_best_seller = 1');
    if (req.query.flag === 'sale') filters.push('(p.is_sale_item = 1 OR p.old_price > p.price)');
    if (req.query.flag === 'instock') filters.push('p.stock > 0');

    const sortMap = {
      featured: 'p.is_best_seller DESC, p.stock > 0 DESC, p.id ASC',
      newest: 'p.id DESC',
      price_asc: 'p.price ASC',
      price_desc: 'p.price DESC',
      name: 'p.name ASC',
      stock: 'p.stock DESC',
    };
    const orderBy = sortMap[String(req.query.sort || 'featured')] || sortMap.featured;

    const where = `WHERE ${filters.join(' AND ')}`;
    const total = liveGet(`SELECT COUNT(*) AS c FROM products p ${where}`, params)?.c ?? 0;
    const rows = liveAll(`${PRODUCT_SELECT} ${where} ORDER BY ${orderBy} LIMIT ? OFFSET ?`, [
      ...params, limit, offset,
    ]);

    const category = req.query.category && req.query.category !== 'all'
      ? liveGet('SELECT id, name, slug, short_description, long_description, main_image, background_image, parent_id FROM categories WHERE slug = ?', [String(req.query.category)])
      : null;

    res.json({
      available: true,
      ...listResponse(rows.map(presentProduct), total, { page, limit }),
      category: category
        ? { ...category, imageUrl: mediaUrl(category.main_image), breadcrumb: categoryBreadcrumb(category.id) }
        : null,
    });
  }),
);

router.get(
  '/products/:slug',
  asyncHandler(async (req, res) => {
    if (!liveAvailable()) return emptyResponse(res);
    const row = liveGet(`${PRODUCT_SELECT} WHERE p.slug = ? AND p.is_active = 1`, [String(req.params.slug)]);
    if (!row) return res.status(404).json({ error: 'Product not found.' });

    const product = presentProduct(row);
    product.specifications = parseJsonColumn(row.specs_json, []);
    product.details = row.details;
    product.videoEmbed = row.video_embed;
    product.gallery = product.images.map((path) => ({ path, url: mediaUrl(path) }));
    product.breadcrumb = categoryBreadcrumb(row.category_id);

    const faqs = liveAll(
      "SELECT id, question, answer FROM faqs WHERE scope = 'product' AND item_id = ? ORDER BY id",
      [row.id],
    );

    const related = liveAll(
      `${PRODUCT_SELECT} WHERE p.is_active = 1 AND p.id != ? AND p.category_id = ? ORDER BY p.is_best_seller DESC, p.id LIMIT 4`,
      [row.id, row.category_id],
    ).map(presentProduct);

    // FAQs are stored per category; include the product's category and every
    // ancestor so a level-3 product still inherits the collection's answers.
    const categoryIds = [...new Set([row.category_id, ...product.breadcrumb.map((crumb) => crumb.id)].filter(Boolean))];
    const categoryFaqs = categoryIds.length
      ? liveAll(
          `SELECT id, question, answer FROM faqs
            WHERE scope = 'category' AND item_id IN (${categoryIds.map(() => '?').join(',')})
            ORDER BY id LIMIT 6`,
          categoryIds,
        )
      : [];

    res.json({ available: true, data: product, faqs, categoryFaqs, related });
  }),
);

/** Category-level FAQs — the showroom's answers about delivery, fabrics, lead times. */
router.get(
  '/faqs',
  asyncHandler(async (req, res) => {
    if (!liveAvailable()) return emptyResponse(res);

    const limit = Math.min(24, Math.max(1, toInt(req.query.limit, 6)));
    const params = [];
    let where = "f.scope = 'category'";

    if (req.query.category && req.query.category !== 'all') {
      const slug = String(req.query.category);
      const node = liveGet('SELECT id FROM categories WHERE slug = ?', [slug]);
      if (!node) return res.json({ data: [] });
      // A collection page shows its own FAQs plus those answered for its parents.
      const ancestors = categoryBreadcrumb(node.id).map((crumb) => crumb.id);
      const ids = [...new Set([...categorySubtree(slug), ...ancestors])];
      where += ` AND f.item_id IN (${ids.map(() => '?').join(',')})`;
      params.push(...ids);
    }

    const data = liveAll(
      `SELECT f.id, f.question, f.answer, f.item_id, c.name AS category_name, c.slug AS category_slug
         FROM faqs f JOIN categories c ON c.id = f.item_id
        WHERE ${where}
        ORDER BY f.id LIMIT ?`,
      [...params, limit],
    );
    res.json({ data });
  }),
);

router.get(
  '/content',
  asyncHandler(async (_req, res) => {
    if (!liveAvailable()) return emptyResponse(res);
    res.json({
      available: true,
      blogs: liveAll('SELECT id, title, slug, meta_description, image, created_at FROM blogs ORDER BY created_at DESC').map((blog) => ({
        ...blog,
        imageUrl: mediaUrl(blog.image),
      })),
      hero: liveAll('SELECT id, kicker, title, description, cta_label, cta_url, image FROM hero_slides').map((slide) => ({
        ...slide,
        imageUrl: mediaUrl(slide.image),
      })),
      footer: liveSettings(),
      footerLinks: liveAll('SELECT section, title, url FROM footer_links ORDER BY section, sort_order'),
    });
  }),
);

export default router;
