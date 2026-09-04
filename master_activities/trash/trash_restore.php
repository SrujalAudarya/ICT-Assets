<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$model_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($model_id > 0) {
    // Find the 'Working' status ID to restore the model
    $st_res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Working' LIMIT 1");
    if ($st_res && mysqli_num_rows($st_res) > 0) {
        $working_status_id = mysqli_fetch_assoc($st_res)['status_id'];
        mysqli_query($conn, "UPDATE asset_models SET status_id = '$working_status_id' WHERE model_id = $model_id");
        header("Location: trash_bin.php?msg=restored");
        exit();
    }
}

header("Location: trash_bin.php?msg=error");
exit();
?>