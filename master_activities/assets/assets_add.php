<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================================================
   HELPER: CHECK COLUMN EXISTS
   ========================================================= */
function columnExists($conn, $table, $column) {
    $table  = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($q && mysqli_num_rows($q) > 0);
}

/* =========================================================
   HELPER: UPLOAD DOCUMENT + SAVE IN documents TABLE
   ========================================================= */
function uploadDoc($conn, $asset_id, $file_input, $type) {
    if (!isset($_FILES[$file_input]) || empty($_FILES[$file_input]['name'])) {
        return;
    }

    if ($_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error uploading file for {$type}");
    }

    $original_name = $_FILES[$file_input]['name'];
    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    if (!in_array($file_ext, $allowed)) {
        throw new Exception("Invalid file type for {$type}. Allowed: pdf, jpg, jpeg, png, doc, docx, xls, xlsx");
    }

    $file_name = $type . "_" . $asset_id . "_" . time() . "." . $file_ext;

    $upload_dir = "../../uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $path = $upload_dir . $file_name;
    $db_path = "uploads/" . $file_name;

    if (!move_uploaded_file($_FILES[$file_input]['tmp_name'], $path)) {
        throw new Exception("Failed to upload {$type} file.");
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO documents (asset_id, file_name, file_path, document_type) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Failed to prepare document insert query.");
    }

    mysqli_stmt_bind_param($stmt, "isss", $asset_id, $original_name, $db_path, $type);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to save document record: " . mysqli_error($conn));
    }
}

/* =========================================================
   FETCH DROPDOWNS
   ========================================================= */
$categories = mysqli_query($conn, "SELECT category_id, category_name FROM asset_categories ORDER BY category_name ASC");
$models     = mysqli_query($conn, "SELECT model_id, model_name FROM asset_models ORDER BY model_name ASC");
$vendors    = mysqli_query($conn, "SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name ASC");
$locations  = mysqli_query($conn, "SELECT location_id, dept_name, floor FROM locations ORDER BY dept_name ASC");
$statuses   = mysqli_query($conn, "SELECT status_id, status_name FROM asset_status ORDER BY status_name ASC");
$users      = mysqli_query($conn, "SELECT user_id, name, role FROM users ORDER BY name ASC");

/* =========================================================
   FIND ASSIGNED STATUS ID
   ========================================================= */
$assigned_status_id = "";
$assigned_status_q = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name='Assigned' LIMIT 1");
if ($assigned_status_q && mysqli_num_rows($assigned_status_q) > 0) {
    $assigned_status_row = mysqli_fetch_assoc($assigned_status_q);
    $assigned_status_id = $assigned_status_row['status_id'];
}

/* =========================================================
   HANDLE FORM SUBMIT
   ========================================================= */
$error = "";

if (isset($_POST['save_asset'])) {

    // -----------------------------
    // ASSET DATA
    // -----------------------------
    $asset_name      = trim($_POST['asset_name'] ?? '');
    $serial_number   = trim($_POST['serial_number'] ?? '');
    $category_id     = trim($_POST['category_id'] ?? '');
    $model_id        = trim($_POST['model_id'] ?? '');
    $vendor_id       = trim($_POST['vendor_id'] ?? '');
    $location_id     = trim($_POST['location_id'] ?? '');
    $status_id       = trim($_POST['status_id'] ?? '');
    $purchase_date   = trim($_POST['purchase_date'] ?? '');
    $warranty_expiry = trim($_POST['warranty_expiry'] ?? '');
    $cost            = trim($_POST['cost'] ?? '');

    // -----------------------------
    // OPTIONAL ASSIGNMENT DATA
    // -----------------------------
    $assign_now      = isset($_POST['assign_now']) ? 1 : 0;
    $user_id         = trim($_POST['user_id'] ?? '');
    $assigned_date   = trim($_POST['assigned_date'] ?? date('Y-m-d'));
    $assign_remarks  = trim($_POST['assign_remarks'] ?? '');

    // -----------------------------
    // VALIDATION
    // -----------------------------
    if ($asset_name == "") {
        $error = "Asset Name is required.";
    } elseif ($serial_number == "") {
        $error = "Serial Number is required.";
    } elseif ($category_id == "") {
        $error = "Please select Category.";
    } elseif ($location_id == "") {
        $error = "Please select Location.";
    } elseif (!$assign_now && $status_id == "") {
        $error = "Please select Status.";
    } elseif ($assign_now && $user_id == "") {
        $error = "Please select User because 'Assign Now' is checked.";
    }

    // Duplicate serial number check
    if ($error == "") {
        $serial_safe = mysqli_real_escape_string($conn, $serial_number);
        $dup = mysqli_query($conn, "SELECT asset_id FROM assets WHERE serial_number = '$serial_safe' LIMIT 1");
        if ($dup && mysqli_num_rows($dup) > 0) {
            $error = "An asset with this Serial Number already exists.";
        }
    }

    // Duplicate asset code check (only if column exists and asset code entered)
    $has_asset_name_column = columnExists($conn, "assets", "asset_name");

    if ($error == "" && $has_asset_name_column && $asset_name != "") {
        $asset_name_safe = mysqli_real_escape_string($conn, $asset_name);
        $dup_name = mysqli_query($conn, "SELECT asset_id FROM assets WHERE asset_name = '$asset_name_safe' LIMIT 1");
        if ($dup_name && mysqli_num_rows($dup_name) > 0) {
            $error = "An asset with this Asset Name already exists.";
        }
    }

    if ($error == "") {
        mysqli_begin_transaction($conn);

        try {
            // -----------------------------
            // IF ASSIGN NOW => FORCE STATUS = Assigned
            // -----------------------------
            if ($assign_now) {
                $assigned_status_q = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name='Assigned' LIMIT 1");
                $assigned_status = mysqli_fetch_assoc($assigned_status_q);

                if (!$assigned_status) {
                    throw new Exception("Assigned status not found in asset_status table.");
                }

                $status_id = $assigned_status['status_id'];
            }

            // -----------------------------
            // ESCAPE VALUES
            // -----------------------------
            $asset_name_safe    = mysqli_real_escape_string($conn, $asset_name);
            $serial_number_safe = mysqli_real_escape_string($conn, $serial_number);
            $category_id_safe   = mysqli_real_escape_string($conn, $category_id);
            $location_id_safe   = mysqli_real_escape_string($conn, $location_id);
            $status_id_safe     = mysqli_real_escape_string($conn, $status_id);

            $model_id_sql        = ($model_id !== '') ? "'" . mysqli_real_escape_string($conn, $model_id) . "'" : "NULL";
            $vendor_id_sql       = ($vendor_id !== '') ? "'" . mysqli_real_escape_string($conn, $vendor_id) . "'" : "NULL";
            $purchase_date_sql   = ($purchase_date !== '') ? "'" . mysqli_real_escape_string($conn, $purchase_date) . "'" : "NULL";
            $warranty_expiry_sql = ($warranty_expiry !== '') ? "'" . mysqli_real_escape_string($conn, $warranty_expiry) . "'" : "NULL";
            $cost_sql            = ($cost !== '') ? "'" . mysqli_real_escape_string($conn, $cost) . "'" : "NULL";

            // -----------------------------
            // INSERT INTO assets
            // -----------------------------
            if ($has_asset_name_column) {
                $asset_name_sql = ($asset_name !== '') ? "'$asset_name_safe'" : "NULL";

                $insert_asset = "
                    INSERT INTO assets (
                        asset_name,
                        model_id,
                        serial_number,
                        category_id,
                        vendor_id,
                        location_id,
                        status_id,
                        purchase_date,
                        warranty_expiry,
                        cost
                    ) VALUES (
                        '$asset_name_safe',
                        $model_id_sql,
                        '$serial_number_safe',
                        '$category_id_safe',
                        $vendor_id_sql,
                        '$location_id_safe',
                        '$status_id_safe',
                        $purchase_date_sql,
                        $warranty_expiry_sql,
                        $cost_sql
                    )
                ";
            } else {
                $insert_asset = "
                    INSERT INTO assets (
                        asset_name,
                        model_id,
                        serial_number,
                        category_id,
                        vendor_id,
                        location_id,
                        status_id,
                        purchase_date,
                        warranty_expiry,
                        cost
                    ) VALUES (
                        '$asset_name_safe',
                        $model_id_sql,
                        '$serial_number_safe',
                        '$category_id_safe',
                        $vendor_id_sql,
                        '$location_id_safe',
                        '$status_id_safe',
                        $purchase_date_sql,
                        $warranty_expiry_sql,
                        $cost_sql
                    )
                ";
            }

            if (!mysqli_query($conn, $insert_asset)) {
                throw new Exception("Failed to save asset: " . mysqli_error($conn));
            }

            $asset_id = mysqli_insert_id($conn);

            // -----------------------------
            // UPLOAD DOCUMENTS INTO documents TABLE
            // -----------------------------
            uploadDoc($conn, $asset_id, "sale_order", "SALE_ORDER");
            uploadDoc($conn, $asset_id, "invoice", "INVOICE");
            uploadDoc($conn, $asset_id, "warranty_doc", "WARRANTY");

            // -----------------------------
            // OPTIONAL ASSIGNMENT INSERT
            // -----------------------------
            if ($assign_now) {
                $asset_id_safe       = mysqli_real_escape_string($conn, $asset_id);
                $user_id_safe        = mysqli_real_escape_string($conn, $user_id);
                $assigned_date_safe  = mysqli_real_escape_string($conn, $assigned_date);
                $assign_remarks_safe = mysqli_real_escape_string($conn, $assign_remarks);

                // check if already assigned (safety)
                $check_assign = mysqli_query($conn, "
                    SELECT assignment_id 
                    FROM asset_assignments 
                    WHERE asset_id = '$asset_id_safe' AND returned_date IS NULL
                    LIMIT 1
                ");

                if (!$check_assign || mysqli_num_rows($check_assign) == 0) {
                    $insert_assignment = "
                        INSERT INTO asset_assignments (
                            asset_id,
                            user_id,
                            assigned_date,
                            remarks
                        ) VALUES (
                            '$asset_id_safe',
                            '$user_id_safe',
                            '$assigned_date_safe',
                            '$assign_remarks_safe'
                        )
                    ";

                    if (!mysqli_query($conn, $insert_assignment)) {
                        throw new Exception("Failed to create assignment record: " . mysqli_error($conn));
                    }
                }
            }

            mysqli_commit($conn);

            header("Location: asset_details.php?id=" . $asset_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add New Asset & Procurement Documents</h4>
        </div>
        <div class="card-body">

            <?php if($error != ""): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">

                <!-- BASIC INFORMATION -->
                <h5 class="text-primary border-bottom pb-2 mb-3">Basic Information</h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" class="form-control"
                               value="<?= htmlspecialchars($_POST['asset_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                        <input type="text" name="serial_number" class="form-control"
                               value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control"
                               value="<?= htmlspecialchars($_POST['purchase_date'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php
                            mysqli_data_seek($categories, 0);
                            while($row = mysqli_fetch_assoc($categories)) {
                                $selected = (($_POST['category_id'] ?? '') == $row['category_id']) ? 'selected' : '';
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
                            mysqli_data_seek($models, 0);
                            while($row = mysqli_fetch_assoc($models)) {
                                $selected = (($_POST['model_id'] ?? '') == $row['model_id']) ? 'selected' : '';
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
                            mysqli_data_seek($vendors, 0);
                            while($row = mysqli_fetch_assoc($vendors)) {
                                $selected = (($_POST['vendor_id'] ?? '') == $row['vendor_id']) ? 'selected' : '';
                                echo "<option value='{$row['vendor_id']}' $selected>" . htmlspecialchars($row['vendor_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <select name="location_id" class="form-select" required>
                            <option value="">Select Location</option>
                            <?php
                            mysqli_data_seek($locations, 0);
                            while($row = mysqli_fetch_assoc($locations)) {
                                $selected = (($_POST['location_id'] ?? '') == $row['location_id']) ? 'selected' : '';
                                $label = $row['dept_name'];
                                if (!empty($row['floor'])) {
                                    $label .= " ({$row['floor']})";
                                }
                                echo "<option value='{$row['location_id']}' $selected>" . htmlspecialchars($label) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status_id" id="status_id" class="form-select" required>
                            <option value="">Select Status</option>
                            <?php
                            mysqli_data_seek($statuses, 0);
                            while($row = mysqli_fetch_assoc($statuses)) {
                                $selected = (($_POST['status_id'] ?? '') == $row['status_id']) ? 'selected' : '';
                                echo "<option value='{$row['status_id']}' $selected>" . htmlspecialchars($row['status_name']) . "</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">
                            If you assign this asset immediately, status will automatically become <strong>Assigned</strong>.
                        </small>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" class="form-control"
                               value="<?= htmlspecialchars($_POST['warranty_expiry'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cost (₹)</label>
                        <input type="number" step="0.01" min="0" name="cost" class="form-control"
                               value="<?= htmlspecialchars($_POST['cost'] ?? '') ?>">
                    </div>
                </div>

                <!-- PROCUREMENT DOCUMENTS -->
                <h5 class="text-primary border-bottom pb-2 mb-3 mt-4">Procurement Documents</h5>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sale Order</label>
                        <input type="file" name="sale_order" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Invoice</label>
                        <input type="file" name="invoice" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty Card</label>
                        <input type="file" name="warranty_doc" class="form-control">
                    </div>
                </div>

                <!-- OPTIONAL ASSIGNMENT -->
                <h5 class="text-primary border-bottom pb-2 mb-3 mt-4">Assign Asset to User (Optional)</h5>

                <div class="card border-success mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">Assignment Details</h6>
                    </div>
                    <div class="card-body">

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="assign_now" id="assign_now" value="1"
                                   <?= isset($_POST['assign_now']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="assign_now">
                                Assign this asset immediately after saving
                            </label>
                        </div>

                        <div id="assignment_fields" style="display:none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Select User</label>
                                    <select name="user_id" class="form-select">
                                        <option value="">-- Choose Employee --</option>
                                        <?php
                                        mysqli_data_seek($users, 0);
                                        while($row = mysqli_fetch_assoc($users)) {
                                            $selected = (($_POST['user_id'] ?? '') == $row['user_id']) ? 'selected' : '';
                                            echo "<option value='{$row['user_id']}' $selected>" . htmlspecialchars($row['name'] . " (" . $row['role'] . ")") . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Assignment Date</label>
                                    <input type="date" name="assigned_date" class="form-control"
                                           value="<?= htmlspecialchars($_POST['assigned_date'] ?? date('Y-m-d')) ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Remarks / Handover Notes</label>
                                <textarea name="assign_remarks" class="form-control" rows="3"
                                          placeholder="e.g. Handed over with charger and bag..."><?= htmlspecialchars($_POST['assign_remarks'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="mt-4 border-top pt-3">
                    <button type="submit" name="save_asset" class="btn btn-primary btn-lg px-5">
                        Save Asset
                    </button>
                    <a href="assets_list.php" class="btn btn-secondary btn-lg px-5">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const assignNow = document.getElementById("assign_now");
    const assignmentFields = document.getElementById("assignment_fields");
    const statusSelect = document.getElementById("status_id");
    const assignedStatusId = "<?= $assigned_status_id ?>";

    let previousStatus = statusSelect.value;

    function toggleAssignmentFields() {
        if (assignNow.checked) {
            assignmentFields.style.display = "block";

            if (statusSelect.value !== assignedStatusId) {
                previousStatus = statusSelect.value;
            }

            if (assignedStatusId !== "") {
                statusSelect.value = assignedStatusId;
            }

            statusSelect.setAttribute("disabled", "disabled");
        } else {
            assignmentFields.style.display = "none";
            statusSelect.removeAttribute("disabled");

            if (previousStatus !== "") {
                statusSelect.value = previousStatus;
            }
        }
    }

    assignNow.addEventListener("change", toggleAssignmentFields);

    statusSelect.addEventListener("change", function () {
        if (!assignNow.checked) {
            previousStatus = statusSelect.value;
        }
    });

    document.querySelector("form").addEventListener("submit", function () {
        statusSelect.removeAttribute("disabled");
    });

    toggleAssignmentFields();
});
</script>

<?php include("../../includes/footer.php"); ?>