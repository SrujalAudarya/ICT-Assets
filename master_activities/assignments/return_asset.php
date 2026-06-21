<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = $_GET['id'];

$date = date("Y-m-d");

// Get asset ID
$data = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT asset_id FROM asset_assignments WHERE assignment_id='$id'")
);

$asset_id = $data['asset_id'];

// Update return date
mysqli_query($conn, "UPDATE asset_assignments
SET returned_date='$date'
WHERE assignment_id='$id'");

// Change status back to Available
$available = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name='Available'")
);

mysqli_query($conn, "UPDATE assets
SET status_id='{$available['status_id']}'
WHERE asset_id='$asset_id'");

header("Location: assignments_list.php");
?>
