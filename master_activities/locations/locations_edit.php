<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

if(isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['location_name']);
    $dept_name = mysqli_real_escape_string($conn, $_POST['dept_name']);
    $floor = mysqli_real_escape_string($conn, $_POST['floor']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    $query = "UPDATE locations SET 
              location_name='$name', 
              dept_name='$dept_name', 
              floor='$floor', 
              remarks='$remarks' 
              WHERE location_id=$id";

    if(mysqli_query($conn, $query)) {
        header("Location: locations_list.php");
        exit();
    } else {
        $error = mysqli_error($conn);
    }
}

$result = mysqli_query($conn, "SELECT * FROM locations WHERE location_id=$id");
$row = mysqli_fetch_assoc($result);

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0">Edit Location</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="location_name" value="<?= htmlspecialchars($row['location_name']) ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">dept_name</label>
                        <input type="text" name="dept_name" value="<?= htmlspecialchars($row['dept_name']) ?>" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Floor</label>
                        <input type="text" name="floor" value="<?= htmlspecialchars($row['floor']) ?>" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="4"><?= htmlspecialchars($row['remarks']) ?></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" name="update" class="btn btn-primary px-4">Update Location</button>
                    <a href="locations_list.php" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>
