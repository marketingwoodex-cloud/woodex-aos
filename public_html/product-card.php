<?php
$productName = htmlspecialchars($row['product_name']);
$proslug     = htmlspecialchars($row['slug']);
$sold        = (int)$row['sold_quantity'];
$images      = json_decode($row['images'], true);
$imageUrl    = is_array($images) && count($images) > 0 ? htmlspecialchars($images[0]) : 'assets/images/placeholder.png';
$avgRating   = (float)$row['avg_rating'];
$totalReviews= (int)$row['rating_count'];
$discount    = renderDiscountPercent($row);
$priceText   = renderPriceRange($row);
$isSaleItem  = ((int)($row['is_sale_item'] ?? 0) === 1);
$categoryLabel = !empty($row['category_name']) ? strtoupper((string)$row['category_name']) : '';
$cardColClass = isset($cardColClass) && is_string($cardColClass) && trim($cardColClass) !== ''
  ? trim($cardColClass)
  : 'col-lg-3 col-md-4 col-sm-6 col-12 mb-4';
?>
<div class="<?= htmlspecialchars($cardColClass, ENT_QUOTES, 'UTF-8') ?>">
  <div class="product-card-v2">
    <div class="product-image-container-v2">
      <button type="button" class="wdx-quick-view-btn" data-slug="<?= $proslug ?>">
        <i class="bi bi-eye"></i> Quick View
      </button>
      <?php if ($isSaleItem): ?>
        <div class="sale-badge-v2">Sale</div>
      <?php endif; ?>
      <?php if ($discount > 0): ?>
        <div class="discount-badge-v2"><?= $discount ?>% OFF</div>
      <?php endif; ?>
      <a href="<?= $domain . 'shop/' . $proslug; ?>">
        <img loading="lazy" src="<?= $domain . $imageUrl ?>" class="product-image-v2" alt="<?= $productName ?>">
      </a>
    </div>
    <div class="product-details-v2">
      <?php if ($categoryLabel !== ''): ?>
      <div class="product-cat-v2"><?= htmlspecialchars($categoryLabel) ?></div>
      <?php endif; ?>
      <h3 class="product-title-v2">
        <a href="<?= $domain . 'shop/' . $proslug; ?>"><?= $productName ?></a>
      </h3>
      <?php if ((int)$totalReviews > 0): ?>
      <div class="product-rating-sold-v2">
        <span class="product-rating-v2"><?= (int)$totalReviews; ?> Reviews</span>
      </div>
      <?php endif; ?>
      <p class="mb-0">
        <?php if ($discount > 0 && !empty($row['old_price'])): ?>
          <span class="product-old-price-v2">PKR <?= number_format((float)$row['old_price'], 0) ?></span>
        <?php endif; ?>
        <span class="product-price-v2"><?= $priceText ?></span>
      </p>
    </div>
  </div>
</div>
