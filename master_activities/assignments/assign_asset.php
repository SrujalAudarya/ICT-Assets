<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

if (isset($_POST['assign'])) {
    $asset_id = mysqli_real_escape_string($conn, $_POST['asset_id']);
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $date = mysqli_real_escape_string($conn, $_POST['assigned_date']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    // Insert assignment
    $query = "INSERT INTO asset_assignments (asset_id, user_id, assigned_date, remarks) 
              VALUES ('$asset_id', '$user_id', '$date', '$remarks')";
    
    if(mysqli_query($conn, $query)) {
        // Update asset status to Assigned
        $assigned_status_query = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name='Assigned'");
        $assigned_status = mysqli_fetch_assoc($assigned_status_query);

        if ($assigned_status) {
            mysqli_query($conn, "UPDATE assets SET status_id='{$assigned_status['status_id']}' WHERE asset_id='$asset_id'");
        }

        header("Location: assignments_list.php");
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
            <h4 class="mb-0">Assign Asset to User</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Select Asset</label>
                        <select name="asset_id" class="form-select" required>
                            <option value="">-- Choose Available Asset --</option>
                            <?php
                            $available = mysqli_query($conn, "
                                SELECT a.asset_id, a.asset_name, a.serial_number, c.category_name
                                FROM assets a
                                LEFT JOIN asset_status s ON a.status_id=s.status_id
                                LEFT JOIN asset_categories c ON a.category_id=c.category_id
                                WHERE s.status_name='Available' OR s.status_name='Spare' OR s.status_name='Working'
                            ");
                            while ($row = mysqli_fetch_assoc($available)) {
                                echo "<option value='{$row['asset_id']}'>{$row['asset_name']} ({$row['serial_number']}) - {$row['category_name']}</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">Only assets with 'Available', 'Spare', or 'Working' status are shown.</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Select User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">-- Choose Employee --</option>
                            <?php
                            $users = mysqli_query($conn, "SELECT user_id, name, role FROM users ORDER BY name ASC");
                            while ($row = mysqli_fetch_assoc($users)) {
                                echo "<option value='{$row['user_id']}'>{$row['name']} ({$row['role']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Assignment Date</label>
                        <input type="date" name="assigned_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Remarks / Handover Notes</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Handed over with charger and bag..."></textarea>
                </div>

                <div class="mt-4 border-top pt-3">
                    <button type="submit" name="assign" class="btn btn-success btn-lg px-5">Assign Asset</button>
                    <a href="assignments_list.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>
