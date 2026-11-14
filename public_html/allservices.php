<?php
session_start();
include "lbpanel/config/connection.php";
function renderStars($average)
{
    // Simple approach: round to nearest integer
    $rounded = round($average);
    // Return that many stars, rest are blank
    $fullStars = str_repeat('★', $rounded);
    $emptyStars= str_repeat('☆', 5 - $rounded);
    return $fullStars . $emptyStars;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico">
   <title>Explore High-End Clothing at Streetwear Pakistan</title>
     <meta name="description" content="Experience the best in clothing with Streetwear Pakistan. Explore our categories.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" rel="stylesheet">
     <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
   <style>
/* Floating Animation */
@keyframes floatUpDown {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

/* Applying animation to floating icons */
.floating-icon img {
    animation: floatUpDown 3s infinite ease-in-out;
}

/* Position adjustment */
.closer {
    margin-top: -10px; /* Move closer icons slightly upward */
}

</style>
</head>
<body>
<?php  include_once('header/header.php'); ?>

<!--New Definitions End-->

<?php

// Fetch popular categories from the database
$query = "SELECT id, category_name, short_description, main_image, slug FROM categories ORDER BY id DESC"; // Adjust LIMIT as needed
$result = $conn->query($query);
function extractPTags($html) {
    $doc = new DOMDocument();
    
    // Suppress warnings caused by malformed HTML
    libxml_use_internal_errors(true);
    $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    
    $paragraphs = $doc->getElementsByTagName('p');
    $output = '';

    foreach ($paragraphs as $p) {
        $output .= $doc->saveHTML($p);
    }

    return $output;
}
?>

<section class="my-5">
    <!-- Popular Categories Section -->
    <div class="popular-treatments-section-v1 py-5">
        <div class="container text-center">
            <h1 class="product-section-title-v2 text-white mb-5">Explore Our Categories</h1>
            <div class="row">
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Extract treatment data
                        $catName = htmlspecialchars($row['category_name']);
                        $imageUrl = $domain.htmlspecialchars($row['main_image']);
                        $detailsLink = $domain.'categories/'.htmlspecialchars($row['slug']);
                        // Fallback image if none provided
                        if (empty($imageUrl)) {
                            $imageUrl = $domain.'assets/images/placeholder-treatment.png'; // Ensure you have a placeholder image
                        }
                        ?>
                        <div class="col-12 col-lg-4">
                            <div class="treatment-card-v1 mb-4">
                                <img src="<?= $imageUrl ?>" alt="<?= $catName ?>" class="treatment-image-v1">
                                <div class="treatment-overlay">
                                    <h3 class="pop-treatment-name"><?= $catName ?></h3>
                                    <?php if(!empty($row['short_description'])){?>
                                    <p><?= extractPTags($row['short_description']) ?></p>
                                    <?php  } ?>
                                    <a href="<?= $detailsLink ?>" class="view-details-button">Shop Now</a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<p>No popular categories available at the moment.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</section>
<?php
// Fetch products (limit to however many you want to display)
$query = "SELECT
  p.id,
  p.product_name,
  p.old_price,
  p.sold_quantity,
  p.current_price,
  p.images,
  p.slug,
  CASE 
    WHEN p.rating_count > 0 THEN p.rating_sum / p.rating_count
    ELSE 0
  END AS avg_rating,
  p.rating_count,
  COUNT(v.id)                           AS variation_count,
  MIN(v.price)                  AS min_price,
  MAX(v.price)                  AS max_price
FROM products p
LEFT JOIN product_variations v
  ON v.product_id = p.id
GROUP BY
  p.id,
  p.product_name,
  p.old_price,
  p.sold_quantity,
  p.current_price,
  p.images,
  p.slug,
  p.rating_count,
  p.rating_sum
ORDER BY p.id DESC
LIMIT 8";
$result = $conn->query($query);
?>

<!-- Product Section -->
<section class="home-products">
    <div class="product-section-v2">
        <div class="container">
            <h2 class="product-section-title-v2 text-center pb-3">🔥 New Arrivals</h2>

            <div class="product-row-v2 justify-content-start row">
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $productName   = htmlspecialchars($row['product_name']);
                        $oldPrice      = (float)$row['old_price'];
                        $singlePrice   = (float)$row['current_price']; // from products table
                        $minVarPrice   = isset($row['min_price']) ? (float)$row['min_price'] : null;
                        $maxVarPrice   = isset($row['max_price']) ? (float)$row['max_price'] : null;
                        $hasVariations = ((int)$row['variation_count']) > 0;
                        $proslug       = htmlspecialchars($row['slug']);
                        $sold          = (int)$row['sold_quantity'];

                        $images   = json_decode($row['images'], true);
                        $imageUrl = is_array($images) && count($images) > 0 ? htmlspecialchars($images[0]) : 'assets/images/placeholder.png';

                        // Price display logic
                        if ($hasVariations && $minVarPrice !== null && $maxVarPrice !== null) {
                            // If min == max, show single; else show range
                            if ($minVarPrice === $maxVarPrice) {
                                $priceDisplay = 'PKR ' . number_format($minVarPrice, 2);
                                $effectiveCurrentPriceForDiscount = $minVarPrice;
                            } else {
                                $priceDisplay = 'PKR ' . number_format($minVarPrice, 2) . ' – ' . number_format($maxVarPrice, 2);
                                // For badge, use the lowest price to show the maximum discount "up to"
                                $effectiveCurrentPriceForDiscount = $minVarPrice;
                            }
                        } else {
                            // No variations → use product's own price
                            $priceDisplay = 'PKR ' . number_format($singlePrice, 2);
                            $effectiveCurrentPriceForDiscount = $singlePrice;
                        }

                        // Discount badge:
                        // If we have an old price and it's higher than the effective price, show discount
                        $discount = ($oldPrice > 0 && $oldPrice > $effectiveCurrentPriceForDiscount)
                            ? round((($oldPrice - $effectiveCurrentPriceForDiscount) / $oldPrice) * 100)
                            : 0;

                        $avgRating    = (float)$row['avg_rating'];
                        $totalReviews = (int)$row['rating_count'];
                        ?>
                        <div class="col-lg-3 col-6 mb-4">
                            <div class="product-card-v2">
                                <div class="product-image-container-v2">
                                    <button type="button" class="wdx-quick-view-btn" data-slug="<?= $proslug ?>">
                                        <i class="bi bi-eye"></i> Quick View
                                    </button>
                                    <?php if ($discount > 0): ?>
                                        <div class="discount-badge-v2">
                                            <?= $hasVariations ? 'Up to ' . $discount . '% OFF' : $discount . '% OFF' ?>
                                        </div>
                                    <?php endif; ?>
                                    <a href="<?php echo $domain . 'shop/' . $proslug; ?>">
                                        <img loading="lazy" src="<?= $domain . $imageUrl ?>" class="product-image-v2" alt="<?= $productName ?>">
                                    </a>
                                </div>
                                <div class="product-details-v2">
                                    <h3 class="product-title-v2">
                                        <a href="<?php echo $domain . 'shop/' . $proslug; ?>"><?= $productName ?></a>
                                    </h3>

                                    <div class="product-rating-sold-v2">
                                        <span class="product-rating-v2">
                                            <?php if ((int)$totalReviews > 0): ?>
                                                <?= (int)$totalReviews ?> Reviews
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <p>
                                        <?php if ($discount > 0): ?>
                                            <span class="product-old-price-v2">PKR <?= number_format($oldPrice, 2) ?></span>
                                        <?php endif; ?>
                                        <span class="product-price-v2"><?= $priceDisplay ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<p>No products available at the moment.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</section>

<?php  include_once('footer/footer.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>

</body>
</html>
