<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

if(isset($_POST['save'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $password = $_POST['password'];

    if (($role === 'Admin' || $role === 'ICT Staff') && empty($password)) {
        $error = "Password is required for Admin and ICT Staff roles.";
    } else {
        $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

        $query = "INSERT INTO users (name, email, phone, role, status, password) 
                  VALUES ('$name', '$email', '$phone', '$role', '$status', " . 
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
            <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control text-uppercase" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">System Role</label>
                        <select name="role" id="roleSelect" class="form-select" onchange="togglePassword()" required>
                            <option value="Employee">Employee</option>
                            <option value="Admin">Admin</option>
                            <option value="ICT Staff">ICT Staff</option>
                            <option value="Server">Server</option>
                            <option value="DRC Room">DRC Room</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">User Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div id="passwordField" style="display:none;" class="mb-3">
                    <label class="form-label">System Password</label>
                    <input type="password" name="password" id="passwordInput" class="form-control">
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
    
    if (role === "Admin" || role === "ICT Staff" || role === "DRC Room") {
        passwordField.style.display = "block";
        passwordInput.required = true;
    } else {
        passwordField.style.display = "none";
        passwordInput.required = false;
        passwordInput.value = "";
    }
}
</script>
<?php include("../../includes/footer.php"); ?>