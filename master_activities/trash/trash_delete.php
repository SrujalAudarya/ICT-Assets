<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$model_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($model_id > 0) {
    // Fetch files to delete physically
    $img_res = mysqli_query($conn, "SELECT model_image, supply_order_doc FROM asset_models WHERE model_id = $model_id LIMIT 1");
    if ($img_res && mysqli_num_rows($img_res) > 0) {
        $row_files = mysqli_fetch_assoc($img_res);
        
        if (!empty($row_files['model_image']) && file_exists("../../" . $row_files['model_image'])) {
            unlink("../../" . $row_files['model_image']);
        }
        if (!empty($row_files['supply_order_doc']) && file_exists("../../" . $row_files['supply_order_doc'])) {
            unlink("../../" . $row_files['supply_order_doc']);
        }
    }

    // Permanently delete model record from database
    mysqli_query($conn, "DELETE FROM asset_models WHERE model_id = $model_id");
    header("Location: trash_bin.php?msg=deleted");
    exit();
}

header("Location: trash_bin.php?msg=error");
exit();
?>