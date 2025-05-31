<?php

$_SESSION['id'] ??= 175;
$user_id = (int)$_SESSION['id'];

$upload_dir = __DIR__ . "/uploads/OSHA_Cards/users/$user_id/";
$web_path   = "/uploads/OSHA_Cards/users/$user_id/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$msg = null;

// Handle delete
if (isset($_GET['delete'])) {
    $delete_file = basename($_GET['delete']);
    $full_path = $upload_dir . $delete_file;
    if (file_exists($full_path)) {
        unlink($full_path);
        $msg = "🗑️ Deleted: $delete_file";
    }
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_files'])) {
    $count = count($_FILES['user_files']['name']);
    for ($i = 0; $i < $count; $i++) {
        $name     = $_FILES['user_files']['name'][$i];
        $tmp_name = $_FILES['user_files']['tmp_name'][$i];
        if (!$tmp_name) continue;

        $filename = basename($name);
        $target   = $upload_dir . $filename;

        if (move_uploaded_file($tmp_name, $target)) {
            $msg = "✅ Uploaded: " . htmlspecialchars($filename);
        } else {
            $msg = "❌ Upload failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Upload with Preview + Delete</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    html, body {
      margin: 0;
      padding: 0;
      font-family: system-ui, sans-serif;
      background: #fff;
    }
    .wrap {
      padding: 1rem;
    }
    h2 {
      font-size: 1.4rem;
      margin: 1rem 0 0.5rem;
    }
    .msg {
      margin: 0.75rem 0;
      color: green;
    }
    form {
      margin-bottom: 1rem;
    }
    .upload-block {
      margin-bottom: 2rem;
    }
    .upload-block input[type="file"] {
      width: 100%;
    }
    .preview-img {
      width: 100%;
      max-height: 280px;
      object-fit: contain;
      margin-top: 0.5rem;
      display: none;
    }
    .btn-more {
      background: #007bff;
      color: #fff;
      border: none;
      padding: 0.6rem 1rem;
      font-size: 1rem;
      border-radius: 6px;
      cursor: pointer;
      width: 100%;
    }
    button[type="submit"] {
      margin-top: 1rem;
      width: 100%;
      padding: 0.75rem;
      background: #28c031;
      border: none;
      color: white;
      font-weight: bold;
      border-radius: 6px;
      font-size: 1rem;
    }
    .gallery {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 1rem;
    }
    .gallery-item {
      position: relative;
    }
    .gallery img {
      width: 90px;
      height: 90px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #ccc;
    }
    .gallery a.delete {
      position: absolute;
      top: -8px;
      right: -8px;
      background: red;
      color: white;
      border: none;
      border-radius: 50%;
      font-size: 0.9rem;
      width: 20px;
      height: 20px;
      text-align: center;
      line-height: 20px;
      font-weight: bold;
      text-decoration: none;
    }
  </style>
</head>
<body>

<div class="wrap">

  <h2>Upload for User #<?= $user_id ?></h2>

  <?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="uploadForm">
    <div id="uploadArea">
      <div class="upload-block">
        <input type="file" name="user_files[]" accept="image/*" capture="environment">
        <img class="preview-img">
      </div>
    </div>

    <button type="button" class="btn-more" onclick="addMore()">+ Add More</button>
    <button type="submit">Upload All</button>
  </form>

  <h2>Uploaded Images</h2>
  <div class="gallery">
    <?php
    $files = array_diff(scandir($upload_dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $web_path . rawurlencode($file);
        $delete_link = htmlspecialchars($_SERVER['PHP_SELF']) . '?delete=' . urlencode($file);

        if (@getimagesize($upload_dir . $file)) {
            echo "<div class='gallery-item'>";
            echo "<a href='$path' target='_blank'><img src='$path' alt=''></a>";
            echo "<a href='$delete_link' class='delete' onclick='return confirm(\"Delete this image?\")'>×</a>";
            echo "</div>";
        }
    }
    ?>
  </div>

</div>

<script>
function addMore() {
  const area = document.getElementById('uploadArea');
  const block = document.createElement('div');
  block.className = 'upload-block';
  block.innerHTML = `
    <input type="file" name="user_files[]" accept="image/*" capture="environment">
    <img class="preview-img">
  `;
  area.appendChild(block);
}

document.getElementById('uploadArea').addEventListener('change', function (e) {
  if (e.target.type === 'file') {
    const file = e.target.files[0];
    const img = e.target.nextElementSibling;
    if (file && file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = () => {
        img.src = reader.result;
        img.style.display = 'block';
      };
      reader.readAsDataURL(file);
    }
  }
});
</script>

</body>
</html>
