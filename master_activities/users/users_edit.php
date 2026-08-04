<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = "";
$success = "";

// Fetch current user details BEFORE update to check their current status
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$id'");
$user = mysqli_fetch_assoc($user_query);

if (!$user) {
    header("Location: users_list.php");
    exit();
}

// =========================================================
// PROCESS USER UPDATE & AUTO-ASSIGNMENT LOGIC
// =========================================================
if (isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $location_id = mysqli_real_escape_string($conn, $_POST['location_id']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $current_status = $user['status'];
    $today = date('Y-m-d');

    mysqli_begin_transaction($conn);

    try {
        // -------------------------------------------------------------------------
        // AUTOMATION 1: User becomes INACTIVE -> Auto-Return all active assignments
        // -------------------------------------------------------------------------
        if ($current_status == 'Active' && $new_status == 'Inactive') {
            
            $active_assignments = mysqli_query($conn, "
                SELECT assignment_id, asset_id, remarks 
                FROM asset_assignments 
                WHERE user_id = '$id' AND returned_date IS NULL
            ");
            
            while ($asn = mysqli_fetch_assoc($active_assignments)) {
                $asn_id = $asn['assignment_id'];
                $ast_id = $asn['asset_id'];
                
                // Add tracking tag
                $new_remarks = mysqli_real_escape_string($conn, $asn['remarks'] . " [Auto-returned: User made Inactive]");
                
                // 1. Mark as returned
                $ret_query = "UPDATE asset_assignments SET returned_date = '$today', remarks = '$new_remarks' WHERE assignment_id = '$asn_id'";
                if (!mysqli_query($conn, $ret_query)) throw new Exception(mysqli_error($conn));
                
                // 2. Make asset Available (Status ID 21)
                $ast_query = "UPDATE assets SET status_id = 21 WHERE asset_id = '$ast_id'";
                if (!mysqli_query($conn, $ast_query)) throw new Exception(mysqli_error($conn));
            }
        } 
        
        // -------------------------------------------------------------------------
        // AUTOMATION 2: User becomes ACTIVE -> Auto-Reassign previously taken assets
        // -------------------------------------------------------------------------
        elseif ($current_status == 'Inactive' && $new_status == 'Active') {
            
            $auto_returned = mysqli_query($conn, "
                SELECT asn.asset_id 
                FROM asset_assignments asn
                JOIN assets a ON asn.asset_id = a.asset_id
                WHERE asn.user_id = '$id' 
                  AND asn.remarks LIKE '%[Auto-returned: User made Inactive]%'
                  AND a.status_id = 21 
                  AND a.asset_id NOT IN (SELECT asset_id FROM asset_assignments WHERE returned_date IS NULL)
            ");
            
            while ($row = mysqli_fetch_assoc($auto_returned)) {
                $ast_id = $row['asset_id'];
                $remark = "[Auto-assigned: User Reactivated]";
                
                // 1. Create a fresh assignment record
                $assign_query = "INSERT INTO asset_assignments (asset_id, user_id, assigned_date, remarks) 
                                 VALUES ('$ast_id', '$id', '$today', '$remark')";
                if (!mysqli_query($conn, $assign_query)) throw new Exception(mysqli_error($conn));
                
                // 2. Update asset status to Assigned (Status ID 22)
                $ast_query = "UPDATE assets SET status_id = 22 WHERE asset_id = '$ast_id'";
                if (!mysqli_query($conn, $ast_query)) throw new Exception(mysqli_error($conn));
                
                // 3. Remove the tag from the OLD history record to clear it
                mysqli_query($conn, "
                    UPDATE asset_assignments 
                    SET remarks = REPLACE(remarks, ' [Auto-returned: User made Inactive]', '') 
                    WHERE asset_id = '$ast_id' AND user_id = '$id' AND returned_date IS NOT NULL
                ");
            }
        }

        // -------------------------------------------------------------------------
        // UPDATE USER PROFILE
        // -------------------------------------------------------------------------
        $loc_sql = empty($location_id) ? "NULL" : "'$location_id'";
        
        $update_user_query = "UPDATE users SET 
                                name = '$name', 
                                email = '$email', 
                                phone = '$phone', 
                                location_id = $loc_sql, 
                                role = '$role', 
                                status = '$new_status' 
                              WHERE user_id = '$id'";
                              
        if (!mysqli_query($conn, $update_user_query)) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_commit($conn);
        header("Location: users_list.php?msg=updated");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Update Failed: " . $e->getMessage();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <!-- PAGE HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Edit User Profile</h3>
                <a href="users_list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to List</a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    
                    <?php if(!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Automation Info Banner -->
                    <div class="alert alert-info border-info bg-light small mb-4">
                        <i class="bi bi-info-circle-fill text-info me-2"></i>
                        <strong>System Notice:</strong> Changing a user's status to <strong>Inactive</strong> will automatically return all their currently assigned assets to the 'Available' pool. Changing them back to <strong>Active</strong> will attempt to automatically reassign those specific assets if they have not been given to someone else.
                    </div>

                    <form method="post">
                        <!-- NAME & EMAIL -->
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label text-muted fw-bold mb-1">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold mb-1">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                        </div>

                        <!-- PHONE & LOCATION -->
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label text-muted fw-bold mb-1">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold mb-1">Location / Department</label>
                                <select name="location_id" class="form-select">
                                    <option value="">-- No Location Assigned --</option>
                                    <?php
                                    $loc_res = mysqli_query($conn, "SELECT * FROM locations ORDER BY dept_name ASC");
                                    while ($loc = mysqli_fetch_assoc($loc_res)) {
                                        $selected = ($user['location_id'] == $loc['location_id']) ? "selected" : "";
                                        echo "<option value='{$loc['location_id']}' {$selected}>" . htmlspecialchars($loc['dept_name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- ROLE & STATUS -->
                        <div class="row mb-4 bg-light p-3 rounded border">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label text-muted fw-bold mb-1">System Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <?php
                                    $roles = ['Admin', 'ICT Staff', 'Employee', 'Apprentice', 'Server', 'DRC Room'];
                                    foreach ($roles as $r) {
                                        $sel = ($user['role'] == $r) ? "selected" : "";
                                        echo "<option value='$r' $sel>$r</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold mb-1">Account Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select fw-bold <?= $user['status'] == 'Active' ? 'text-success' : 'text-danger' ?>" required>
                                    <option value="Active" class="text-success fw-bold" <?= $user['status'] == 'Active' ? 'selected' : '' ?>>● Active</option>
                                    <option value="Inactive" class="text-danger fw-bold" <?= $user['status'] == 'Inactive' ? 'selected' : '' ?>>● Inactive</option>
                                </select>
                            </div>
                        </div>

                        <hr class="text-muted">

                        <!-- BUTTONS -->
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="users_list.php" class="btn btn-light px-4 border">Cancel</a>
                            <button type="submit" name="update" class="btn btn-primary px-5"><i class="bi bi-save me-1"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>