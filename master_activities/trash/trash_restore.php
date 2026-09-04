<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    // 1. Check what type of model this is (Provisional vs Final)
    $model_query = "
        SELECT m.*, s.status_name 
        FROM asset_models m 
        LEFT JOIN asset_status s ON m.status_id = s.status_id 
        WHERE m.model_id = $id LIMIT 1
    ";
    $model_res = mysqli_query($conn, $model_query);
    if ($model_res && $model = mysqli_fetch_assoc($model_res)) {
        
        if ($model['status_name'] === 'Provisional Survey Off') {
            // --- SCENARIO A: RESTORING PROVISIONAL (NOT IN USE) ASSETS ---
            // 1. Change asset state back to 'In Use' or 'Working' so it returns to active inventory
            mysqli_query($conn, "UPDATE assets SET asset_state = 'In Use' WHERE model_id = $id AND asset_state = 'Not In Use'");

            // 2. Automatically reassign these assets to their last active/previous user from asset_assignments
            $assets_res = mysqli_query($conn, "SELECT asset_id FROM assets WHERE model_id = $id AND asset_state = 'In Use'");
            while ($asset = mysqli_fetch_assoc($assets_res)) {
                $asset_id = $asset['asset_id'];

                // Find the most recent user who had this asset assigned before it was unassigned
                $last_assignment_q = mysqli_query($conn, "
                    SELECT user_id FROM asset_assignments 
                    WHERE asset_id = $asset_id AND returned_date IS NOT NULL 
                    ORDER BY assignment_id DESC LIMIT 1
                ");

                if ($last_assignment_q && mysqli_num_rows($last_assignment_q) > 0) {
                    $last_user_id = mysqli_fetch_assoc($last_assignment_q)['user_id'];

                    // Create a new active assignment record re-linking them to the previous user
                    mysqli_query($conn, "
                        INSERT INTO asset_assignments (asset_id, user_id, assigned_date, returned_date, remarks) 
                        VALUES ($asset_id, $last_user_id, CURDATE(), NULL, 'Automatically reassigned upon restoration from trash')
                    ");

                    // Update asset operational status to 'Assigned'
                    $assigned_status_q = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Assigned' LIMIT 1");
                    if ($assigned_status_q && mysqli_num_rows($assigned_status_q) > 0) {
                        $assigned_status_id = mysqli_fetch_assoc($assigned_status_q)['status_id'];
                        mysqli_query($conn, "UPDATE assets SET status_id = $assigned_status_id WHERE asset_id = $asset_id");
                    }
                } else {
                    // If no previous user exists, mark asset status as 'Available'
                    $available_status_q = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
                    if ($available_status_q && mysqli_num_rows($available_status_q) > 0) {
                        $available_status_id = mysqli_fetch_assoc($available_status_q)['status_id'];
                        mysqli_query($conn, "UPDATE assets SET status_id = $available_status_id WHERE asset_id = $asset_id");
                    }
                }
            }

        } else {
            // --- SCENARIO B: RESTORING FINAL SURVEY OFF MODEL ---
            // Change model status back to 'Working' to return it and its assets to active modules
            $working_status_q = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Working' LIMIT 1");
            if ($working_status_q && mysqli_num_rows($working_status_q) > 0) {
                $working_status_id = mysqli_fetch_assoc($working_status_q)['status_id'];
                mysqli_query($conn, "UPDATE asset_models SET status_id = $working_status_id WHERE model_id = $id");
            }
        }

        header("Location: trash_bin.php?msg=restored");
        exit();
    }
}

header("Location: trash_bin.php?msg=error");
exit();