<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

if(isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['status_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $query = "UPDATE asset_status SET 
              status_name='$name', 
              description='$description' 
              WHERE status_id=$id";

    if(mysqli_query($conn, $query)) {
        header("Location: status_list.php");
        exit();
    } else {
        $error = mysqli_error($conn);
    }
}

$result = mysqli_query($conn, "SELECT * FROM asset_status WHERE status_id=$id");
$row = mysqli_fetch_assoc($result);

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0">Edit Asset Status</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Status Name</label>
                    <input type="text" name="status_name" value="<?= htmlspecialchars($row['status_name']) ?>" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($row['description']) ?></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" name="update" class="btn btn-primary px-4">Update Status</button>
                    <a href="status_list.php" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>