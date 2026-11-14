<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Function to generate unique filename if needed
function generateUniqueFilename($directory, $filename) {
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $newFilename = $filename;
    $counter = 1;
    while (file_exists($directory . $newFilename)) {
        $newFilename = $baseName . '-' . $counter . '.' . $fileExtension;
        $counter++;
    }
    return $newFilename;
}

// Function to create a unique slug from blog title
function generateBlogSlug($title, $conn, $excludeId = 0) {
    $slug = strtolower(trim((string)$title));
    $slug = str_replace('&', 'and', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'blog';

    $checkSlug = $slug;
    $counter = 1;
    while (true) {
        if ($excludeId > 0) {
            $stmt = $conn->prepare("SELECT id FROM blogs WHERE slug = ? AND id <> ? LIMIT 1");
            $stmt->bind_param('si', $checkSlug, $excludeId);
        } else {
            $stmt = $conn->prepare("SELECT id FROM blogs WHERE slug = ? LIMIT 1");
            $stmt->bind_param('s', $checkSlug);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if (!$exists) return $checkSlug;
        $checkSlug = $slug . '-' . $counter;
        $counter++;
    }
}

function uploadBlogImage($file) {
    if (empty($file) || empty($file['name'])) return '';
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed.');
    }

    $maxBytes = 5 * 1024 * 1024;
    if (!empty($file['size']) && (int)$file['size'] > $maxBytes) {
        throw new Exception('Image is too large (max 5MB).');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Unsupported image type. Use JPG, PNG, or WEBP.');
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new Exception('Invalid image file.');
    }

    $directory = '../assets/images/blogs/';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $fullPath = $directory . $name;
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new Exception('Unable to save uploaded image.');
    }

    return 'assets/images/blogs/' . $name;
}

$blogSlugPreview = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $blogTitle = trim((string)($_POST['blog_title'] ?? ''));
        $metaDescription = trim((string)($_POST['meta_description'] ?? ''));
        $blogContent = (string)($_POST['blog_content'] ?? '');

        if ($blogTitle === '') throw new Exception('Blog title is required.');
        if (trim(strip_tags($blogContent)) === '') throw new Exception('Blog content is required.');

        $blogSlug = generateBlogSlug($blogTitle, $conn);
        $blogSlugPreview = $blogSlug;

        $blogImage = '';
        if (!empty($_FILES['blog_image']['name'])) {
            $blogImage = uploadBlogImage($_FILES['blog_image']);
        }

        $stmt = $conn->prepare("INSERT INTO blogs (blog_title, slug, meta_description, blog_image, blog_content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssss', $blogTitle, $blogSlug, $metaDescription, $blogImage, $blogContent);

        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        $_SESSION['success'] = 'Blog added successfully.';
        header('Location: edit-blog.php?id=' . $newId);
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add New Blog</title>
  <!-- Bootstrap CSS -->
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" 
    rel="stylesheet"
  >
  <!-- Font Awesome for Icons -->
  <link 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" 
    rel="stylesheet"
  >
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container form-container">
  <h2 class="form-header">Add New Blog</h2>

  <!-- Display Success or Error Message -->
  <?php if (isset($success_message)): ?>
    <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
  <?php endif; ?>
  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
  <?php endif; ?>

  <form action="" method="POST" enctype="multipart/form-data">
    <div class="row">
      <!-- Blog Title -->
      <div class="col-md-6">
        <div class="form-group mb-3">
          <label for="blog_title">Blog Title</label>
          <input 
            type="text" 
            class="form-control" 
            id="blog_title" 
            name="blog_title" 
            placeholder="Enter blog title" 
            required
          >
        </div>
      </div>

      <!-- Meta Description -->
      <div class="col-md-6">
        <div class="form-group mb-3">
          <label for="meta_description">Meta Description</label>
          <input 
            type="text" 
            class="form-control" 
            id="meta_description" 
            name="meta_description" 
            placeholder="Short meta description"
            value="<?= htmlspecialchars($_POST['meta_description'] ?? '') ?>"
          >
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-group mb-3">
          <label for="slug">Slug (auto)</label>
          <input type="text" class="form-control" id="slug" value="<?= htmlspecialchars($blogSlugPreview) ?>" readonly>
          <small class="text-muted">Used in URL: <code>/blog/your-slug</code></small>
        </div>
      </div>

      <!-- Blog Image -->
      <div class="col-md-6">
        <div class="form-group mb-3">
          <label for="blog_image">Blog Image</label>
          <input 
            type="file" 
            class="form-control-file" 
            id="blog_image" 
            name="blog_image" 
            accept="image/*"
          >
        </div>
      </div>

      <!-- Blog Content -->
      <div class="col-md-12">
        <div class="form-group mb-3">
          <label for="blog_content">Blog Content</label>
          <textarea 
            class="form-control" 
            id="blog_content" 
            name="blog_content" 
            rows="6" 
            placeholder="Enter the blog content here..."
          ><?= htmlspecialchars($_POST['blog_content'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Submit Button -->
    <div class="text-center">
      <button type="submit" class="btn btn-primary mt-3">Add Blog</button>
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
  function blogUploadAdapter(loader) {
    return {
      upload: function () {
        return loader.file.then(function (file) {
          return new Promise(function (resolve, reject) {
            var data = new FormData();
            data.append('upload', file);

            fetch('blog-editor-upload.php', {
              method: 'POST',
              body: data,
              credentials: 'same-origin'
            })
              .then(function (response) {
                return response.json().then(function (json) {
                  if (!response.ok) {
                    throw new Error((json && json.error && json.error.message) || 'Upload failed');
                  }
                  return json;
                });
              })
              .then(function (result) {
                if (!result || !result.url) {
                  reject('Upload failed: invalid server response.');
                  return;
                }
                resolve({ default: result.url });
              })
              .catch(function (error) {
                reject(error.message || 'Image upload failed.');
              });
          });
        });
      },
      abort: function () {}
    };
  }

  function blogUploadPlugin(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
      return blogUploadAdapter(loader);
    };
  }

  ClassicEditor.create(document.querySelector('#blog_content'), {
    extraPlugins: [blogUploadPlugin]
  }).catch(function (error) {
    console.error(error);
  });
</script>
</body>
</html>
