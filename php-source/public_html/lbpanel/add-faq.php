<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// 1. Load product list
$products = [];
$pstmt = $conn->prepare("SELECT id, product_name FROM products ORDER BY product_name ASC");
$pstmt->execute();
$presult = $pstmt->get_result();
while ($row = $presult->fetch_assoc()) {
    $products[] = $row;
}
$pstmt->close();

// 2. Load category list
$categories = [];
$tstmt = $conn->prepare("SELECT id, category_name FROM categories ORDER BY category_name ASC");
$tstmt->execute();
$tresult = $tstmt->get_result();
while ($row = $tresult->fetch_assoc()) {
    $categories[] = $row;
}
$tstmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faqFor  = $conn->real_escape_string($_POST['faq_for']);      // 'product' or 'category'
    $itemId  = (int)$_POST['item_id'];                            // The selected product/category ID
    $question= $conn->real_escape_string($_POST['question']);
    $answer  = $conn->real_escape_string($_POST['answer']);

    // Insert into faqs
    $stmt = $conn->prepare("
        INSERT INTO faqs (faq_for, item_id, question, answer, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('siss', $faqFor, $itemId, $question, $answer);

    if ($stmt->execute()) {
        $success_message = "FAQ added successfully!";
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add FAQ</title>
  <!-- Bootstrap CSS -->
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <link 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" 
    rel="stylesheet"
  >
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container form-container">
  <h2 class="form-header">Add New FAQ</h2>
  
  <!-- Display success / error messages -->
  <?php if (isset($success_message)): ?>
    <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
  <?php endif; ?>
  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
  <?php endif; ?>

  <form action="" method="POST">
    <div class="row">
      <!-- FAQ For -->
      <div class="col-md-6">
        <div class="mb-3">
          <label for="faq_for" class="form-label">FAQ For</label>
          <select class="form-select" id="faq_for" name="faq_for" required>
            <option value="product">Product</option>
            <option value="category">Category</option>
          </select>
        </div>
      </div>

      <!-- Item Selection (product or category) -->
      <div class="col-md-6">
        <div class="mb-3" id="productSelect" style="display:block;">
          <label class="form-label">Select Product</label>
          <select class="form-select" name="item_id" id="productDropdown">
            <?php foreach ($products as $prod): ?>
              <option value="<?= $prod['id'] ?>">
                <?= htmlspecialchars($prod['product_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3" id="categorySelect" style="display:none;">
          <label class="form-label">Select Category</label>
          <select class="form-select" name="item_id" id="categoryDropdown">
            <?php foreach ($categories as $category): ?>
              <option value="<?= $category['id'] ?>">
                <?= htmlspecialchars($category['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Question -->
      <div class="col-md-12">
        <div class="mb-3">
          <label for="question" class="form-label">Question</label>
          <input 
            type="text" 
            class="form-control" 
            id="question" 
            name="question" 
            placeholder="Enter the question" 
            required
          >
        </div>
      </div>

      <!-- Answer -->
      <div class="col-md-12">
        <div class="mb-3">
          <label for="answer" class="form-label">Answer</label>
          <textarea 
            class="form-control" 
            id="answer" 
            name="answer" 
            rows="5" 
            placeholder="Enter the answer"
          ></textarea>
        </div>
      </div>
    </div>

    <!-- Submit Button -->
    <div class="text-center">
      <button type="submit" class="btn btn-primary mt-3">Add FAQ</button>
    </div>
  </form>
</div>

<!-- Scripts -->
<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js">
</script>
<script 
  src="https://code.jquery.com/jquery-3.5.1.min.js">
</script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
  ClassicEditor.create(document.querySelector('#answer')).catch(function(error){ console.error(error); });

  // Toggle between product dropdown and category dropdown
  $(document).ready(function(){
    $('#faq_for').on('change', function(){
      if ($(this).val() === 'product') {
        $('#productSelect').show();
        $('#categorySelect').hide();
      } else {
        $('#productSelect').hide();
        $('#categorySelect').show();
      }
    });
  });
</script>
</body>
</html>
