<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

if(isset($_POST['save'])) {
    $dept_name = mysqli_real_escape_string($conn, $_POST['dept_name']);
    $floor = mysqli_real_escape_string($conn, $_POST['floor']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    $query = "INSERT INTO locations (dept_name, floor, remarks) 
              VALUES ('$dept_name', '$floor', '$remarks')";

    if(mysqli_query($conn, $query)) {
        header("Location: locations_list.php");
        exit();
    } else {
        $error = mysqli_error($conn);
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add New Location</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Department</label>
                        <input type="text" name="dept_name" class="form-control" placeholder="e.g. ICT">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Floor</label>
                        <input type="text" name="floor" class="form-control" placeholder="e.g. 2nd Floor">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="4" placeholder="Additional details..."></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" name="save" class="btn btn-success px-4">Save Location</button>
                    <a href="locations_list.php" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>
