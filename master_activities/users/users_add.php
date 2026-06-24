<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

if(isset($_POST['save'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password'];

    // Basic validation
    if (($role === 'Admin' || $role === 'ICT Staff') && empty($password)) {
        $error = "Password is required for Admin and ICT Staff roles.";
    } else {
        // Hash password if provided
        $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

        $query = "INSERT INTO users (name, email, phone, role, password) 
                  VALUES ('$name', '$email', '$phone', '$role', " . 
                  ($hashed_password ? "'$hashed_password'" : "NULL") . ")";
        
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
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add New User / Employee</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. john@company.com">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. +91 1234567890">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">System Role</label>
                        <select name="role" id="roleSelect" class="form-select" onchange="togglePassword()" required>
                            <option value="Employee">Employee - Asset Holder</option>
                            <option value="Admin">Admin - System Access</option>
                            <option value="ICT Staff">ICT Staff</option>
                            <option value="Server">Server</option>
                        </select>
                    </div>
                </div>

                <div id="passwordField" style="display:none;" class="mb-3">
                    <label class="form-label">System Password</label>
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter secure password">
                    <small class="text-muted">Required for Admin and ICT Staff roles.</small>
                </div>

                <div class="mt-4">
                    <button type="submit" name="save" class="btn btn-success px-4">Save User</button>
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
    var passwordInput = document.getElementById("passwordInput");
    
    if (role === "Admin" || role === "ICT Staff") {
        passwordField.style.display = "block";
        passwordInput.required = true;
    } else {
        passwordField.style.display = "none";
        passwordInput.required = false;
        passwordInput.value = "";
    }
}
document.addEventListener('DOMContentLoaded', togglePassword);
</script>

<?php include("../../includes/footer.php"); ?>