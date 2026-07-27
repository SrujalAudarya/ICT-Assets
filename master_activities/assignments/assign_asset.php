<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================================================
   GET PRESELECTED ASSET (if coming from asset_details page)
========================================================= */
$preselected_asset_id = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
$preselected_asset = null;

if ($preselected_asset_id > 0) {
    $asset_q = mysqli_query($conn, "
        SELECT a.asset_id, a.asset_name, a.serial_number, s.status_name, c.category_name
        FROM assets a
        LEFT JOIN asset_status s ON a.status_id = s.status_id
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        WHERE a.asset_id = '$preselected_asset_id'
        LIMIT 1
    ");
    if ($asset_q && mysqli_num_rows($asset_q) > 0) {
        $preselected_asset = mysqli_fetch_assoc($asset_q);
    } else {
        $preselected_asset_id = 0;
    }
}

/* =========================================================
   FORM SUBMIT
========================================================= */
if (isset($_POST['assign'])) {

    // If asset came from asset details page, use hidden field
    if (!empty($_POST['asset_id'])) {
        $asset_id = mysqli_real_escape_string($conn, $_POST['asset_id']);
    } else {
        $asset_id = mysqli_real_escape_string($conn, $_POST['asset_id_select'] ?? '');
    }

    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $date = mysqli_real_escape_string($conn, $_POST['assigned_date']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    /* ---------- VALIDATION ---------- */
    if (empty($asset_id) || empty($user_id) || empty($date)) {
        $error = "Please fill all required fields.";
    } else {

        // Check asset exists
        $check_asset = mysqli_query($conn, "SELECT * FROM assets WHERE asset_id='$asset_id' LIMIT 1");
        if (!$check_asset || mysqli_num_rows($check_asset) == 0) {
            $error = "Selected asset not found.";
        } else {

            // Check if asset is already actively assigned
            $check_active = mysqli_query($conn, "
                SELECT assignment_id 
                FROM asset_assignments 
                WHERE asset_id='$asset_id' AND returned_date IS NULL
                LIMIT 1
            ");

            if ($check_active && mysqli_num_rows($check_active) > 0) {
                $error = "This asset is already assigned and not yet returned.";
            } else {

                // Get Assigned status ID
                $assigned_status_query = mysqli_query($conn, "
                    SELECT status_id FROM asset_status 
                    WHERE status_name='Assigned' 
                    LIMIT 1
                ");
                $assigned_status = mysqli_fetch_assoc($assigned_status_query);

                if (!$assigned_status) {
                    $error = "Assigned status not found in asset_status table.";
                } else {

                    mysqli_begin_transaction($conn);

                    try {
                        // Insert assignment record
                        $insert = mysqli_query($conn, "
                            INSERT INTO asset_assignments (asset_id, user_id, assigned_date, remarks)
                            VALUES ('$asset_id', '$user_id', '$date', '$remarks')
                        ");

                        if (!$insert) {
                            throw new Exception(mysqli_error($conn));
                        }

                        // Update asset status to Assigned
                        $update = mysqli_query($conn, "
                            UPDATE assets 
                            SET status_id='{$assigned_status['status_id']}'
                            WHERE asset_id='$asset_id'
                        ");

                        if (!$update) {
                            throw new Exception(mysqli_error($conn));
                        }

                        mysqli_commit($conn);

                        // Redirect back to asset details if assignment started from asset page
                        if (!empty($_POST['return_to_asset']) && $_POST['return_to_asset'] == '1') {
                            header("Location: ../assets/asset_details.php?id=" . $asset_id . "&msg=assigned");
                            exit();
                        } else {
                            header("Location: assignments_list.php?msg=assigned");
                            exit();
                        }

                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = "Error: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <!-- PAGE HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0 text-dark"><i class="bi bi-person-workspace text-primary me-2"></i> Assign Asset to User</h3>
                <?php if($preselected_asset): ?>
                    <a href="../assets/asset_details.php?id=<?= $preselected_asset['asset_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Asset</a>
                <?php else: ?>
                    <a href="assignments_list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to List</a>
                <?php endif; ?>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        
                        <?php if($preselected_asset): ?>
                            <!-- =====================================================
                                 MODE A: Asset came from asset_details.php
                            ====================================================== -->
                            <input type="hidden" name="asset_id" value="<?= $preselected_asset['asset_id'] ?>">
                            <input type="hidden" name="return_to_asset" value="1">

                            <div class="card border-info bg-light mb-4">
                                <div class="card-header bg-info text-white fw-bold">
                                    <i class="bi bi-info-circle me-1"></i> Preselected Asset for Assignment
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-5 mb-2 mb-md-0">
                                            <label class="text-muted small d-block fw-bold">Asset Name</label>
                                            <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($preselected_asset['asset_name']) ?></div>
                                        </div>
                                        <div class="col-md-4 mb-2 mb-md-0">
                                            <label class="text-muted small d-block fw-bold">Serial Number</label>
                                            <div><code class="fs-6"><?= htmlspecialchars($preselected_asset['serial_number']) ?></code></div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="text-muted small d-block fw-bold">Category</label>
                                            <div>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($preselected_asset['category_name'] ?? 'N/A') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- =====================================================
                                 MODE B: Opened directly from assignments module
                            ====================================================== -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <label class="form-label text-muted fw-bold mb-1">Select Asset <span class="text-danger">*</span></label>
                                    <select name="asset_id_select" id="assetSelect" class="form-select" required>
                                        <option value="">Search by Asset Name, Serial No, or Prev User...</option>
                                        <?php
                                        // SMART QUERY: Fetches available assets + grabs the LAST assigned user[cite: 1]
                                        $available = mysqli_query($conn, "
                                            SELECT a.asset_id, a.asset_name, a.serial_number, c.category_name,
                                                   (SELECT u.name 
                                                    FROM asset_assignments asn
                                                    JOIN users u ON asn.user_id = u.user_id
                                                    WHERE asn.asset_id = a.asset_id AND asn.returned_date IS NOT NULL
                                                    ORDER BY asn.returned_date DESC LIMIT 1) AS prev_user
                                            FROM assets a
                                            LEFT JOIN asset_status s ON a.status_id = s.status_id
                                            LEFT JOIN asset_categories c ON a.category_id = c.category_id
                                            WHERE s.status_name IN ('Available','Spare','Working')
                                              AND a.asset_id NOT IN (
                                                  SELECT asset_id 
                                                  FROM asset_assignments 
                                                  WHERE returned_date IS NULL
                                              )
                                            ORDER BY c.category_name ASC, a.asset_name ASC
                                        ");
                                        
                                        $current_category = "";

                                        while ($row = mysqli_fetch_assoc($available)) {
                                            // Group by category for better UX
                                            if ($row['category_name'] != $current_category) {
                                                if ($current_category != "") echo "</optgroup>";
                                                $current_category = $row['category_name'];
                                                echo "<optgroup label='" . htmlspecialchars($current_category) . "'>";
                                            }
                                            
                                            $sn = htmlspecialchars($row['serial_number']);
                                            $name = htmlspecialchars($row['asset_name']);
                                            $prev = !empty($row['prev_user']) ? htmlspecialchars($row['prev_user']) : 'New / Unassigned';

                                            echo "<option value='{$row['asset_id']}'>SN: {$sn} | {$name} (Prev User: {$prev})</option>";
                                        }
                                        if ($current_category != "") echo "</optgroup>";
                                        ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- USER & DATE ROW -->
                        <div class="row mb-4">
                            <div class="col-md-7 mb-3 mb-md-0">
                                <label class="form-label text-muted fw-bold mb-1">Assign To Employee <span class="text-danger">*</span></label>
                                <select name="user_id" id="userSelect" class="form-select" required>
                                    <option value="">Search employee name or role...</option>
                                    <?php
                                    $users = mysqli_query($conn, "SELECT user_id, name, role FROM users WHERE status = 'Active' ORDER BY name ASC");
                                    while ($row = mysqli_fetch_assoc($users)) {
                                        echo "<option value='{$row['user_id']}'>"
                                            . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['role']) . ")"
                                            . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label text-muted fw-bold mb-1">Assignment Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar-date"></i></span>
                                    <input type="date" name="assigned_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- REMARKS -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label text-muted fw-bold mb-1">Remarks / Handover Notes</label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Handed over with charger, bag, adapter, etc."></textarea>
                            </div>
                        </div>

                        <hr class="text-muted">

                        <!-- SUBMIT BUTTONS -->
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <?php if($preselected_asset): ?>
                                <a href="../assets/asset_details.php?id=<?= $preselected_asset['asset_id'] ?>" class="btn btn-light px-4 border">Cancel</a>
                            <?php else: ?>
                                <a href="assignments_list.php" class="btn btn-light px-4 border">Cancel</a>
                            <?php endif; ?>
                            <button type="submit" name="assign" class="btn btn-primary px-5"><i class="bi bi-check-circle me-1"></i> Assign Asset</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>