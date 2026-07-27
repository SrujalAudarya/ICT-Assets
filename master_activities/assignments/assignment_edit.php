<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = "";

// Fetch Assignment Info
$query = "SELECT asn.*, a.asset_name, a.serial_number, u.name AS user_name 
          FROM asset_assignments asn
          JOIN assets a ON asn.asset_id = a.asset_id
          JOIN users u ON asn.user_id = u.user_id
          WHERE asn.assignment_id = '$id'";
$result = mysqli_query($conn, $query);
$assign = mysqli_fetch_assoc($result);

if (!$assign) {
    header("Location: assignments_list.php");
    exit();
}

if (isset($_POST['update_assign'])) {
    $assigned_date = mysqli_real_escape_string($conn, $_POST['assigned_date']);
    $returned_date = !empty($_POST['returned_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['returned_date']) . "'" : "NULL";
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    $update_query = "UPDATE asset_assignments 
                     SET assigned_date = '$assigned_date', 
                         returned_date = $returned_date, 
                         remarks = '$remarks' 
                     WHERE assignment_id = '$id'";

    if (mysqli_query($conn, $update_query)) {
        // Direct back to appropriate tab based on current status
        $tab = $returned_date !== "NULL" ? "history" : "active";
        header("Location: assignments_list.php?tab=$tab&msg=updated");
        exit();
    } else {
        $error = "Error updating record: " . mysqli_error($conn);
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 border-top border-warning border-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 text-warning text-dark"><i class="bi bi-pencil-square me-2"></i> Edit Assignment Record</h4>
                    <a href="assignments_list.php" class="btn btn-sm btn-outline-secondary">Back</a>
                </div>
                <div class="card-body p-4">
                    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                    
                    <div class="alert alert-warning small">
                        <i class="bi bi-info-circle-fill me-1"></i> For data integrity, you cannot change the Asset or the User on an existing assignment. If an error was made, delete this assignment and create a new one.
                    </div>

                    <form method="POST">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold">Asset</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($assign['asset_name'] . ' (' . $assign['serial_number'] . ')') ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted fw-bold">Assigned To</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($assign['user_name']) ?>" readonly>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Assigned Date <span class="text-danger">*</span></label>
                                <input type="date" name="assigned_date" class="form-control" value="<?= $assign['assigned_date'] ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Returned Date</label>
                                <input type="date" name="returned_date" class="form-control" value="<?= $assign['returned_date'] ?>">
                                <small class="text-muted">Leave blank if currently active.</small>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="4"><?= htmlspecialchars($assign['remarks']) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="update_assign" class="btn btn-warning px-5 fw-bold">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../../includes/footer.php"); ?>