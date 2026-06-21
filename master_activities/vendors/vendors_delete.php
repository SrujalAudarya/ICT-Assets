<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = (int)$_GET['id'];

// Check if vendor is linked with any model
$model_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_models WHERE vendor_id = $id");
$model_count = mysqli_fetch_assoc($model_check)['total'];

// Check if vendor is linked with any asset
$asset_check = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE vendor_id = $id");
$asset_count = mysqli_fetch_assoc($asset_check)['total'];

if ($model_count > 0 || $asset_count > 0) {
    echo "<script>
            alert('Vendor cannot be deleted because it is linked with models/assets.');
            window.location.href='" . ROUTE_VENDORS . "';
          </script>";
    exit();
}

mysqli_query($conn, "DELETE FROM vendors WHERE vendor_id = $id");
header("Location: " . ROUTE_VENDORS);
exit();
?>