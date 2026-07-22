<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . "/../../includes/auth.php");
include(__DIR__ . "/../../config/db.php");
include(__DIR__ . "/../../includes/header.php");
include(__DIR__ . "/../../includes/sidebar.php");

// Check DB connection
if (!$conn) {
    die("Database connection error");
}

// Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid ID");
}

$id = intval($_GET['id']);

// Fetch data
$result = mysqli_query($conn, "SELECT * FROM pc_details WHERE id=$id");

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

$row = mysqli_fetch_assoc($result);

if (!$row) {
    die("Record not found");
}
?>

<div class="container-fluid mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Edit PC</h2>
        <a href="pc_list.php" class="btn btn-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            <!-- IMPORTANT -->
            <form method="POST" enctype="multipart/form-data">

                <!-- PC MODEL -->
                <div class="mb-3">
                    <label class="form-label">PC Model</label>
                    <input type="text" name="pc_model" class="form-control"
                           value="<?= htmlspecialchars($row['pc_model'] ?? '') ?>" required>
                </div>

                <!-- FILE VIEW + UPLOAD -->
                <div class="mb-3">
                    <label class="form-label">Supply Order (PDF)</label><br>

                    <?php if (!empty($row['supply_order_file'])) { ?>
                        <a href="uploads/<?= $row['supply_order_file'] ?>" target="_blank" class="btn btn-info btn-sm">
                            View PDF
                        </a>
                    <?php } else { ?>
                        <span class="text-muted">No file uploaded</span>
                    <?php } ?>

                    <br><br>
                    <input type="file" name="supply_order_file" class="form-control" accept="application/pdf">
                </div>

                <!-- EXPIRY -->
                <div class="mb-3">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control"
                           value="<?= $row['expiry_date'] ?>">
                </div>

                <button type="submit" name="update" class="btn btn-success">Update</button>
                <a href="pc_list.php" class="btn btn-secondary">Cancel</a>

            </form>

        </div>
    </div>

</div>

<?php
if (isset($_POST['update'])) {

    $model  = mysqli_real_escape_string($conn, $_POST['pc_model']);
    $expiry = mysqli_real_escape_string($conn, $_POST['expiry_date']);

    $file_name = $row['supply_order_file']; // keep old file

    // ✅ FILE UPLOAD
    if (!empty($_FILES['supply_order_file']['name'])) {

        $uploadDir = __DIR__ . "/uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $originalName = $_FILES['supply_order_file']['name'];
        $safeName = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $originalName);

        $targetFile = $uploadDir . $safeName;

        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        if ($fileType == "pdf") {

            if (move_uploaded_file($_FILES['supply_order_file']['tmp_name'], $targetFile)) {

                // delete old file
                if (!empty($row['supply_order_file']) && file_exists($uploadDir . $row['supply_order_file'])) {
                    unlink($uploadDir . $row['supply_order_file']);
                }

                $file_name = $safeName;

            } else {
                die("File upload failed");
            }

        } else {
            echo "<script>alert('Only PDF allowed');</script>";
        }
    }

    // ✅ UPDATE QUERY
    $sql = "UPDATE pc_details SET 
            pc_model='$model',
            supply_order_file='$file_name',
            expiry_date='$expiry'
            WHERE id=$id";

    if (mysqli_query($conn, $sql)) {
        echo "<script>
                alert('Updated Successfully');
                window.location='pc_list.php';
              </script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<?php include(__DIR__ . "/../../includes/footer.php"); ?>