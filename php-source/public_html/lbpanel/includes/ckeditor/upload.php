<?php
if (isset($_FILES['upload'])) {
  $file = $_FILES['upload'];
  $filename = $file['name'];
  $tmp_name = $file['tmp_name'];
  $error = $file['error'];

  // Check for errors
  if ($error !== UPLOAD_ERR_OK) {
    echo "Upload failed with error code $error";
    exit;
  }

  // Specify the upload directory
  $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/admin/includes/ckeditor/uploads';

  // Move the uploaded file to the upload directory
  if (!move_uploaded_file($tmp_name, "$upload_dir/$filename")) {
    echo "Failed to move uploaded file";
    exit;
  }

  // Return the file URL
  $url = "/admin/includes/ckeditor/uploads/$filename";
  echo "<script type='text/javascript'>window.parent.CKEDITOR.tools.callFunction($_GET[CKEditorFuncNum], '$url');</script>";
}
?>
