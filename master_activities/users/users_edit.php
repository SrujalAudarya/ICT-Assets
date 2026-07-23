<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

$result = mysqli_query($conn, "SELECT * FROM users WHERE user_id=$id");
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>User not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

if(isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $password = $_POST['password'];

    if (($role === 'Admin' || $role === 'ICT Staff' || $role === 'DRC Room') && empty($data['password']) && empty($password)) {
        $error = "Password is required for this role.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // If user is being changed from Active to Inactive, auto-return assets
            if ($data['status'] == 'Active' && $status == 'Inactive') {
                
                // 1. Get the dynamic status_id for 'Available'
                $status_res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
                $available_status_id = null;
                if ($status_res && mysqli_num_rows($status_res) > 0) {
                    $status_row = mysqli_fetch_assoc($status_res);
                    $available_status_id = $status_row['status_id'];
                }

                if ($available_status_id) {
                    $assignments = mysqli_query($conn, "SELECT asset_id FROM asset_assignments WHERE user_id = '$id' AND returned_date IS NULL");
                    
                    $asset_ids = [];
                    while ($row = mysqli_fetch_assoc($assignments)) {
                        $asset_ids[] = $row['asset_id'];
                    }

                    if (!empty($asset_ids)) {
                        $ids_str = implode(',', $asset_ids);
                        // Update assets to Available
                        mysqli_query($conn, "UPDATE assets SET status_id = '$available_status_id' WHERE asset_id IN ($ids_str)");
                        // Mark as returned in assignment history
                        mysqli_query($conn, "UPDATE asset_assignments SET returned_date = CURDATE(), remarks = CONCAT(IFNULL(remarks, ''), ' [Auto-returned: User made Inactive]') WHERE user_id = '$id' AND returned_date IS NULL");
                    }
                }
            }

            $update_fields = "name='$name', email='$email', phone='$phone', role='$role', status='$status'";
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_fields .= ", password='$hashed_password'";
            }
            
            mysqli_query($conn, "UPDATE users SET $update_fields WHERE user_id=$id");
            mysqli_commit($conn);
            
            header("Location: users_list.php?msg=status_updated");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error: " . $e->getMessage();
        }
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0">Edit User / Employee</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($data['name']) ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($data['phone']) ?>" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">System Role</label>
                        <select name="role" id="roleSelect" class="form-select" onchange="togglePassword()" required>
                            <option value="Employee" <?= ($data['role'] == 'Employee') ? 'selected' : '' ?>>Employee</option>
                            <option value="Admin" <?= ($data['role'] == 'Admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="ICT Staff" <?= ($data['role'] == 'ICT Staff') ? 'selected' : '' ?>>ICT Staff</option>
                            <option value="Server" <?= ($data['role'] == 'Server') ? 'selected' : '' ?>>Server</option>
                            <option value="DRC Room" <?= ($data['role'] == 'DRC Room') ? 'selected' : '' ?>>DRC Room</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">User Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Active" <?= ($data['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= ($data['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <small class="text-muted">Setting to Inactive will auto-return assigned assets.</small>
                    </div>
                </div>

                <div id="passwordField" style="<?= in_array($data['role'], ['Admin', 'ICT Staff', 'DRC Room']) ? 'display:block;' : 'display:none;' ?>" class="mb-3">
                    <label class="form-label">System Password</label>
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Leave blank to keep current password">
                </div>

                <div class="mt-4">
                    <button type="submit" name="update" class="btn btn-primary px-4">Update User</button>
                    <a href="users_list.php" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function togglePassword() {
    var role = document.getElementById("roleSelect").value;
    var passwordField = document.getElementById("passwordField");
    if (role === "Admin" || role === "ICT Staff" || role === "DRC Room") {
        passwordField.style.display = "block";
    } else {
        passwordField.style.display = "none";
        document.getElementById("passwordInput").value = "";
    }
}
</script>
<?php include("../../includes/footer.php"); ?>