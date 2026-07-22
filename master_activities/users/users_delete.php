<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

// Sanitize the input to prevent SQL Injection
$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if (!$id) {
    header("Location: users_list.php?msg=error");
    exit();
}

// Start a transaction so if anything fails, no partial data is saved
mysqli_begin_transaction($conn);

try {
    // 1. Get the status_id for 'Available'
    $status_res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
    $available_status_id = null;
    if ($status_res && mysqli_num_rows($status_res) > 0) {
        $status_row = mysqli_fetch_assoc($status_res);
        $available_status_id = $status_row['status_id'];
    }

    if ($available_status_id) {
        // 2. Find all assets currently assigned to this user
        $assignments_res = mysqli_query($conn, "
            SELECT asset_id 
            FROM asset_assignments 
            WHERE user_id = '$id' AND returned_date IS NULL
        ");
        
        $asset_ids = [];
        while ($row = mysqli_fetch_assoc($assignments_res)) {
            $asset_ids[] = $row['asset_id'];
        }

        // 3. If they have active assets, update their status and close the assignment
        if (!empty($asset_ids)) {
            $asset_ids_str = implode(',', $asset_ids);
            
            // Set assets back to Available
            $update_assets = "UPDATE assets SET status_id = '$available_status_id' WHERE asset_id IN ($asset_ids_str)";
            mysqli_query($conn, $update_assets);
            
            // Mark the assignments as returned today with a note
            $update_assignments = "
                UPDATE asset_assignments 
                SET returned_date = CURDATE(), remarks = CONCAT(IFNULL(remarks, ''), ' [Auto-returned: User Deleted]') 
                WHERE user_id = '$id' AND returned_date IS NULL
            ";
            mysqli_query($conn, $update_assignments);
        }
    }

    // 4. Finally, delete the user
    mysqli_query($conn, "DELETE FROM users WHERE user_id='$id'");

    // Commit all changes to the database
    mysqli_commit($conn);
    
    header("Location: users_list.php?msg=deleted");
    exit();

} catch (Exception $e) {
    // If any error occurs, undo all changes
    mysqli_rollback($conn);
    header("Location: users_list.php?msg=error");
    exit();
}
?>