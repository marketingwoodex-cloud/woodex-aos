<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
  $_SESSION['error'] = 'You must be logged in to manage hero slider.';
  header('Location: index.php');
  exit;
}

// Normalize base URL
$domain = isset($domain) ? rtrim((string)$domain, '/') . '/' : '';

function ensure_column($conn, $table, $column, $definition) {
  $safeTable = str_replace('`', '', $table);
  $safeColumn = str_replace('`', '', $column);
  $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
  if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
  }
}

// Ensure support for second slider design and active design switching.
ensure_column($conn, 'hero_sliders', 'active_design', "VARCHAR(20) NOT NULL DEFAULT 'classic' AFTER is_active");
ensure_column($conn, 'hero_slides', 'description', "TEXT NULL AFTER title");
ensure_column($conn, 'hero_slides', 'button_color', "VARCHAR(20) NOT NULL DEFAULT '#78be20' AFTER cta_url");
ensure_column($conn, 'hero_slides', 'heading_color', "VARCHAR(20) NOT NULL DEFAULT '#111111' AFTER button_color");
ensure_column($conn, 'hero_slides', 'description_color', "VARCHAR(20) NOT NULL DEFAULT '#4f5d6b' AFTER heading_color");

$conn->query("CREATE TABLE IF NOT EXISTS hero_slides_alt (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slider_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  cta_label VARCHAR(80) NULL,
  cta_url VARCHAR(255) NULL,
  button_color VARCHAR(20) NOT NULL DEFAULT '#78be20',
  heading_color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  description_color VARCHAR(20) NOT NULL DEFAULT '#deebf7',
  background_image_path VARCHAR(255) NOT NULL,
  background_image_alt VARCHAR(160) NULL,
  sort_order INT NOT NULL DEFAULT 1,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_alt_slider (slider_id, is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
ensure_column($conn, 'hero_slides_alt', 'button_color', "VARCHAR(20) NOT NULL DEFAULT '#78be20' AFTER cta_url");
ensure_column($conn, 'hero_slides_alt', 'heading_color', "VARCHAR(20) NOT NULL DEFAULT '#ffffff' AFTER button_color");
ensure_column($conn, 'hero_slides_alt', 'description_color', "VARCHAR(20) NOT NULL DEFAULT '#deebf7' AFTER heading_color");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_active_design'])) {
  $activeDesign = ($_POST['active_design'] ?? 'classic') === 'modern' ? 'modern' : 'classic';
  $setStmt = $conn->prepare("UPDATE hero_sliders SET active_design = ? WHERE id = ?");
  $sliderPostId = (int)($_POST['slider_id'] ?? 0);
  $setStmt->bind_param('si', $activeDesign, $sliderPostId);
  $setStmt->execute();
  $setStmt->close();
  header('Location: manage_hero_slider.php');
  exit;
}

$dbError = '';
$slider = null;
$sliderRes = $conn->query("SELECT id, name, is_active, active_design FROM hero_sliders ORDER BY id LIMIT 1");
if ($sliderRes) {
  $slider = $sliderRes->fetch_assoc();
}

if (!$slider && !$conn->error) {
  $conn->query("INSERT INTO hero_sliders (name, is_active, active_design) VALUES ('Homepage Hero', 1, 'classic')");
  $sliderRes2 = $conn->query("SELECT id, name, is_active, active_design FROM hero_sliders ORDER BY id LIMIT 1");
  if ($sliderRes2) {
    $slider = $sliderRes2->fetch_assoc();
  }
}

if (!$slider) {
  $dbError = $conn->error ? $conn->error : 'Unable to load hero slider.';
  $sliderId = 0;
  $slides = [];
  $altSlides = [];
  $activeDesign = 'classic';
} else {
  $sliderId = (int)$slider['id'];
  $activeDesign = (($slider['active_design'] ?? 'classic') === 'modern') ? 'modern' : 'classic';

  $slidesRes = $conn->query("\n    SELECT * FROM hero_slides\n    WHERE slider_id = {$sliderId}\n    ORDER BY sort_order ASC, id ASC\n  ");
  $slides = $slidesRes ? $slidesRes->fetch_all(MYSQLI_ASSOC) : [];
  $altSlidesRes = $conn->query("\n    SELECT * FROM hero_slides_alt\n    WHERE slider_id = {$sliderId}\n    ORDER BY sort_order ASC, id ASC\n  ");
  $altSlides = $altSlidesRes ? $altSlidesRes->fetch_all(MYSQLI_ASSOC) : [];
  if (!$slidesRes && !$dbError) {
    $dbError = $conn->error;
  }
  if (!$altSlidesRes && !$dbError) {
    $dbError = $conn->error;
  }
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editSlide = null;
if ($editId) {
  if ($sliderId && ($stmt = $conn->prepare("SELECT * FROM hero_slides WHERE id=? AND slider_id=?"))) {
    $stmt->bind_param("ii", $editId, $sliderId);
    $stmt->execute();
    $editSlide = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

$editAltId = isset($_GET['edit_alt']) ? (int)$_GET['edit_alt'] : 0;
$editAltSlide = null;
if ($editAltId) {
  if ($sliderId && ($stmt = $conn->prepare("SELECT * FROM hero_slides_alt WHERE id=? AND slider_id=?"))) {
    $stmt->bind_param("ii", $editAltId, $sliderId);
    $stmt->execute();
    $editAltSlide = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Hero Slider</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .thumb{width:110px;height:70px;object-fit:contain;border:1px solid #eee;background:#fff}
    .table td, .table th{vertical-align:middle}
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container py-4">
  <?php if (!empty($dbError)): ?>
    <div class="alert alert-danger">
      <strong>Hero slider error:</strong>
      <span><?= htmlspecialchars($dbError) ?></span>
      <div class="small mt-2">If this is a fresh install, ensure database tables <code>hero_sliders</code> and <code>hero_slides</code> exist.</div>
    </div>
  <?php endif; ?>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Hero Slider: <?= htmlspecialchars($slider['name'] ?? '—') ?></h3>
    <a class="btn btn-primary" href="manage_hero_slider.php">Add Slide</a>
  </div>

  <div class="card mb-4">
    <div class="card-header">Active Slider Design</div>
    <div class="card-body">
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="set_active_design" value="1">
        <input type="hidden" name="slider_id" value="<?= (int)$sliderId ?>">
        <div class="col-md-6 col-lg-4">
          <label class="form-label">Homepage slider style</label>
          <select name="active_design" class="form-select">
            <option value="classic" <?= ($activeDesign ?? 'classic') === 'classic' ? 'selected' : '' ?>>Classic Slider (current)</option>
            <option value="modern" <?= ($activeDesign ?? 'classic') === 'modern' ? 'selected' : '' ?>>Background Hero Slider (new)</option>
          </select>
        </div>
        <div class="col-md-6 col-lg-3">
          <button class="btn btn-success">Save Active Design</button>
        </div>
        <div class="col-12">
          <small class="text-muted">Both designs remain available. Only the selected design is shown on homepage.</small>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">Slides</div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>#</th><th>Preview</th><th>Kicker</th><th>Title</th><th>Order</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
              <?php if(!$slides): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No slides yet</td></tr>
              <?php else: foreach($slides as $s): ?>
                <tr>
                  <td><?= (int)$s['id'] ?></td>
                  <?php $imgPath = !empty($s['image_path']) ? (rtrim($domain,'/') . '/' . ltrim((string)$s['image_path'], '/')) : ''; ?>
                  <td>
                    <?php if ($imgPath): ?>
                      <img class="thumb" src="<?= htmlspecialchars($imgPath) ?>" alt="">
                    <?php else: ?>
                      <span class="text-muted small">No image</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($s['kicker']) ?></td>
                  <td><?= htmlspecialchars(str_replace("\n", " / ", strip_tags($s['title']))) ?></td>
                  <td><?= (int)$s['sort_order'] ?></td>
                  <td><?= $s['is_published'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Hidden</span>' ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$s['id'] ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="php/hero_delete_slide.php?id=<?= (int)$s['id'] ?>" onclick="return confirm('Delete this slide?')">Delete</a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header"><?= $editSlide ? 'Edit Slide' : 'Add Slide' ?></div>
        <div class="card-body">
          <form action="php/hero_save_slide.php" method="post" enctype="multipart/form-data">
               <input type="hidden" name="slider_id" value="<?= (int)$sliderId ?>">
            <?php if($editSlide): ?>
              <input type="hidden" name="id" value="<?= (int)$editSlide['id'] ?>">
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label">Kicker (small text)</label>
              <input name="kicker" class="form-control" maxlength="120" value="<?= htmlspecialchars($editSlide['kicker'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Title (use new lines for breaks)</label>
              <textarea name="title" class="form-control" rows="4" required><?= htmlspecialchars($editSlide['title'] ?? '') ?></textarea>
            </div>

            <div class="mb-2">
              <label class="form-label">Description</label>
              <textarea id="heroDescription" name="description" class="form-control" rows="3"><?= htmlspecialchars($editSlide['description'] ?? '') ?></textarea>
            </div>

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">CTA Label</label>
                <input name="cta_label" class="form-control" maxlength="60" value="<?= htmlspecialchars($editSlide['cta_label'] ?? 'SHOP NOW') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">CTA URL</label>
                <input name="cta_url" class="form-control" value="<?= htmlspecialchars($editSlide['cta_url'] ?? '/shop') ?>">
              </div>
            </div>

            <div class="mt-2">
              <label class="form-label">Button Color</label>
              <input type="color" name="button_color" class="form-control form-control-color" value="<?= htmlspecialchars($editSlide['button_color'] ?? '#78be20') ?>">
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Heading Text Color</label>
                <input type="color" name="heading_color" class="form-control form-control-color" value="<?= htmlspecialchars($editSlide['heading_color'] ?? '#111111') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Description Text Color</label>
                <input type="color" name="description_color" class="form-control form-control-color" value="<?= htmlspecialchars($editSlide['description_color'] ?? '#4f5d6b') ?>">
              </div>
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Text Column</label>
                <select name="text_col" class="form-select">
                  <?php $tc = $editSlide['text_col'] ?? 'left'; ?>
                  <option value="left"  <?= $tc==='left'?'selected':'' ?>>Left</option>
                  <option value="right" <?= $tc==='right'?'selected':'' ?>>Right</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="<?= htmlspecialchars($editSlide['sort_order'] ?? 1) ?>">
              </div>
            </div>

            <div class="mt-2">
              <label class="form-label">Image (PNG with transparent bg recommended)</label>
              <input type="file" name="image" class="form-control" <?= $editSlide ? '' : 'required' ?> accept="image/*">
              <?php if($editSlide && !empty($editSlide['image_path'])): ?>
                <div class="mt-2">
                  <?php $editImg = rtrim($domain,'/') . '/' . ltrim((string)$editSlide['image_path'], '/'); ?>
                  <img class="thumb" src="<?= htmlspecialchars($editImg) ?>" alt="">
                  <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editSlide['image_path']) ?>">
                </div>
              <?php endif; ?>
              <small class="text-muted">Saved to <code>assets/images/heroes/</code></small>
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Publish?</label>
                <?php $pub = isset($editSlide['is_published']) ? (int)$editSlide['is_published'] : 1; ?>
                <select name="is_published" class="form-select">
                  <option value="1" <?= $pub===1?'selected':''; ?>>Yes</option>
                  <option value="0" <?= $pub===0?'selected':''; ?>>No</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Alt text</label>
                <input name="image_alt" class="form-control" maxlength="160" value="<?= htmlspecialchars($editSlide['image_alt'] ?? '') ?>">
              </div>
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Start At (optional)</label>
                <input type="datetime-local" name="start_at" class="form-control" value="<?= isset($editSlide['start_at']) && $editSlide['start_at'] ? date('Y-m-d\TH:i', strtotime($editSlide['start_at'])) : '' ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">End At (optional)</label>
                <input type="datetime-local" name="end_at" class="form-control" value="<?= isset($editSlide['end_at']) && $editSlide['end_at'] ? date('Y-m-d\TH:i', strtotime($editSlide['end_at'])) : '' ?>">
              </div>
            </div>

            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary"><?= $editSlide ? 'Update Slide' : 'Save Slide' ?></button>
              <?php if($editSlide): ?>
                <a class="btn btn-secondary" href="manage_hero_slider.php">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <hr class="my-4">
  <h4 class="mb-3">Background Hero Slider (New Design)</h4>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">Background Slides</div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>#</th><th>Background</th><th>Heading</th><th>Description</th><th>Order</th><th>Status</th><th></th>
            </tr></thead>
            <tbody>
              <?php if (empty($altSlides)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No background slides yet</td></tr>
              <?php else: foreach($altSlides as $s): ?>
                <tr>
                  <td><?= (int)$s['id'] ?></td>
                  <?php $bgPath = !empty($s['background_image_path']) ? (rtrim($domain, '/') . '/' . ltrim((string)$s['background_image_path'], '/')) : ''; ?>
                  <td>
                    <?php if ($bgPath): ?>
                      <img class="thumb" src="<?= htmlspecialchars($bgPath) ?>" alt="">
                    <?php else: ?>
                      <span class="text-muted small">No image</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($s['title']) ?></td>
                  <td><?= htmlspecialchars(mb_substr(strip_tags((string)$s['description']), 0, 70)) ?></td>
                  <td><?= (int)$s['sort_order'] ?></td>
                  <td><?= $s['is_published'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Hidden</span>' ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="?edit_alt=<?= (int)$s['id'] ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="php/hero_delete_alt_slide.php?id=<?= (int)$s['id'] ?>" onclick="return confirm('Delete this background slide?')">Delete</a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header"><?= $editAltSlide ? 'Edit Background Slide' : 'Add Background Slide' ?></div>
        <div class="card-body">
          <form action="php/hero_save_alt_slide.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="slider_id" value="<?= (int)$sliderId ?>">
            <?php if($editAltSlide): ?>
              <input type="hidden" name="id" value="<?= (int)$editAltSlide['id'] ?>">
            <?php endif; ?>

            <div class="mb-2">
              <label class="form-label">Main Heading (Hero Title)</label>
              <input name="title" class="form-control" required maxlength="255" value="<?= htmlspecialchars($editAltSlide['title'] ?? '') ?>">
            </div>

            <div class="mb-2">
              <label class="form-label">Subheading / Description</label>
              <textarea id="heroAltDescription" name="description" class="form-control" rows="3"><?= htmlspecialchars($editAltSlide['description'] ?? '') ?></textarea>
            </div>

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">CTA Button Label</label>
                <input name="cta_label" class="form-control" maxlength="80" value="<?= htmlspecialchars($editAltSlide['cta_label'] ?? 'Shop Now') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">CTA URL</label>
                <input name="cta_url" class="form-control" value="<?= htmlspecialchars($editAltSlide['cta_url'] ?? '/shop') ?>">
              </div>
            </div>

            <div class="mt-2">
              <label class="form-label">Button Color</label>
              <input type="color" name="button_color" class="form-control form-control-color" value="<?= htmlspecialchars($editAltSlide['button_color'] ?? '#78be20') ?>">
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Heading Text Color</label>
                <input type="color" name="heading_color" class="form-control form-control-color" value="<?= htmlspecialchars($editAltSlide['heading_color'] ?? '#ffffff') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Description Text Color</label>
                <input type="color" name="description_color" class="form-control form-control-color" value="<?= htmlspecialchars($editAltSlide['description_color'] ?? '#deebf7') ?>">
              </div>
            </div>

            <div class="mt-2">
              <label class="form-label">Background Image</label>
              <input type="file" name="background_image" class="form-control" <?= $editAltSlide ? '' : 'required' ?> accept="image/*">
              <?php if($editAltSlide && !empty($editAltSlide['background_image_path'])): ?>
                <div class="mt-2">
                  <?php $editBg = rtrim($domain,'/') . '/' . ltrim((string)$editAltSlide['background_image_path'], '/'); ?>
                  <img class="thumb" src="<?= htmlspecialchars($editBg) ?>" alt="">
                  <input type="hidden" name="existing_background_image" value="<?= htmlspecialchars($editAltSlide['background_image_path']) ?>">
                </div>
              <?php endif; ?>
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Alt text</label>
                <input name="background_image_alt" class="form-control" maxlength="160" value="<?= htmlspecialchars($editAltSlide['background_image_alt'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="<?= htmlspecialchars($editAltSlide['sort_order'] ?? 1) ?>">
              </div>
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Publish?</label>
                <?php $pubAlt = isset($editAltSlide['is_published']) ? (int)$editAltSlide['is_published'] : 1; ?>
                <select name="is_published" class="form-select">
                  <option value="1" <?= $pubAlt===1?'selected':''; ?>>Yes</option>
                  <option value="0" <?= $pubAlt===0?'selected':''; ?>>No</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Start At (optional)</label>
                <input type="datetime-local" name="start_at" class="form-control" value="<?= isset($editAltSlide['start_at']) && $editAltSlide['start_at'] ? date('Y-m-d\TH:i', strtotime($editAltSlide['start_at'])) : '' ?>">
              </div>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">End At (optional)</label>
                <input type="datetime-local" name="end_at" class="form-control" value="<?= isset($editAltSlide['end_at']) && $editAltSlide['end_at'] ? date('Y-m-d\TH:i', strtotime($editAltSlide['end_at'])) : '' ?>">
              </div>
            </div>

            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary"><?= $editAltSlide ? 'Update Slide' : 'Save Slide' ?></button>
              <?php if($editAltSlide): ?>
                <a class="btn btn-secondary" href="manage_hero_slider.php">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>


</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
  ClassicEditor.create(document.querySelector('#heroDescription')).catch(function(error){ console.error(error); });
  ClassicEditor.create(document.querySelector('#heroAltDescription')).catch(function(error){ console.error(error); });
</script>
</body>
</html>
