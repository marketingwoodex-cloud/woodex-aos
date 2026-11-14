<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Fetch categories + brands
$categories = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);
$brands = $conn->query("SELECT id, brand_name FROM brands ORDER BY brand_name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch attributes and their values
$attributes = [];
$attrRes = $conn->query("SELECT * FROM attributes");
while ($attr = $attrRes->fetch_assoc()) {
    $attr['values'] = [];
    $valRes = $conn->query("SELECT * FROM attribute_values WHERE attribute_id = " . (int)$attr['id']);
    while ($val = $valRes->fetch_assoc()) {
        $attr['values'][] = $val;
    }
    $attributes[] = $attr;
}

// Session messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
$productId = $_SESSION['current_product_id'] ?? null;

if (isset($_GET['new']) && $_GET['new'] === '1') {
    $productId = null;
    unset($_SESSION['current_product_id']);
}

unset($_SESSION['success'], $_SESSION['error'], $_SESSION['current_product_id']);

// If we have a product ID, check if it's a variable product
$isVariableProduct = false;
$uploadedImages = [];
if ($productId) {
    $product = $conn->query("SELECT product_type, images FROM products WHERE id = $productId")->fetch_assoc();
    $isVariableProduct = ($product['product_type'] === 'variable');
    $uploadedImages = json_decode($product['images'] ?? '[]', true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $productId ? 'Add Variations' : 'Add New Product' ?></title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .form-step {
      display: none;
    }
    .form-step.active {
      display: block;
    }
    .image-thumbnail {
      width: 100px;
      height: 100px;
      object-fit: cover;
      margin-right: 10px;
      margin-bottom: 10px;
      cursor: pointer;
      border: 2px solid transparent;
    }
    .image-thumbnail.selected {
      border-color: #0d6efd;
    }
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container mt-4">
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h4><?= $productId ? 'Add Variations for Product' : 'Add New Product' ?></h4>
    </div>
    <div class="card-body">
      <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
      <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
      <?php endif; ?>

      <?php if (!$productId): ?>
      <!-- Step 1: Basic Product Information -->
      <form action="php/process_add_product.php" method="POST" enctype="multipart/form-data" id="productForm" class="form-step active" data-step="1">
        <div class="row">
          <div class="col-md-6">
            <label>Product Name</label>
            <input type="text" name="productName" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label>Product Type</label>
            <select name="productType" id="productType" class="form-select" required>
              <option value="simple">Simple</option>
              <option value="variable">Variable</option>
            </select>
          </div>
        </div>

        <div class="row mt-2">
          <div class="col-md-6">
            <label>Categories</label>
            <select name="categories[]" class="form-select" multiple size="6" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Hold Ctrl/Cmd to select multiple categories.</small>
          </div>
          <div class="col-md-6">
            <label>Brand</label>
            <select name="brand" class="form-select" required>
              <option value="">Select</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['brand_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row mt-2 simple-fields">
          <div class="col-md-4"><label>Old Price</label><input type="number" name="oldPrice" step="0.01" class="form-control"></div>
          <div class="col-md-4"><label>Current Price</label><input type="number" name="currentPrice" step="0.01" class="form-control"></div>
          <div class="col-md-4"><label>Stock</label><input type="number" name="quantity" class="form-control"></div>
        </div>

        <div class="mt-2"><label>Product SKU</label><input type="text" name="sku" class="form-control"></div>

        <div class="mt-3 p-2 border rounded" style="background:#f8fafc;">
          <label for="isBestSeller" class="d-flex align-items-center gap-2 m-0" style="text-transform:none;letter-spacing:0;font-weight:600;cursor:pointer;">
            <input type="checkbox" id="isBestSeller" name="isBestSeller" value="1" style="width:18px;height:18px;">
            <span>Show this product in Best Sellers section</span>
          </label>
        </div>

        <div class="mt-2 p-2 border rounded" style="background:#f8fafc;">
          <label for="isSaleItem" class="d-flex align-items-center gap-2 m-0" style="text-transform:none;letter-spacing:0;font-weight:600;cursor:pointer;">
            <input type="checkbox" id="isSaleItem" name="isSaleItem" value="1" style="width:18px;height:18px;">
            <span>Sale Item</span>
          </label>
        </div>

        <div class="mt-2"><label>Short Description</label><textarea name="shortDescription" id="shortDescription" class="form-control"></textarea></div>
        <div class="mt-2"><label>Product Details</label><textarea name="productDetails" class="form-control" id="productDetails"></textarea></div>

        <div class="mt-2">
          <label>Specifications</label>
          <div id="specifications">
            <div class="row g-2 mb-1">
              <div class="col-md-5"><input type="text" class="form-control" name="specifications[0][heading]" placeholder="Heading"></div>
              <div class="col-md-5"><input type="text" class="form-control" name="specifications[0][description]" placeholder="Description"></div>
              <div class="col-md-2"><button type="button" class="btn btn-success w-100" onclick="addSpecification()"><i class="fas fa-plus"></i></button></div>
            </div>
          </div>
        </div>

        <div class="mt-2">
          <label>Images</label>
          <input type="file" name="images[]" multiple class="form-control" required>
        </div>

        <div class="d-flex justify-content-between mt-3">
          <button type="submit" class="btn btn-primary">Save Product</button>
        </div>
      </form>
      <?php elseif ($isVariableProduct): ?>
      <!-- Step 2: Variations for Variable Product -->
      <div id="variableSection" class="form-step active" data-step="2">
        <div class="alert alert-info">
          Product created successfully! Now add variations for this product.
        </div>
        <div class="mb-3">
          <a href="add-products.php?new=1" class="btn btn-outline-primary btn-sm">+ Add New Product</a>
        </div>
        
        <div class="mb-4">
          <h5>Uploaded Images</h5>
          <div class="d-flex flex-wrap">
            <?php foreach ($uploadedImages as $index => $img): ?>
              <div class="position-relative me-2 mb-2">
                <img src="<?= $domain.htmlspecialchars($img) ?>" class="image-thumbnail" data-index="<?= $index ?>" onclick="toggleImageSelection(this)">
                <input type="hidden" name="product_images[]" value="<?= htmlspecialchars($img) ?>">
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <form action="php/process_add_variations.php" method="POST" id="variationsForm">
          <input type="hidden" name="productId" value="<?= $productId ?>">
          
          <div class="mb-3">
            <h4>Select Attributes & Values</h4>
            <?php if (empty($attributes)): ?>
              <p class="text-danger">No attributes defined. <a href="manage-attributes.php">Add attributes</a>.</p>
            <?php else: ?>
              <?php foreach ($attributes as $attr): ?>
                <div class="mb-2">
                  <strong><?= htmlspecialchars($attr['name']) ?>:</strong><br>
                  <?php foreach ($attr['values'] as $val): ?>
                    <label class="me-2">
                      <input type="checkbox" name="attributes[<?= $attr['id'] ?>][]" value="<?= htmlspecialchars($val['value']) ?>">
                      <?php if ($attr['value_type'] === 'color'): ?>
                        <span style="display:inline-block;width:20px;height:20px;background:<?= htmlspecialchars($val['value']) ?>;border:1px solid #ccc;"></span>
                        <?= htmlspecialchars($val['label']) ?>
                      <?php else: ?>
                        <?= htmlspecialchars($val['value']) ?>
                      <?php endif; ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
              <button type="button" class="btn btn-info mb-2" id="generateVariations">Generate Variations</button>
            <?php endif; ?>
          </div>

          <div id="variationsContainer" class="mb-3"></div>

          <div class="d-flex justify-content-between">
            <a href="add-products.php" class="btn btn-secondary">Back to Products</a>
            <button type="submit" class="btn btn-primary" id="saveVariations" disabled>Save Variations</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
// Initialize editor only for long content field
ClassicEditor.create(document.querySelector('#productDetails')).catch(error => console.error(error));

// Toggle product type fields
$('#productType').change(function() {
  if ($(this).val() === 'variable') {
    $('.simple-fields').hide();
    $('.simple-fields input').prop('required', false);
  } else {
    $('.simple-fields').show();
    $('.simple-fields input').prop('required', true);
  }
});

// Generate variations
$('#generateVariations').click(function() {
  if ($('input[name^="attributes["]:checked').length === 0) {
    alert('Please select at least one attribute value');
    return;
  }
  
  $.post('php/generate-variations.php', {
    attributes: $('input[name^="attributes["]:checked').serializeArray(),
    productId: <?= $productId ?: 'null' ?>
  }, function(data) {
    $('#variationsContainer').html(data);
    $('#saveVariations').prop('disabled', false);
  }).fail(function() {
    alert('Error generating variations');
  });
});

// Toggle image selection
function toggleImageSelection(element) {
  $(element).toggleClass('selected');
  const imgUrl = $(element).attr('src');
  const variationIndex = $(element).closest('.variation-item').data('index');
  
  if ($(element).hasClass('selected')) {
    $(`input[name="variations[${variationIndex}][image]"]`).val(imgUrl);
  } else {
    $(`input[name="variations[${variationIndex}][image]"]`).val('');
  }
}

// Add specification fields
function addSpecification() {
  const count = $('#specifications .row').length;
  $('#specifications').append(`
    <div class="row g-2 mb-1">
      <div class="col-md-5"><input type="text" class="form-control" name="specifications[${count}][heading]" placeholder="Heading"></div>
      <div class="col-md-5"><input type="text" class="form-control" name="specifications[${count}][description]" placeholder="Description"></div>
      <div class="col-md-2"><button type="button" class="btn btn-danger w-100" onclick="$(this).parent().parent().remove()"><i class="fas fa-minus"></i></button></div>
    </div>`);
}
</script>
</body>
</html>
