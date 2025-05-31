<?php
// public/activity/checklog/process_checkin.php

declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';

// ─── 1) Guard: must be logged in ─────────────────────────
if (empty($_SESSION['id'])) {
    header('Location: /login.php');
    exit;
}
$user_id    = (int) $_SESSION['id'];
$project_id = (int) ($_POST['project_id'] ?? 0);
$notes      = trim($_POST['notes'] ?? '');

// ─── 2) Only proceed if a valid project was chosen ────────
if ($project_id > 0) {
    // 3) Insert with lookup of related fields
    $sql = <<<SQL
INSERT INTO public.activities_log (
    user_id,
    project_id,
    notes,
    created_at,
    project_name,
    address_line1,
    full_name,
    check_in_date,
    check_in_clock,
    check_out_date,
    check_out_clock
)
SELECT
    \$1,
    \$2,
    \$3,
    NOW(),
    pa.project_name,
    pa.address_line1,
    u.full_name,
    CURRENT_DATE,
    CURRENT_TIME,
    NULL,
    NULL
FROM public.project_addresses pa
JOIN public.users u ON u.id = \$1
WHERE pa.id = \$2
RETURNING id, check_in_clock;
SQL;

    $res = pg_query_params($conn, $sql, [ $user_id, $project_id, $notes ]);

    if (!$res) {
        error_log("❌ process_checkin INSERT failed: " . pg_last_error($conn));
    } else {
        $new = pg_fetch_assoc($res);
        error_log("✅ process_checkin INSERT succeeded: id={$new['id']}, check_in_clock={$new['check_in_clock']}");
    }
}

// ─── 4) Redirect back to dashboard ────────────────────────
header('Location: /dashboard_standard.php');
exit;
