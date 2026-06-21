<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if (!$id) {
    header("Location: assets_list.php?msg=error");
    exit();
}

/* =========================================================
   HELPER: UPLOAD DOCUMENT
========================================================= */
function uploadDoc($conn, $asset_id, $file_input, $type) {
    if (!empty($_FILES[$file_input]['name'])) {
        $original_name = $_FILES[$file_input]['name'];
        $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $file_name = $type . "_" . $asset_id . "_" . time() . "." . $file_ext;

        $upload_dir = "../../uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $path = $upload_dir . $file_name;
        $db_path = "uploads/" . $file_name;

        if (move_uploaded_file($_FILES[$file_input]['tmp_name'], $path)) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO documents (asset_id, file_name, file_path, document_type) 
                VALUES (?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "isss", $asset_id, $original_name, $db_path, $type);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

/* =========================================================
   AJAX UPDATE
========================================================= */
if (isset($_POST['update_ajax'])) {

    $name            = mysqli_real_escape_string($conn, trim($_POST['asset_name'] ?? ''));
    $serial          = mysqli_real_escape_string($conn, trim($_POST['serial_number'] ?? ''));
    $category        = mysqli_real_escape_string($conn, $_POST['category_id'] ?? '');
    $model_id        = mysqli_real_escape_string($conn, $_POST['model_id'] ?? '');
    $vendor          = mysqli_real_escape_string($conn, $_POST['vendor_id'] ?? '');
    $location        = mysqli_real_escape_string($conn, $_POST['location_id'] ?? '');
    $status          = mysqli_real_escape_string($conn, $_POST['status_id'] ?? '');
    $date            = mysqli_real_escape_string($conn, $_POST['purchase_date'] ?? '');
    $warranty_expiry = mysqli_real_escape_string($conn, $_POST['warranty_expiry'] ?? '');
    $cost            = mysqli_real_escape_string($conn, $_POST['cost'] ?? '');

    // Assignment fields
    $assign_now          = isset($_POST['assign_now']) ? 1 : 0;
    $user_id             = mysqli_real_escape_string($conn, $_POST['user_id'] ?? '');
    $assigned_date       = mysqli_real_escape_string($conn, $_POST['assigned_date'] ?? '');
    $assignment_remarks  = mysqli_real_escape_string($conn, $_POST['assignment_remarks'] ?? '');

    if ($name == '' || $serial == '' || $category == '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Asset Name, Serial Number and Category are required.'
        ]);
        exit();
    }

    // Check duplicate serial number on another asset
    $dup = mysqli_query($conn, "SELECT asset_id FROM assets WHERE serial_number='$serial' AND asset_id != '$id' LIMIT 1");
    if (mysqli_num_rows($dup) > 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Serial number already exists for another asset.'
        ]);
        exit();
    }

    mysqli_begin_transaction($conn);

    try {
        /* ---------------- UPDATE ASSET ---------------- */
        $query = "UPDATE assets SET
                    asset_name='$name',
                    serial_number='$serial',
                    category_id='$category',
                    model_id=" . ($model_id !== '' ? "'$model_id'" : "NULL") . ",
                    vendor_id=" . ($vendor !== '' ? "'$vendor'" : "NULL") . ",
                    location_id=" . ($location !== '' ? "'$location'" : "NULL") . ",
                    status_id=" . ($status !== '' ? "'$status'" : "NULL") . ",
                    purchase_date=" . ($date !== '' ? "'$date'" : "NULL") . ",
                    warranty_expiry=" . ($warranty_expiry !== '' ? "'$warranty_expiry'" : "NULL") . ",
                    cost=" . ($cost !== '' ? "'$cost'" : "NULL") . "
                  WHERE asset_id='$id'";

        if (!mysqli_query($conn, $query)) {
            throw new Exception(mysqli_error($conn));
        }

        /* ---------------- ASSIGNMENT HANDLING ---------------- */
        if ($assign_now && !empty($user_id) && !empty($assigned_date)) {

            // Get Assigned status
            $assigned_status_res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name='Assigned' LIMIT 1");
            $assigned_status = mysqli_fetch_assoc($assigned_status_res);

            if (!$assigned_status) {
                throw new Exception("Assigned status not found in asset_status table.");
            }

            // Check active assignment
            $existing_assignment_q = mysqli_query($conn, "
                SELECT assignment_id
                FROM asset_assignments
                WHERE asset_id='$id' AND returned_date IS NULL
                LIMIT 1
            ");
            $existing_assignment = mysqli_fetch_assoc($existing_assignment_q);

            if ($existing_assignment) {
                // Update active assignment
                $assignment_id = $existing_assignment['assignment_id'];
                $update_assign = "
                    UPDATE asset_assignments
                    SET user_id='$user_id',
                        assigned_date='$assigned_date',
                        remarks='$assignment_remarks'
                    WHERE assignment_id='$assignment_id'
                ";
                if (!mysqli_query($conn, $update_assign)) {
                    throw new Exception(mysqli_error($conn));
                }
            } else {
                // Create new assignment
                $insert_assign = "
                    INSERT INTO asset_assignments (asset_id, user_id, assigned_date, remarks)
                    VALUES ('$id', '$user_id', '$assigned_date', '$assignment_remarks')
                ";
                if (!mysqli_query($conn, $insert_assign)) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            // Force status to Assigned
            $assigned_status_id = $assigned_status['status_id'];
            if (!mysqli_query($conn, "UPDATE assets SET status_id='$assigned_status_id' WHERE asset_id='$id'")) {
                throw new Exception(mysqli_error($conn));
            }
        }

        /* ---------------- DOCUMENT UPLOAD ---------------- */
        uploadDoc($conn, $id, "sale_order", "SALE_ORDER");
        uploadDoc($conn, $id, "invoice", "INVOICE");
        uploadDoc($conn, $id, "warranty_doc", "WARRANTY");

        mysqli_commit($conn);

        echo json_encode([
            'status'   => 'success',
            'redirect' => 'asset_details.php?id=' . $id
        ]);
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");

/* =========================================================
   FETCH ASSET
========================================================= */
$result = mysqli_query($conn, "SELECT * FROM assets WHERE asset_id='$id'");
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Asset not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* =========================================================
   FETCH CURRENT ACTIVE ASSIGNMENT
========================================================= */
$current_assignment = null;
$assign_q = mysqli_query($conn, "
    SELECT aa.*, u.name AS user_name, u.role AS user_role
    FROM asset_assignments aa
    LEFT JOIN users u ON aa.user_id = u.user_id
    WHERE aa.asset_id='$id' AND aa.returned_date IS NULL
    LIMIT 1
");
if ($assign_q && mysqli_num_rows($assign_q) > 0) {
    $current_assignment = mysqli_fetch_assoc($assign_q);
}

/* =========================================================
   FETCH EXISTING DOC TYPES
========================================================= */
$docs_res = mysqli_query($conn, "SELECT document_type FROM documents WHERE asset_id='$id'");
$existing_docs = [];
while($d = mysqli_fetch_assoc($docs_res)) {
    $existing_docs[] = $d['document_type'];
}
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Edit Asset: <?= htmlspecialchars($data['asset_name']) ?></h4>
            <a href="asset_details.php?id=<?= $id ?>" class="btn btn-sm btn-outline-dark">View Details</a>
        </div>

        <div class="card-body">

            <!-- Upload Overlay -->
            <div id="uploadOverlay" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.8); z-index:9999; color:white; text-align:center; padding-top:15%;">
                <div class="spinner-border text-warning mb-3" role="status" style="width:4rem; height:4rem;"></div>
                <h2 id="statusText">Updating Asset Data...</h2>
                <div class="container mt-4" style="max-width: 600px;">
                    <div class="progress" style="height:30px;">
                        <div id="progressBar" class="progress-bar bg-warning progress-bar-striped progress-bar-animated"
                             role="progressbar" style="width:0%; font-weight:bold;">0%</div>
                    </div>
                    <p class="mt-3 fs-5">Please wait, do not refresh or close the page.</p>
                </div>
            </div>

            <form id="assetForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="update_ajax" value="1">

                <!-- BASIC INFO -->
                <h5 class="text-primary border-bottom pb-2 mb-3">Basic Information</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" class="form-control"
                               value="<?= htmlspecialchars($data['asset_name']) ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                        <input type="text" name="serial_number" class="form-control"
                               value="<?= htmlspecialchars($data['serial_number']) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY category_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                $selected = ($row['category_id'] == $data['category_id']) ? "selected" : "";
                                echo "<option value='{$row['category_id']}' $selected>" . htmlspecialchars($row['category_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Model</label>
                        <select name="model_id" class="form-select">
                            <option value="">Select Model</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM asset_models ORDER BY model_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                $selected = ($row['model_id'] == $data['model_id']) ? "selected" : "";
                                echo "<option value='{$row['model_id']}' $selected>" . htmlspecialchars($row['model_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Vendor</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">Select Vendor</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM vendors ORDER BY vendor_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                $selected = ($row['vendor_id'] == $data['vendor_id']) ? "selected" : "";
                                echo "<option value='{$row['vendor_id']}' $selected>" . htmlspecialchars($row['vendor_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Location</label>
                        <select name="location_id" class="form-select">
                            <option value="">Select Location</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM locations ORDER BY dept_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                $selected = ($row['location_id'] == $data['location_id']) ? "selected" : "";
                                echo "<option value='{$row['location_id']}' $selected>" . htmlspecialchars($row['dept_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status_id" class="form-select">
                            <option value="">Select Status</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM asset_status ORDER BY status_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                $selected = ($row['status_id'] == $data['status_id']) ? "selected" : "";
                                echo "<option value='{$row['status_id']}' $selected>" . htmlspecialchars($row['status_name']) . "</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">If you assign this asset below, status will automatically become <b>Assigned</b>.</small>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control"
                               value="<?= htmlspecialchars($data['purchase_date']) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" class="form-control"
                               value="<?= htmlspecialchars($data['warranty_expiry']) ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cost (₹)</label>
                        <input type="number" step="0.01" name="cost" class="form-control"
                               value="<?= htmlspecialchars($data['cost']) ?>">
                    </div>
                </div>

                <!-- ASSIGNMENT SECTION -->
                <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">Assignment (Optional)</h5>

                <?php if($current_assignment): ?>
                    <div class="alert alert-info">
                        <strong>Currently Assigned To:</strong>
                        <?= htmlspecialchars($current_assignment['user_name']) ?>
                        <?php if(!empty($current_assignment['user_role'])): ?>
                            (<?= htmlspecialchars($current_assignment['user_role']) ?>)
                        <?php endif; ?>
                        <br>
                        <small>
                            Assigned on: <?= date('d M Y', strtotime($current_assignment['assigned_date'])) ?>
                        </small>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="assign_now" name="assign_now" value="1"
                                   <?= $current_assignment ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="assign_now">
                                Assign / Reassign this asset to a user
                            </label>
                        </div>
                        <small class="text-muted">
                            Enable this if you want to assign or reassign this asset now.
                        </small>
                    </div>

                    <div class="col-md-4 mb-3 assign-fields">
                        <label class="form-label">Select User</label>
                        <select name="user_id" class="form-select">
                            <option value="">-- Select Employee --</option>
                            <?php
                            $users = mysqli_query($conn, "SELECT user_id, name, role FROM users ORDER BY name ASC");
                            while($u = mysqli_fetch_assoc($users)){
                                $selected = ($current_assignment && $current_assignment['user_id'] == $u['user_id']) ? "selected" : "";
                                echo "<option value='{$u['user_id']}' $selected>" . htmlspecialchars($u['name']) . " (" . htmlspecialchars($u['role']) . ")</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3 assign-fields">
                        <label class="form-label">Assignment Date</label>
                        <input type="date" name="assigned_date" class="form-control"
                               value="<?= $current_assignment ? htmlspecialchars($current_assignment['assigned_date']) : date('Y-m-d') ?>">
                    </div>

                    <div class="col-md-4 mb-3 assign-fields">
                        <label class="form-label">Assignment Remarks</label>
                        <input type="text" name="assignment_remarks" class="form-control"
                               value="<?= htmlspecialchars($current_assignment['remarks'] ?? '') ?>"
                               placeholder="Optional remarks">
                    </div>
                </div>

                <!-- DOCUMENTS -->
                <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">Update Procurement Documents</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sale Order</label>
                        <input type="file" name="sale_order" class="form-control">
                        <?php if(in_array('SALE_ORDER', $existing_docs)): ?>
                            <small class="text-success">
                                <i class="bi bi-check-circle"></i> Document already exists. Upload to add another copy.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Invoice</label>
                        <input type="file" name="invoice" class="form-control">
                        <?php if(in_array('INVOICE', $existing_docs)): ?>
                            <small class="text-success">
                                <i class="bi bi-check-circle"></i> Document already exists. Upload to add another copy.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty Card</label>
                        <input type="file" name="warranty_doc" class="form-control">
                        <?php if(in_array('WARRANTY', $existing_docs)): ?>
                            <small class="text-success">
                                <i class="bi bi-check-circle"></i> Document already exists. Upload to add another copy.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 border-top pt-3">
                    <button type="submit" class="btn btn-warning btn-lg px-5">Update Asset</button>
                    <a href="assets_list.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* Toggle assignment fields */
document.addEventListener('DOMContentLoaded', function () {
    const assignCheckbox = document.getElementById('assign_now');
    const assignFields = document.querySelectorAll('.assign-fields');

    function toggleAssignFields() {
        assignFields.forEach(el => {
            el.style.display = assignCheckbox.checked ? 'block' : 'none';
        });
    }

    if (assignCheckbox) {
        assignCheckbox.addEventListener('change', toggleAssignFields);
        toggleAssignFields();
    }
});

/* AJAX form submit with progress */
document.getElementById('assetForm').onsubmit = function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();

    document.getElementById('uploadOverlay').style.display = 'block';

    xhr.upload.onprogress = function(event) {
        if (event.lengthComputable) {
            const percent = Math.round((event.loaded / event.total) * 100);
            const bar = document.getElementById('progressBar');
            bar.style.width = percent + '%';
            bar.innerHTML = percent + '%';

            if (percent === 100) {
                document.getElementById('statusText').innerHTML = "Finalizing...";
            }
        }
    };

    xhr.onload = function() {
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.status === 'success') {
                window.location.href = response.redirect;
            } else {
                alert('Error: ' + response.message);
                document.getElementById('uploadOverlay').style.display = 'none';
            }
        } catch (e) {
            console.error(xhr.responseText);
            alert('An unexpected error occurred.');
            document.getElementById('uploadOverlay').style.display = 'none';
        }
    };

    xhr.onerror = function() {
        alert('Request failed. Please try again.');
        document.getElementById('uploadOverlay').style.display = 'none';
    };

    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(formData);
};
</script>

<?php include("../../includes/footer.php"); ?>
