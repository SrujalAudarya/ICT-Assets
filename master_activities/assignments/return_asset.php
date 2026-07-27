<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = "";

// Fetch Assignment Info
$query = "SELECT asn.*, a.asset_name, a.serial_number, u.name AS user_name, a.asset_id 
          FROM asset_assignments asn
          JOIN assets a ON asn.asset_id = a.asset_id
          JOIN users u ON asn.user_id = u.user_id
          WHERE asn.assignment_id = '$id' AND asn.returned_date IS NULL";
$result = mysqli_query($conn, $query);
$assign = mysqli_fetch_assoc($result);

if (!$assign) {
    header("Location: assignments_list.php");
    exit();
}

if (isset($_POST['process_return'])) {
    $returned_date = mysqli_real_escape_string($conn, $_POST['returned_date']);
    $return_remarks = mysqli_real_escape_string($conn, $_POST['return_remarks']);
    
    // Append return remarks to existing remarks
    $new_remarks = $assign['remarks'];
    if (!empty($return_remarks)) {
        $new_remarks .= "\n[Returned on $returned_date]: $return_remarks";
    }
    $new_remarks_esc = mysqli_real_escape_string($conn, $new_remarks);

    mysqli_begin_transaction($conn);
    try {
        // 1. Mark as returned in assignment table
        $update_asn = mysqli_query($conn, "UPDATE asset_assignments SET returned_date = '$returned_date', remarks = '$new_remarks_esc' WHERE assignment_id = '$id'");
        if (!$update_asn) throw new Exception("Failed to update assignment record.");

        // 2. Find the ID for 'Available' Status
        $avail_query = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
        $avail_status = mysqli_fetch_assoc($avail_query)['status_id'] ?? 21;

        // 3. Mark Asset as Available
        $update_asset = mysqli_query($conn, "UPDATE assets SET status_id = '$avail_status' WHERE asset_id = '{$assign['asset_id']}'");
        if (!$update_asset) throw new Exception("Failed to update asset status.");

        mysqli_commit($conn);
        header("Location: assignments_list.php?tab=history&msg=returned");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 border-top border-success border-4">
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0 text-success"><i class="bi bi-arrow-return-left me-2"></i> Process Asset Return</h4>
                </div>
                <div class="card-body p-4">
                    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                    
                    <div class="bg-light p-3 rounded mb-4 border">
                        <label class="text-muted small fw-bold d-block">Returning Asset:</label>
                        <div class="fs-5 fw-bold text-dark"><?= htmlspecialchars($assign['asset_name']) ?> <code class="fs-6">(<?= htmlspecialchars($assign['serial_number']) ?>)</code></div>
                        
                        <label class="text-muted small fw-bold d-block mt-3">From Employee:</label>
                        <div class="fs-5 fw-bold text-primary"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($assign['user_name']) ?></div>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Date of Return <span class="text-danger">*</span></label>
                            <input type="date" name="returned_date" class="form-control form-control-lg" value="<?= date('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Return Condition / Notes</label>
                            <textarea name="return_remarks" class="form-control" rows="3" placeholder="Condition of the laptop, missing charger, screen cracked, etc..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="assignments_list.php" class="btn btn-light border w-50 py-2">Cancel</a>
                            <button type="submit" name="process_return" class="btn btn-success w-50 py-2 fw-bold">Confirm Return</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../../includes/footer.php"); ?>