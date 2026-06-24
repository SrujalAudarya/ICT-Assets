<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch existing user data
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
    $password = $_POST['password'];

    // Basic validation
    if (($role === 'Admin' || $role === 'ICT Staff' || $role === 'Employees') && empty($data['password']) && empty($password)) {
        $error = "Password is required for Admin and ICT Staff roles.";
    } else {
        // Update query construction
        $update_fields = "name='$name', email='$email', phone='$phone', role='$role'";
        
        // Update password only if provided
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_fields .= ", password='$hashed_password'";
        }
        
        $query = "UPDATE users SET $update_fields WHERE user_id=$id";
        
        if(mysqli_query($conn, $query)) {
            header("Location: users_list.php");
            exit();
        } else {
            $error = "Error: " . mysqli_error($conn);
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
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

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
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($data['phone']) ?>" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">System Role</label>
                        <select name="role" id="roleSelect" class="form-select" onchange="togglePassword()" required>
                            <option value="Employee" <?= ($data['role'] == 'Employee') ? 'selected' : '' ?>>Employee (Asset Holder)</option>
                            <option value="Admin" <?= ($data['role'] == 'Admin') ? 'selected' : '' ?>>Admin (System Access)</option>
                            <option value="ICT Staff" <?= ($data['role'] == 'ICT Staff') ? 'selected' : '' ?>>ICT Staff</option>
                            <option value="Server" <?= ($data['role'] == 'Server') ? 'selected' : '' ?>>Server</option>
                        </select>
                    </div>
                </div>

                <div id="passwordField" style="<?= ($data['role'] == 'Admin' || $data['role'] == 'ICT Staff') ? 'display:block;' : 'display:none;' ?>" class="mb-3">
                    <label class="form-label">System Password</label>
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Leave blank to keep current password">
                    <small class="text-muted">Required for Admin and ICT Staff roles if not already set.</small>
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
    
    if (role === "Admin" || role === "ICT Staff") {
        passwordField.style.display = "block";
    } else {
        passwordField.style.display = "none";
        document.getElementById("passwordInput").value = "";
    }
}
// Run on load to set initial state
document.addEventListener('DOMContentLoaded', togglePassword);
</script>

<?php include("../../includes/footer.php"); ?>
