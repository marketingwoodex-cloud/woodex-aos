<?php
// review.php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

// 1. Get the order number from the URL
// e.g., review.php?order=ORDER-ABC123
// or use an .htaccess rewrite if you want /review/ORDER-ABC123
$orderNumber = $_GET['order'] ?? null;
if (!$orderNumber) {
    die("No order number provided.");
}

// 2. Fetch order items to display
$stmt = $conn->prepare("
    SELECT oi.product_id, oi.product_name, oi.price, oi.quantity, p.rating_count, p.rating_sum
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->bind_param('s', $orderNumber);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// 3. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We expect arrays: rating[], review[], product_id[]
    $ratings   = $_POST['rating']   ?? [];
    $reviews   = $_POST['review']   ?? [];
    $prodIds   = $_POST['product_id'] ?? [];

    // Insert each product's review
    for ($i = 0; $i < count($prodIds); $i++) {
        $pid    = (int)$prodIds[$i];
        $rate   = (int)$ratings[$i];
        $review = trim($reviews[$i] ?? '');

        // 3.1 Insert into `reviews`
        $stmtIns = $conn->prepare("
            INSERT INTO reviews (order_number, product_id, rating, review_text)
            VALUES (?, ?, ?, ?)
        ");
        $stmtIns->bind_param('siis', $orderNumber, $pid, $rate, $review);
        $stmtIns->execute();
        $stmtIns->close();

        // 3.2 Update the product rating_count, rating_sum
        // rating_count += 1, rating_sum += rate
        $stmtUpd = $conn->prepare("
            UPDATE products
            SET rating_count = rating_count + 1,
                rating_sum   = rating_sum + ?
            WHERE id = ?
        ");
        $stmtUpd->bind_param('ii', $rate, $pid);
        $stmtUpd->execute();
        $stmtUpd->close();
    }

    // After all reviews are stored, show a thank-you message or redirect
    header("Location: " . $domain . "thanks-for-the-review");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Review Your Order</title>
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <link 
    href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.3/font/bootstrap-icons.min.css" 
    rel="stylesheet"
  >
  <link href="<?php echo $domain; ?>assets/style.css" rel="stylesheet">
</head>
<body>

<?php include_once('header/header.php'); ?>

<section class="review-info-section">
  <div class="container">
    <!-- Maybe show some info or your contact cards if you'd like -->
    <div class="row mb-4">
      <div class="col text-center">
        <h1>Review Your Order</h1>
        <p class="text-muted">Order Number: <?php echo htmlspecialchars($orderNumber); ?></p>
        <p>Please share your feedback on each product you purchased.</p>
      </div>
    </div>

    <?php if (empty($items)): ?>
      <div class="alert alert-warning">
        No items found for this order.
      </div>
    <?php else: ?>
      <form method="POST" class="row">
        <?php foreach ($items as $index => $item): ?>
          <div class="col-md-6 mb-4">
            <div class="card p-3">
              <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
              <p class="mb-2">
                <strong>Price:</strong> 
                ₨ <?php echo number_format($item['price'], 2); ?><br>
                <strong>Quantity:</strong> <?php echo (int)$item['quantity']; ?>
              </p>
              
              <!-- Hidden product_id field -->
              <input 
                type="hidden" 
                name="product_id[]" 
                value="<?php echo (int)$item['product_id']; ?>"
              >

              <!-- Rating select (1-5) -->
              <div class="mb-2">
                <label for="rating_<?php echo $index; ?>" class="form-label">Your Rating (1=Poor, 5=Excellent)</label>
                <select 
                  name="rating[]" 
                  id="rating_<?php echo $index; ?>" 
                  class="form-select" 
                  required
                >
                  <option value="5">5</option>
                  <option value="4">4</option>
                  <option value="3">3</option>
                  <option value="2">2</option>
                  <option value="1">1</option>
                </select>
              </div>

              <!-- Review text -->
              <div class="mb-2">
                <label for="review_<?php echo $index; ?>" class="form-label">Review (Optional)</label>
                <textarea 
                  name="review[]" 
                  id="review_<?php echo $index; ?>" 
                  class="form-control" 
                  rows="3"
                ></textarea>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            Submit Reviews
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php include_once('footer/footer.php'); ?>

<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js">
</script>
<!-- jQuery if needed -->
</body>
</html>
