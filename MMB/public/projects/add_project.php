<?php
// add_project.php – Form to add a new project address

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../config/db_connect.php');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name   = trim($_POST['project_name']);
    $address_line1  = trim($_POST['address_line1']);
    $address_line2  = trim($_POST['address_line2']);
    $city           = trim($_POST['city']);
    $state          = trim($_POST['state']);
    $zip_code       = trim($_POST['zip_code']);
    $country        = trim($_POST['country']);

    if ($project_name && $address_line1 && $city && $state && $zip_code && $country) {
        $stmt = $mysqli->prepare("INSERT INTO project_addresses 
            (project_name, address_line1, address_line2, city, state, zip_code, country) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $project_name, $address_line1, $address_line2, $city, $state, $zip_code, $country);

        if ($stmt->execute()) {
            $success = "Project address added successfully!";
        } else {
            $error = "Error saving data. Try again.";
        }

        $stmt->close();
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Project Address</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div style="max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
        <h2>Add New Project</h2>

        <?php if ($success): ?>
            <p style="color: green;"><?= $success ?></p>
        <?php elseif ($error): ?>
            <p style="color: red;"><?= $error ?></p>
        <?php endif; ?>

        <form method="post" action="">
            <label>Project Name*</label>
            <input type="text" name="project_name" required style="width:100%; margin-bottom:10px;" />

            <label>Address Line 1*</label>
            <input type="text" name="address_line1" required style="width:100%; margin-bottom:10px;" />

            <label>Address Line 2</label>
            <input type="text" name="address_line2" style="width:100%; margin-bottom:10px;" />

            <label>City*</label>
            <input type="text" name="city" required style="width:100%; margin-bottom:10px;" />

            <label>State*</label>
            <input type="text" name="state" required style="width:100%; margin-bottom:10px;" />

            <label>ZIP Code*</label>
            <input type="text" name="zip_code" required style="width:100%; margin-bottom:10px;" />

            <label>Country*</label>
            <input type="text" name="country" required style="width:100%; margin-bottom:20px;" />

            <button type="submit" class="btn-primary">Add Project</button>
        </form>
    </div>
</body>
</html>
