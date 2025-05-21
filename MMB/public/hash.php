<?php
$plain = '';
$hashed = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plain = $_POST['password'] ?? '';
    $hashed = $plain ? password_hash($plain, PASSWORD_BCRYPT) : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🔐 Password Hash Tool</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 40px;
    }
    h2 {
      margin-bottom: 20px;
    }
    .grid {
      display: flex;
      gap: 20px;
    }
    .box {
      flex: 1;
      padding: 20px;
      border: 1px solid #ccc;
      border-radius: 8px;
      background: #f9f9f9;
    }
    textarea, input[type=text] {
      width: 100%;
      padding: 10px;
      font-size: 16px;
      margin-top: 10px;
    }
    button {
      margin-top: 10px;
      padding: 10px 20px;
      font-size: 16px;
      background-color: #0f4d75;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
    }
    .label {
      font-weight: bold;
    }
  </style>
</head>
<body>

<h2>🔐 Plain ↔ Hashed Password Viewer</h2>

<form method="POST">
  <div class="grid">
    <div class="box">
      <div class="label">Plain Password</div>
      <input type="text" name="password" value="<?= htmlspecialchars($plain) ?>" required>
    </div>
    <div class="box">
      <div class="label">Hashed Password (bcrypt)</div>
      <textarea readonly rows="3"><?= htmlspecialchars($hashed) ?></textarea>
    </div>
  </div>
  <button type="submit">Generate Hash</button>
</form>

</body>
</html>
