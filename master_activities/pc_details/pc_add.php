<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . "/../../includes/auth.php");
include(__DIR__ . "/../../config/db.php");
include(__DIR__ . "/../../includes/header.php");
include(__DIR__ . "/../../includes/sidebar.php");

// Check DB connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>

<div class="container-fluid mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Add New PC</h2>
        <a href="pc_list.php" class="btn btn-secondary">Back</a>
    </div>

    <!-- Card -->
    <div class="card shadow-sm">
        <div class="card-body">

            <!-- IMPORTANT: enctype added -->
            <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label">PC Model</label>
                    <input type="text" name="pc_model" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Supply Order (PDF)</label>
                    <input type="file" name="supply_order_file" class="form-control" accept="application/pdf">
                </div>

                <div class="mb-3">
                    <label class="form-label">Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control">
                </div>

                <button type="submit" name="submit" class="btn btn-primary">
                    Add PC
                </button>

            </form>

        </div>
    </div>

</div>

<?php
if (isset($_POST['submit'])) {

    $model  = mysqli_real_escape_string($conn, $_POST['pc_model']);
    $expiry = mysqli_real_escape_string($conn, $_POST['expiry_date']);

    $file_name = "";

    // ✅ FILE UPLOAD
    if (!empty($_FILES['supply_order_file']['name'])) {

        $uploadDir = __DIR__ . "/uploads/";

        // Create folder if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Clean filename
        $originalName = $_FILES['supply_order_file']['name'];
        $safeName = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $originalName);

        $targetFile = $uploadDir . $safeName;

        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Allow only PDF
        if ($fileType != "pdf") {
            echo "<script>alert('Only PDF files allowed');</script>";
        } else {

            if (move_uploaded_file($_FILES['supply_order_file']['tmp_name'], $targetFile)) {
                $file_name = $safeName;
            } else {
                die("File upload failed. Check uploads folder.");
            }
        }
    }

    // ✅ INSERT QUERY
    $sql = "INSERT INTO pc_details (pc_model, supply_order_file, expiry_date)
            VALUES ('$model', '$file_name', '$expiry')";

    if (mysqli_query($conn, $sql)) {
        echo "<script>
                alert('Inserted Successfully');
                window.location='pc_list.php';
              </script>";
    } else {
        echo "DB Error: " . mysqli_error($conn);
    }
}
?>

<?php include(__DIR__ . "/../../includes/footer.php"); ?>