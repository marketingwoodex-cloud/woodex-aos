<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// 1. Load product & category lists
$products = [];
$pstmt = $conn->prepare("SELECT id, product_name FROM products ORDER BY product_name ASC");
$pstmt->execute();
$presult = $pstmt->get_result();
while ($row = $presult->fetch_assoc()) {
    $products[] = $row;
}
$pstmt->close();

$categories = [];
$tstmt = $conn->prepare("SELECT id, category_name FROM categories ORDER BY category_name ASC");
$tstmt->execute();
$tresult = $tstmt->get_result();
while ($row = $tresult->fetch_assoc()) {
    $categories[] = $row;
}
$tstmt->close();

// 2. Get the FAQ ID
if (!isset($_GET['id'])) {
    die("No FAQ ID specified!");
}
$faqId = (int)$_GET['id'];

// 3. Fetch existing FAQ
$stmt = $conn->prepare("SELECT * FROM faqs WHERE id = ?");
$stmt->bind_param("i", $faqId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("FAQ not found!");
}
$faq = $result->fetch_assoc();
$stmt->close();

// 4. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faqFor   = $conn->real_escape_string($_POST['faq_for']);
    $itemId   = (int)$_POST['item_id'];
    $question = $conn->real_escape_string($_POST['question']);
    $answer   = $conn->real_escape_string($_POST['answer']);

    // Update
    $stmtUpd = $conn->prepare("
        UPDATE faqs 
        SET faq_for = ?, 
            item_id = ?, 
            question = ?, 
            answer = ?
        WHERE id = ?
    ");
    $stmtUpd->bind_param('sissi', $faqFor, $itemId, $question, $answer, $faqId);

    if ($stmtUpd->execute()) {
        $success_message = "FAQ updated successfully!";
        // Refresh data
        $faq['faq_for'] = $faqFor;
        $faq['item_id'] = $itemId;
        $faq['question'] = $question;
        $faq['answer']   = $answer;
    } else {
        $error_message = "Error updating FAQ: " . $stmtUpd->error;
    }
    $stmtUpd->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit FAQ</title>
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
  <h2 class="form-header">Edit FAQ</h2>

  <?php if (isset($success_message)): ?>
    <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
  <?php endif; ?>
  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
  <?php endif; ?>

  <form action="" method="POST">
    <div class="row">
      <!-- FAQ For -->
      <div class="col-md-6 mb-3">
        <label for="faq_for" class="form-label">FAQ For</label>
        <select class="form-select" id="faq_for" name="faq_for" required>
          <option value="product"   <?= $faq['faq_for'] === 'product'   ? 'selected' : '' ?>>Product</option>
          <option value="category" <?= $faq['faq_for'] === 'category' ? 'selected' : '' ?>>category</option>
        </select>
      </div>

      <!-- Item Selection -->
      <div class="col-md-6 mb-3">
        <!-- We'll handle the toggle via JS, but first let's show them both and pick one. -->
        <div id="productSelect" style="display: none;">
          <label class="form-label">Select Product</label>
          <select class="form-select" id="productDropdown" name="item_id">
            <?php foreach ($products as $prod): ?>
              <option 
                value="<?= $prod['id'] ?>" 
                <?php if ($faq['faq_for'] === 'product' && (int)$faq['item_id'] === (int)$prod['id']): ?>
                  selected
                <?php endif; ?>
              >
                <?= htmlspecialchars($prod['product_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="categorieselect" style="display: none;">
          <label class="form-label">Select category</label>
          <select class="form-select" id="categoryDropdown" name="item_id">
            <?php foreach ($categories as $treat): ?>
              <option 
                value="<?= $treat['id'] ?>" 
                <?php if ($faq['faq_for'] === 'category' && (int)$faq['item_id'] === (int)$treat['id']): ?>
                  selected
                <?php endif; ?>
              >
                <?= htmlspecialchars($treat['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Question -->
      <div class="col-md-12 mb-3">
        <label for="question" class="form-label">Question</label>
        <input 
          type="text" 
          class="form-control" 
          id="question" 
          name="question" 
          value="<?= htmlspecialchars($faq['question']) ?>"
          required
        >
      </div>

      <!-- Answer -->
      <div class="col-md-12 mb-3">
        <label for="answer" class="form-label">Answer</label>
        <textarea 
          class="form-control" 
          id="answer" 
          name="answer" 
          rows="5"
        ><?= htmlspecialchars($faq['answer']) ?></textarea>
      </div>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-primary mt-3">Update FAQ</button>
    </div>
  </form>
</div>

<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js">
</script>
<script 
  src="https://code.jquery.com/jquery-3.5.1.min.js">
</script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
  ClassicEditor.create(document.querySelector('#answer')).catch(function(error){ console.error(error); });

  // Toggle product/category dropdown 
  function toggleDropdowns() {
    const faqFor = $('#faq_for').val();
    if (faqFor === 'product') {
      $('#productSelect').show();
      $('#categorieselect').hide();
    } else {
      $('#productSelect').hide();
      $('#categorieselect').show();
    }
  }

  $(document).ready(function(){
    // On load, toggle accordingly
    toggleDropdowns();
    // On change
    $('#faq_for').on('change', toggleDropdowns);
  });
</script>
</body>
</html>
