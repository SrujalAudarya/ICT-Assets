<?php
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

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Assign Asset to User</h4>
        </div>

        <div class="card-body">

            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                
                <?php if($preselected_asset): ?>
                    <!-- =====================================================
                         MODE A: Asset came from asset_details.php
                    ====================================================== -->
                    <input type="hidden" name="asset_id" value="<?= $preselected_asset['asset_id'] ?>">
                    <input type="hidden" name="return_to_asset" value="1">

                    <div class="alert alert-info">
                        You are assigning the following asset:
                    </div>

                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <strong>Selected Asset</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="text-muted small d-block">Asset Name</label>
                                    <div class="fw-bold"><?= htmlspecialchars($preselected_asset['asset_name']) ?></div>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="text-muted small d-block">Serial Number</label>
                                    <div><code><?= htmlspecialchars($preselected_asset['serial_number']) ?></code></div>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="text-muted small d-block">Category</label>
                                    <div><?= htmlspecialchars($preselected_asset['category_name'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="text-muted small d-block">Current Status</label>
                                    <div>
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($preselected_asset['status_name'] ?? 'N/A') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- =====================================================
                         MODE B: Opened directly from assignments module
                    ====================================================== -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Select Asset</label>
                            <select name="asset_id_select" class="form-select" required>
                                <option value="">-- Choose Available Asset --</option>
                                <?php
                                $available = mysqli_query($conn, "
                                    SELECT a.asset_id, a.asset_name, a.serial_number, c.category_name
                                    FROM assets a
                                    LEFT JOIN asset_status s ON a.status_id = s.status_id
                                    LEFT JOIN asset_categories c ON a.category_id = c.category_id
                                    WHERE s.status_name IN ('Available','Spare','Working')
                                      AND a.asset_id NOT IN (
                                          SELECT asset_id 
                                          FROM asset_assignments 
                                          WHERE returned_date IS NULL
                                      )
                                    ORDER BY a.asset_name ASC
                                ");
                                while ($row = mysqli_fetch_assoc($available)) {
                                    echo "<option value='{$row['asset_id']}'>"
                                        . htmlspecialchars($row['asset_name']) . " (" 
                                        . htmlspecialchars($row['serial_number']) . ") - "
                                        . htmlspecialchars($row['category_name'])
                                        . "</option>";
                                }
                                ?>
                            </select>
                            <small class="text-muted">
                                Only assets with status Available / Spare / Working and not already assigned are shown.
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Select User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Choose Employee --</option>
                            <?php
                            $users = mysqli_query($conn, "SELECT user_id, name, role FROM users ORDER BY name ASC");
                            while ($row = mysqli_fetch_assoc($users)) {
                                echo "<option value='{$row['user_id']}'>"
                                    . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['role']) . ")"
                                    . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Assignment Date</label>
                        <input type="date" name="assigned_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Remarks / Handover Notes</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Handed over with charger, bag, adapter, etc."></textarea>
                </div>

                <div class="mt-4 border-top pt-3">
                    <button type="submit" name="assign" class="btn btn-success btn-lg px-5">Assign Asset</button>

                    <?php if($preselected_asset): ?>
                        <a href="../assets/asset_details.php?id=<?= $preselected_asset['asset_id'] ?>" class="btn btn-secondary btn-lg px-5">Cancel</a>
                    <?php else: ?>
                        <a href="assignments_list.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>