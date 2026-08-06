<?php
ob_start(); // CRITICAL: Prevents redirect failures
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

// Safely capture the Asset ID from the URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: assets_list.php?msg=error&err_detail=" . urlencode("Invalid Asset ID"));
    exit();
}

mysqli_begin_transaction($conn);

try {
    // 1. Check if the asset exists
    $asset_q = mysqli_query($conn, "SELECT asset_id FROM assets WHERE asset_id='$id' LIMIT 1");
    if (!$asset_q || mysqli_num_rows($asset_q) == 0) {
        throw new Exception("Asset not found in database.");
    }

    // 2. Check for active assignments (Prevent deletion if someone is using it)
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

    // 3. Delete physical documents (Ignore errors if 'documents' table doesn't exist yet)
    try {
        $docs_q = @mysqli_query($conn, "SELECT file_path FROM documents WHERE asset_id='$id'");
        if ($docs_q) {
            while ($doc = mysqli_fetch_assoc($docs_q)) {
                if (!empty($doc['file_path'])) {
                    $full_path = "../../" . $doc['file_path'];
                    if (file_exists($full_path)) {
                        @unlink($full_path); // Delete file from server
                    }
                }
            }
            @mysqli_query($conn, "DELETE FROM documents WHERE asset_id='$id'");
        }
    } catch (Exception $e) {
        // Silently skip if the documents table hasn't been created yet
    }

    // 4. Delete assignment history linked to this asset
    @mysqli_query($conn, "DELETE FROM asset_assignments WHERE asset_id='$id'");

    // 5. Finally, Delete the Asset itself
    if (!mysqli_query($conn, "DELETE FROM assets WHERE asset_id='$id'")) {
        throw new Exception("Database Link Error: " . mysqli_error($conn));
    }

    // Commit changes and trigger the success popup on the list page
    mysqli_commit($conn);
    header("Location: assets_list.php?msg=deleted");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    // If it fails, send the exact error back to the list page so you can read it!
    $error_msg = urlencode($e->getMessage());
    header("Location: assets_list.php?msg=error&err_detail=" . $error_msg);
    exit();
}
?>