<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';  // provides $conn and session

// 1) Handle form submit for both notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // check-in note (id=1)
    $inNote  = trim($_POST['safety_note']       ?? '');
    // check-out note (id=2)
    $outNote = trim($_POST['checkout_note']      ?? '');

    // upsert id=1
    pg_query_params($conn, "
        INSERT INTO safety_notes (id, note, updated_at)
        VALUES (1, \$1, now())
        ON CONFLICT (id) DO UPDATE
          SET note       = EXCLUDED.note,
              updated_at = EXCLUDED.updated_at
    ", [ $inNote ]);

    // upsert id=2
    pg_query_params($conn, "
        INSERT INTO safety_notes (id, note, updated_at)
        VALUES (2, \$1, now())
        ON CONFLICT (id) DO UPDATE
          SET note       = EXCLUDED.note,
              updated_at = EXCLUDED.updated_at
    ", [ $outNote ]);

    // refresh
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 2) Load current notes
$res1 = pg_query_params($conn,
    "SELECT note FROM safety_notes WHERE id = 1",
    []
);
$checkInNote = $res1
  ? pg_fetch_result($res1, 0, 'note')
  : '';

$res2 = pg_query_params($conn,
    "SELECT note FROM safety_notes WHERE id = 2",
    []
);
$checkOutNote = $res2
  ? pg_fetch_result($res2, 0, 'note')
  : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Safety Management</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/css/style.css">
  <style>
    main.container { max-width:600px; margin:2rem auto; padding:0 1rem; }
    textarea { width:100%; font-family:inherit; font-size:1rem; margin-bottom:1rem; }
    button { padding:.75rem 1rem; font-size:1rem; font-weight:bold; }
  </style>
</head>
<body>
<main class="container">
  <h1>Safety Management</h1>
  <form method="post">
    <label for="safety_note">
      <strong>Check-In Note (will appear when users check in):</strong>
    </label>
    <textarea
      id="safety_note"
      name="safety_note"
      rows="6"
      placeholder="Enter your site-wide safety message here…"
    ><?= htmlspecialchars($checkInNote) ?></textarea>

    <label for="checkout_note">
      <strong>Check-Out Note (will appear when users check out):</strong>
    </label>
    <textarea
      id="checkout_note"
      name="checkout_note"
      rows="6"
      placeholder="Enter your site-wide check-out message here…"
    ><?= htmlspecialchars($checkOutNote) ?></textarea>

    <button type="submit">Save Safety Notes</button>
  </form>
</main>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
</body>
</html>
