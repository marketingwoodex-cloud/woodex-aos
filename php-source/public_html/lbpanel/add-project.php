<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to add projects.';
    header('Location: index.php');
    exit;
}

function ensure_projects_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS projects (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      slug VARCHAR(255) NOT NULL,
      category VARCHAR(120) NOT NULL,
      client_name VARCHAR(180) NOT NULL,
      location_city VARCHAR(120) NOT NULL,
      area_sqft VARCHAR(80) NOT NULL,
      short_description TEXT NOT NULL,
      detail_content LONGTEXT NOT NULL,
      cover_image VARCHAR(2048) NOT NULL,
      gallery_json LONGTEXT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      sort_order INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_projects_slug (slug),
      KEY idx_projects_active_sort (is_active, sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function slugify_project($value) {
    $slug = strtolower(trim((string)$value));
    $slug = str_replace('&', 'and', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'project';
}

function unique_project_slug($base, $conn) {
    $check = $base;
    $i = 1;
    while (true) {
        $stmt = $conn->prepare("SELECT id FROM projects WHERE slug = ? LIMIT 1");
        $stmt->bind_param('s', $check);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            return $check;
        }
        $check = $base . '-' . $i;
        $i++;
    }
}

function upload_project_image($file, $dir = '../assets/images/projects/') {
    if (empty($file) || empty($file['name'])) {
        return '';
    }
    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed.');
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Only JPG, JPEG, PNG, WEBP are allowed.');
    }
    $imgInfo = @getimagesize((string)$file['tmp_name']);
    if ($imgInfo === false) {
        throw new Exception('Invalid image file.');
    }
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $fullPath = $dir . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
        throw new Exception('Unable to save uploaded image.');
    }
    return 'assets/images/projects/' . $filename;
}

ensure_projects_table($conn);

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $clientName = trim((string)($_POST['client_name'] ?? ''));
        $locationCity = trim((string)($_POST['location_city'] ?? ''));
        $areaSqft = trim((string)($_POST['area_sqft'] ?? ''));
        $shortDescription = trim((string)($_POST['short_description'] ?? ''));
        $detailContent = (string)($_POST['detail_content'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $category === '' || $clientName === '' || $locationCity === '' || $shortDescription === '' || trim(strip_tags($detailContent)) === '') {
            throw new Exception('Please fill all required fields.');
        }

        $slug = unique_project_slug(slugify_project($title), $conn);
        $coverImage = upload_project_image($_FILES['cover_image'] ?? null);
        if ($coverImage === '') {
            throw new Exception('Cover image is required.');
        }

        $gallery = [];
        if (!empty($_FILES['gallery_images']['name']) && is_array($_FILES['gallery_images']['name'])) {
            foreach ($_FILES['gallery_images']['name'] as $idx => $name) {
                if (trim((string)$name) === '') {
                    continue;
                }
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$idx] ?? '',
                    'type' => $_FILES['gallery_images']['type'][$idx] ?? '',
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$idx] ?? '',
                    'error' => $_FILES['gallery_images']['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['gallery_images']['size'][$idx] ?? 0,
                ];
                $img = upload_project_image($file);
                if ($img !== '') {
                    $gallery[] = $img;
                }
            }
        }
        $galleryJson = json_encode($gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $conn->prepare("INSERT INTO projects (title, slug, category, client_name, location_city, area_sqft, short_description, detail_content, cover_image, gallery_json, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssssssii', $title, $slug, $category, $clientName, $locationCity, $areaSqft, $shortDescription, $detailContent, $coverImage, $galleryJson, $isActive, $sortOrder);
        if (!$stmt->execute()) {
            throw new Exception('Failed to save project: ' . $stmt->error);
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        $_SESSION['success'] = 'Project added successfully.';
        header('Location: edit-project.php?id=' . $newId);
        exit;
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container form-container">
    <h2 class="form-header">Add New Project</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" id="projectForm">
        <div class="row g-3">
            <div class="col-md-6">
                <label>Project Title *</label>
                <input type="text" class="form-control" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-6">
                <label>Category *</label>
                <input type="text" class="form-control" name="category" placeholder="Corporate, Tech, Healthcare" required value="<?= htmlspecialchars($_POST['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-6">
                <label>Client Name *</label>
                <input type="text" class="form-control" name="client_name" required value="<?= htmlspecialchars($_POST['client_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label>Location City *</label>
                <input type="text" class="form-control" name="location_city" required value="<?= htmlspecialchars($_POST['location_city'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-3">
                <label>Area Sq Ft</label>
                <input type="text" class="form-control" name="area_sqft" placeholder="8,500 sq ft" value="<?= htmlspecialchars($_POST['area_sqft'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-12">
                <label>Short Description *</label>
                <textarea class="form-control" name="short_description" rows="3" required><?= htmlspecialchars($_POST['short_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="col-md-12">
                <label>Detail Content *</label>
                <textarea class="form-control" id="detail_content" name="detail_content" rows="8"><?= htmlspecialchars($_POST['detail_content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="col-md-6">
                <label>Cover Image *</label>
                <input type="file" class="form-control" name="cover_image" accept="image/*" required>
            </div>
            <div class="col-md-6">
                <label>Gallery Images (optional)</label>
                <input type="file" class="form-control" name="gallery_images[]" accept="image/*" multiple>
            </div>

            <div class="col-md-4">
                <label>Sort Order</label>
                <input type="number" class="form-control" name="sort_order" value="<?= htmlspecialchars($_POST['sort_order'] ?? '0', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (empty($_POST) || isset($_POST['is_active'])) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Active on frontend</label>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Project</button>
            <a href="projects.php" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
let projectEditor = null;
ClassicEditor.create(document.querySelector('#detail_content'))
  .then(function(editor){ projectEditor = editor; })
  .catch(function(error){ console.error(error); });

document.getElementById('projectForm').addEventListener('submit', function(e){
  if (projectEditor) {
    const html = projectEditor.getData();
    document.getElementById('detail_content').value = html;
    const plain = html.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
    if (plain === '') {
      e.preventDefault();
      alert('Detail Content is required.');
    }
  }
});
</script>
</body>
</html>
