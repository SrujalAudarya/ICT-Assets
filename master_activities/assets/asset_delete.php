<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if (!$id) {
    header("Location: assets_list.php?msg=error");
    exit();
}

mysqli_begin_transaction($conn);

try {
    // Check asset exists
    $asset_q = mysqli_query($conn, "SELECT asset_id FROM assets WHERE asset_id='$id' LIMIT 1");
    if (!$asset_q || mysqli_num_rows($asset_q) == 0) {
        throw new Exception("Asset not found.");
    }

    // Check active assignment
    $active_assign_q = mysqli_query($conn, "
        SELECT assignment_id 
        FROM asset_assignments
        WHERE asset_id='$id' AND returned_date IS NULL
        LIMIT 1
    ");

    if ($active_assign_q && mysqli_num_rows($active_assign_q) > 0) {
        mysqli_rollback($conn);
        header("Location: assets_list.php?msg=cannot_delete_assigned");
        exit();
    }

    // Delete documents first
    $docs_q = mysqli_query($conn, "SELECT file_path FROM documents WHERE asset_id='$id'");
    while ($doc = mysqli_fetch_assoc($docs_q)) {
        if (!empty($doc['file_path'])) {
            $full_path = "../../" . $doc['file_path'];
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }
    }

    mysqli_query($conn, "DELETE FROM documents WHERE asset_id='$id'");

    // Delete old returned assignment history (optional but clean)
    mysqli_query($conn, "DELETE FROM asset_assignments WHERE asset_id='$id'");

    // Delete asset
    if (!mysqli_query($conn, "DELETE FROM assets WHERE asset_id='$id'")) {
        throw new Exception(mysqli_error($conn));
    }

    mysqli_commit($conn);
    header("Location: assets_list.php?msg=deleted");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Location: assets_list.php?msg=error");
    exit();
}
?>
