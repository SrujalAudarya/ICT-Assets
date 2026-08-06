<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================================================
   AJAX HANDLER: FETCH MODEL DETAILS FOR AUTO-FILL
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] == 'get_model_details') {
    header('Content-Type: application/json');
    $mod_id = (int)$_GET['model_id'];

    $query = "SELECT vendor_id, purchase_date, warranty_expiry, cost 
              FROM assets 
              WHERE model_id = $mod_id 
              ORDER BY asset_id DESC LIMIT 1";
    $res = mysqli_query($conn, $query);

    if ($res && $row = mysqli_fetch_assoc($res)) {
        echo json_encode($row);
    } else {
        $fallback_query = "SELECT vendor_id FROM asset_models WHERE model_id = $mod_id LIMIT 1";
        $fallback_res = mysqli_query($conn, $fallback_query);
        if ($fallback_res && $fallback_row = mysqli_fetch_assoc($fallback_res)) {
            echo json_encode(['vendor_id' => $fallback_row['vendor_id'], 'purchase_date' => '', 'warranty_expiry' => '', 'cost' => '']);
        } else {
            echo json_encode([]);
        }
    }
    exit();
}

/* =========================================================
   HELPER: CHECK COLUMN EXISTS
   ========================================================= */
function columnExists($conn, $table, $column)
{
    $table  = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($q && mysqli_num_rows($q) > 0);
}

/* =========================================================
   HELPER: UPLOAD DOCUMENT + SAVE IN documents TABLE
   ========================================================= */
function uploadDoc($conn, $asset_id, $file_input, $type)
{
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
   CSV IMPORT HELPER FUNCTIONS
   ========================================================= */
function parsePurchaseDate($rawDate) {
    $rawDate = trim($rawDate);
    if ($rawDate === '') return null;
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('-', $rawDate);
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) return "$yy-$mm-$dd";
    }
    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('.', $rawDate);
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) return "$yy-$mm-$dd";
    }
    if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('.', $rawDate);
        $yy = '20' . $yy;
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) return "$yy-$mm-$dd";
    }
    if (preg_match('/^\d{2}-\d{2}-\d{2}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('-', $rawDate);
        $yy = '20' . $yy;
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) return "$yy-$mm-$dd";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) return $rawDate;
    return null;
}

function parseCsvLine($line) {
    $line = trim($line);
    if ($line === '') return [];
    if (strpos($line, "\t") !== false) return str_getcsv($line, "\t");
    elseif (strpos($line, ";") !== false) return str_getcsv($line, ";");
    else return str_getcsv($line, ",");
}

function getCsvCategoryId($conn, $categoryName) {
    $categoryName = trim($categoryName);
    if ($categoryName === '') return null;
    $categoryNameEsc = mysqli_real_escape_string($conn, $categoryName);
    $res = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE category_name = '$categoryNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['category_id'];
    mysqli_query($conn, "INSERT INTO asset_categories (category_name) VALUES ('$categoryNameEsc')");
    return mysqli_insert_id($conn);
}

function getCsvLocationId($conn, $deptName) {
    $deptName = trim($deptName);
    if ($deptName === '') return null;
    $deptNameEsc = mysqli_real_escape_string($conn, $deptName);
    $res = mysqli_query($conn, "SELECT location_id FROM locations WHERE dept_name = '$deptNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['location_id'];
    mysqli_query($conn, "INSERT INTO locations (dept_name) VALUES ('$deptNameEsc')");
    return mysqli_insert_id($conn);
}

function getCsvModelId($conn, $modelName, $category_id = null, $vendor_id = null) {
    $modelName = trim($modelName);
    if ($modelName === '') return null;
    $modelNameEsc = mysqli_real_escape_string($conn, $modelName);
    $res = mysqli_query($conn, "SELECT model_id FROM asset_models WHERE model_name = '$modelNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['model_id'];
    $categorySql = $category_id ? $category_id : "NULL";
    $vendorSql   = $vendor_id ? $vendor_id : "NULL";
    mysqli_query($conn, "INSERT INTO asset_models (model_name, category_id, vendor_id) VALUES ('$modelNameEsc', $categorySql, $vendorSql)");
    return mysqli_insert_id($conn);
}

function getCsvVendorId($conn, $vendorName) {
    $vendorName = trim($vendorName);
    if ($vendorName === '') return null;
    $vendorNameEsc = mysqli_real_escape_string($conn, $vendorName);
    $res = mysqli_query($conn, "SELECT vendor_id FROM vendors WHERE vendor_name = '$vendorNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['vendor_id'];
    mysqli_query($conn, "INSERT INTO vendors (vendor_name) VALUES ('$vendorNameEsc')");
    return mysqli_insert_id($conn);
}

// RESTRICTED TO ONLY "ASSIGNED" OR "AVAILABLE"
function getCsvStatusId($conn, $hasAssignedUser = false) {
    if ($hasAssignedUser) {
        $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Assigned' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['status_id'];
    }
    $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['status_id'];
    return null;
}


/* =========================================================
   HANDLE CSV IMPORT LOGIC
   ========================================================= */
$error = "";
$success_msg = "";

if (isset($_POST['import_assets_excel'])) {
    if (!empty($_FILES['asset_excel_file']['name'])) {
        $fileExt = strtolower(pathinfo($_FILES['asset_excel_file']['name'], PATHINFO_EXTENSION));

        if ($fileExt !== 'csv') {
            $error = "Please upload only a CSV file.";
        } else {
            $fileName = $_FILES['asset_excel_file']['tmp_name'];
            $handle = fopen($fileName, "r");

            if ($handle !== false) {
                $rowCount = 0;
                $successCount = 0;
                $failCount = 0;
                $failedRows = [];

                while (($line = fgets($handle)) !== false) {
                    $rowCount++;
                    if ($rowCount == 1) continue; // Skip header

                    $line = trim($line);
                    if ($line === '') continue;
                    $row = parseCsvLine($line);

                    $assignedUserName = trim($row[0] ?? '');
                    $categoryName     = trim($row[1] ?? '');
                    $serialNumber     = trim($row[2] ?? '');
                    $deptName         = trim($row[3] ?? '');
                    $vendorName       = trim($row[4] ?? '');
                    $modelName        = trim($row[5] ?? '');
                    $assetName        = trim($row[6] ?? '');
                    $purchaseDateRaw  = trim($row[7] ?? '');

                    if ($assignedUserName === '' && $categoryName === '' && $serialNumber === '' &&
                        $deptName === '' && $vendorName === '' && $modelName === '' &&
                        $assetName === '' && $purchaseDateRaw === '') {
                        continue;
                    }

                    if ($serialNumber === '' || $assetName === '') {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Validation Failed (Missing Serial or Asset Name)";
                        continue;
                    }

                    $assignedUserNameEsc = mysqli_real_escape_string($conn, $assignedUserName);
                    $serialNumberEsc     = mysqli_real_escape_string($conn, $serialNumber);
                    $assetNameEsc        = mysqli_real_escape_string($conn, $assetName);

                    $dupRes = mysqli_query($conn, "SELECT asset_id FROM assets WHERE serial_number = '$serialNumberEsc' LIMIT 1");
                    if ($dupRes && mysqli_num_rows($dupRes) > 0) {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Duplicate serial number ($serialNumber)";
                        continue;
                    }

                    $category_id = getCsvCategoryId($conn, $categoryName);
                    $vendor_id   = getCsvVendorId($conn, $vendorName);
                    $location_id = getCsvLocationId($conn, $deptName);
                    $model_id    = getCsvModelId($conn, $modelName, $category_id, $vendor_id);
                    $status_id   = getCsvStatusId($conn, !empty($assignedUserName));

                    $parsedDate = parsePurchaseDate($purchaseDateRaw);
                    $purchaseDateSql = $parsedDate ? "'$parsedDate'" : "NULL";

                    $insertAsset = "
                        INSERT INTO assets (
                            asset_name, model_id, serial_number, category_id, vendor_id, location_id, status_id, purchase_date, cost
                        ) VALUES (
                            '$assetNameEsc', " . ($model_id ? $model_id : "NULL") . ", '$serialNumberEsc',
                            " . ($category_id ? $category_id : "NULL") . ", " . ($vendor_id ? $vendor_id : "NULL") . ",
                            " . ($location_id ? $location_id : "NULL") . ", " . ($status_id ? $status_id : "NULL") . ",
                            $purchaseDateSql, 0
                        )";

                    if (!mysqli_query($conn, $insertAsset)) {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Asset insert failed - " . mysqli_error($conn);
                        continue;
                    }

                    $asset_id = mysqli_insert_id($conn);

                    if ($assignedUserName !== '') {
                        $userRes = mysqli_query($conn, "SELECT user_id FROM users WHERE name = '$assignedUserNameEsc' LIMIT 1");

                        if ($userRes && mysqli_num_rows($userRes) > 0) {
                            $userRow = mysqli_fetch_assoc($userRes);
                            $user_id = (int)$userRow['user_id'];
                            $assignQuery = "
                                INSERT INTO asset_assignments (asset_id, user_id, assigned_date, returned_date, remarks) 
                                VALUES ($asset_id, $user_id, CURDATE(), NULL, NULL)";

                            if (!mysqli_query($conn, $assignQuery)) {
                                $failedRows[] = "Row $rowCount: Asset inserted but assignment failed - " . mysqli_error($conn);
                            }
                        } else {
                            $failedRows[] = "Row $rowCount: Asset inserted but user not found for assignment ($assignedUserName)";
                        }
                    }
                    $successCount++;
                }
                fclose($handle);
                $success_msg = "Import completed successfully. Assets Added: $successCount, Failed: $failCount";

                if (!empty($failedRows)) {
                    $error = implode("<br>", $failedRows);
                }
            } else {
                $error = "Unable to open uploaded CSV file.";
            }
        }
    } else {
        $error = "Please select a CSV file.";
    }
}


/* =========================================================
   FETCH DROPDOWNS
   ========================================================= */
$main_categories = mysqli_query($conn, "SELECT category_id, category_name FROM asset_categories WHERE parent_id = 0 OR parent_id IS NULL ORDER BY category_name ASC");
$sub_categories  = mysqli_query($conn, "SELECT category_id, category_name, parent_id FROM asset_categories WHERE parent_id > 0 ORDER BY category_name ASC");

$models    = mysqli_query($conn, "SELECT model_id, model_name, category_id FROM asset_models ORDER BY model_name ASC");
$vendors   = mysqli_query($conn, "SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name ASC");
$locations = mysqli_query($conn, "SELECT location_id, dept_name, floor FROM locations ORDER BY dept_name ASC");
$statuses  = mysqli_query($conn, "SELECT status_id, status_name FROM asset_status ORDER BY status_name ASC");
$users     = mysqli_query($conn, "SELECT user_id, name, role, location_id FROM users WHERE status = 'Active' ORDER BY name ASC");

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
   HANDLE SINGLE FORM SUBMIT
   ========================================================= */
if (isset($_POST['save_asset'])) {

    $asset_name      = trim($_POST['asset_name'] ?? '');
    $serial_number   = trim($_POST['serial_number'] ?? '');

    $main_category_id = trim($_POST['main_category_id'] ?? '');
    $sub_category_id  = trim($_POST['sub_category_id'] ?? '');
    $category_id      = !empty($sub_category_id) ? $sub_category_id : $main_category_id;

    $model_id        = trim($_POST['model_id'] ?? '');
    $vendor_id       = trim($_POST['vendor_id'] ?? '');
    $location_id     = trim($_POST['location_id'] ?? '');
    $status_id       = trim($_POST['status_id'] ?? '');
    $purchase_date   = trim($_POST['purchase_date'] ?? '');
    $warranty_expiry = trim($_POST['warranty_expiry'] ?? '');
    $cost            = trim($_POST['cost'] ?? '');

    $assign_now      = isset($_POST['assign_now']) ? 1 : 0;
    $user_id         = trim($_POST['user_id'] ?? '');
    $assigned_date   = trim($_POST['assigned_date'] ?? date('Y-m-d'));
    $assign_remarks  = trim($_POST['assign_remarks'] ?? '');

    if ($asset_name == "") {
        $error = "Asset Name is required.";
    } elseif ($serial_number == "") {
        $error = "Serial Number is required.";
    } elseif ($category_id == "") {
        $error = "Please select a Category.";
    } elseif ($location_id == "") {
        $error = "Please select Location.";
    } elseif (!$assign_now && $status_id == "") {
        $error = "Please select Status.";
    } elseif ($assign_now && $user_id == "") {
        $error = "Please select User because 'Assign Now' is checked.";
    }

    if ($error == "") {
        $serial_safe = mysqli_real_escape_string($conn, $serial_number);
        $dup = mysqli_query($conn, "SELECT asset_id FROM assets WHERE serial_number = '$serial_safe' LIMIT 1");
        if ($dup && mysqli_num_rows($dup) > 0) {
            $error = "An asset with this Serial Number already exists.";
        }
    }

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
            if ($assign_now) {
                $assigned_status_q = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name='Assigned' LIMIT 1");
                $assigned_status = mysqli_fetch_assoc($assigned_status_q);

                if (!$assigned_status) {
                    throw new Exception("Assigned status not found in asset_status table.");
                }
                $status_id = $assigned_status['status_id'];
            }

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

            if ($has_asset_name_column) {
                $asset_name_sql = ($asset_name !== '') ? "'$asset_name_safe'" : "NULL";
                $insert_asset = "
                    INSERT INTO assets (
                        asset_name, model_id, serial_number, category_id, vendor_id,
                        location_id, status_id, purchase_date, warranty_expiry, cost
                    ) VALUES (
                        '$asset_name_safe', $model_id_sql, '$serial_number_safe', '$category_id_safe', $vendor_id_sql,
                        '$location_id_safe', '$status_id_safe', $purchase_date_sql, $warranty_expiry_sql, $cost_sql
                    )
                ";
            } else {
                $insert_asset = "
                    INSERT INTO assets (
                        asset_name, model_id, serial_number, category_id, vendor_id,
                        location_id, status_id, purchase_date, warranty_expiry, cost
                    ) VALUES (
                        '$asset_name_safe', $model_id_sql, '$serial_number_safe', '$category_id_safe', $vendor_id_sql,
                        '$location_id_safe', '$status_id_safe', $purchase_date_sql, $warranty_expiry_sql, $cost_sql
                    )
                ";
            }

            if (!mysqli_query($conn, $insert_asset)) {
                throw new Exception("Failed to save asset: " . mysqli_error($conn));
            }

            $asset_id = mysqli_insert_id($conn);

            uploadDoc($conn, $asset_id, "sale_order", "SALE_ORDER");
            uploadDoc($conn, $asset_id, "invoice", "INVOICE");
            uploadDoc($conn, $asset_id, "warranty_doc", "WARRANTY");

            if ($assign_now) {
                $asset_id_safe       = mysqli_real_escape_string($conn, $asset_id);
                $user_id_safe        = mysqli_real_escape_string($conn, $user_id);
                $assigned_date_safe  = mysqli_real_escape_string($conn, $assigned_date);
                $assign_remarks_safe = mysqli_real_escape_string($conn, $assign_remarks);

                $check_assign = mysqli_query($conn, "
                    SELECT assignment_id FROM asset_assignments 
                    WHERE asset_id = '$asset_id_safe' AND returned_date IS NULL LIMIT 1
                ");

                if (!$check_assign || mysqli_num_rows($check_assign) == 0) {
                    $insert_assignment = "
                        INSERT INTO asset_assignments (asset_id, user_id, assigned_date, remarks) 
                        VALUES ('$asset_id_safe', '$user_id_safe', '$assigned_date_safe', '$assign_remarks_safe')
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

<div class="container mt-4 mb-5">
    
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0 text-dark"><i class="bi bi-plus-circle-fill text-primary me-2"></i> Add Assets</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="assets_list.php" class="text-decoration-none">Assets Inventory</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Add New</li>
                </ol>
            </nav>
        </div>
        <a href="assets_list.php" class="btn btn-secondary shadow-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <!-- GLOBAL MESSAGES -->
    <?php if ($error != ""): ?>
        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> 
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($success_msg != ""): ?>
        <div class="alert alert-success shadow-sm border-0 d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i> 
            <div><?= $success_msg ?></div>
        </div>
    <?php endif; ?>

    <!-- 1. BULK IMPORT CSV CARD -->
    <div class="card shadow-sm border-0 border-top border-success border-4 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Bulk Import via CSV</h5>
        </div>
        <div class="card-body bg-light">
            <form method="post" enctype="multipart/form-data" class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                <div class="flex-grow-1">
                    <input type="file" name="asset_excel_file" class="form-control shadow-sm" accept=".csv" required>
                </div>
                <button type="submit" name="import_assets_excel" class="btn btn-success fw-bold shadow-sm px-4">
                    <i class="bi bi-upload me-1"></i> Upload & Import
                </button>
            </form>
            <div class="mt-3 text-muted small">
                <i class="bi bi-info-circle-fill text-primary me-1"></i> <strong>Required CSV Column Order:</strong> Assigned User, Category, Serial Number, Department, Vendor, Model, Asset Name, Purchase Date (DD-MM-YYYY)
            </div>
        </div>
    </div>

    <!-- 2. SINGLE ASSET FORM CARD -->
    <div class="card shadow-sm border-0 border-top border-primary border-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-pc-display me-2 text-primary"></i> Add Single Asset</h5>
        </div>
        <div class="card-body p-4 bg-white">

            <form method="post" enctype="multipart/form-data">

                <!-- BASIC INFORMATION -->
                <h6 class="text-primary fw-bold text-uppercase mb-3"><i class="bi bi-info-square me-1"></i> Basic Information</h6>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Asset Name <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" class="form-control shadow-sm"
                            value="<?= htmlspecialchars($_POST['asset_name'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Serial Number <span class="text-danger">*</span></label>
                        <input type="text" name="serial_number" class="form-control text-uppercase shadow-sm"
                            value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- MAIN CATEGORY -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Main Category <span class="text-danger">*</span></label>
                        <select name="main_category_id" id="main_category_id" class="form-select shadow-sm" required>
                            <option value="">Select Main Category</option>
                            <?php
                            mysqli_data_seek($main_categories, 0);
                            while ($row = mysqli_fetch_assoc($main_categories)) {
                                $selected = (($_POST['main_category_id'] ?? '') == $row['category_id']) ? 'selected' : '';
                                echo "<option value='{$row['category_id']}' $selected>" . htmlspecialchars($row['category_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- SUB CATEGORY -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Sub Category</label>
                        <select name="sub_category_id" id="sub_category_id" class="form-select shadow-sm">
                            <option value="">Select Subcategory</option>
                            <?php
                            mysqli_data_seek($sub_categories, 0);
                            while ($row = mysqli_fetch_assoc($sub_categories)) {
                                $selected = (($_POST['sub_category_id'] ?? '') == $row['category_id']) ? 'selected' : '';
                                echo "<option value='{$row['category_id']}' data-parent='{$row['parent_id']}' $selected>" . htmlspecialchars($row['category_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- MODEL -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Model</label>
                        <select name="model_id" id="model_id" class="form-select shadow-sm">
                            <option value="">Select Model</option>
                            <?php
                            mysqli_data_seek($models, 0);
                            while ($row = mysqli_fetch_assoc($models)) {
                                $selected = (($_POST['model_id'] ?? '') == $row['model_id']) ? 'selected' : '';
                                echo "<option value='{$row['model_id']}' data-category='{$row['category_id']}' $selected>" . htmlspecialchars($row['model_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control shadow-sm" id="purchase_date"
                            value="<?= htmlspecialchars($_POST['purchase_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" id="warranty_expiry" class="form-control shadow-sm"
                            value="<?= htmlspecialchars($_POST['warranty_expiry'] ?? '') ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Cost (₹)</label>
                        <input type="number" step="0.01" min="0" name="cost" id="cost" class="form-control shadow-sm"
                            value="<?= htmlspecialchars($_POST['cost'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Vendor</label>
                        <select name="vendor_id" id="vendor_id" class="form-select shadow-sm">
                            <option value="">Select Vendor</option>
                            <?php
                            mysqli_data_seek($vendors, 0);
                            while ($row = mysqli_fetch_assoc($vendors)) {
                                $selected = (($_POST['vendor_id'] ?? '') == $row['vendor_id']) ? 'selected' : '';
                                echo "<option value='{$row['vendor_id']}' $selected>" . htmlspecialchars($row['vendor_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Location <span class="text-danger">*</span></label>
                        <select name="location_id" id="location_id" class="form-select shadow-sm" required>
                            <option value="">Select Location</option>
                            <?php
                            mysqli_data_seek($locations, 0);
                            while ($row = mysqli_fetch_assoc($locations)) {
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
                        <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                        <select name="status_id" id="status_id" class="form-select shadow-sm" required>
                            <option value="">Select Status</option>
                            <?php
                            mysqli_data_seek($statuses, 0);
                            while ($row = mysqli_fetch_assoc($statuses)) {
                                // ONLY SHOW "Assigned" and "Available"
                                if (in_array($row['status_name'], ['Assigned', 'Available'])) {
                                    $selected = (($_POST['status_id'] ?? '') == $row['status_id']) ? 'selected' : '';
                                    echo "<option value='{$row['status_id']}' $selected>" . htmlspecialchars($row['status_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <small class="text-muted">
                            If you assign this below, status becomes <strong>Assigned</strong> automatically.
                        </small>
                    </div>
                </div>

                <!-- PROCUREMENT DOCUMENTS -->
                <h6 class="text-info fw-bold text-uppercase mb-3 border-top pt-4"><i class="bi bi-folder2-open me-1"></i> Procurement Documents</h6>

                <div class="row bg-light p-3 rounded mb-4 shadow-sm border border-light">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <label class="form-label fw-bold text-muted small">Sale Order</label>
                        <input type="file" name="sale_order" class="form-control bg-white">
                    </div>

                    <div class="col-md-4 mb-3 mb-md-0">
                        <label class="form-label fw-bold text-muted small">Invoice</label>
                        <input type="file" name="invoice" class="form-control bg-white">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold text-muted small">Warranty Card</label>
                        <input type="file" name="warranty_doc" class="form-control bg-white">
                    </div>
                </div>

                <!-- OPTIONAL ASSIGNMENT -->
                <h6 class="text-dark fw-bold text-uppercase mb-3 border-top pt-4"><i class="bi bi-person-plus me-1"></i> Fast Assignment (Optional)</h6>

                <div class="card border-dark mb-4 shadow-sm">
                    <div class="card-body bg-light">

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input fs-5" type="checkbox" name="assign_now" id="assign_now" value="1"
                                <?= isset($_POST['assign_now']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold ms-2 pt-1 text-dark" for="assign_now">
                                Assign this asset to a user immediately after saving
                            </label>
                        </div>

                        <div id="assignment_fields" style="display:none;" class="p-3 bg-white border rounded">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Select User</label>
                                    <select name="user_id" id="user_id" class="form-select shadow-sm">
                                        <option value="">-- Choose Employee --</option>
                                        <?php
                                        mysqli_data_seek($users, 0);
                                        while ($row = mysqli_fetch_assoc($users)) {
                                            $selected = (($_POST['user_id'] ?? '') == $row['user_id']) ? 'selected' : '';
                                            echo "<option value='{$row['user_id']}' data-location='{$row['location_id']}' $selected>" . htmlspecialchars($row['name'] . " (" . $row['role'] . ")") . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <small class="text-primary mt-1 d-block"><i class="bi bi-funnel-fill"></i> Automatically filtered based on the Location chosen above.</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Assignment Date</label>
                                    <input type="date" name="assigned_date" class="form-control shadow-sm"
                                        value="<?= htmlspecialchars($_POST['assigned_date'] ?? date('Y-m-d')) ?>">
                                </div>
                            </div>

                            <div class="mb-1">
                                <label class="form-label fw-bold">Remarks / Handover Notes</label>
                                <textarea name="assign_remarks" class="form-control shadow-sm" rows="2"
                                    placeholder="e.g. Handed over with charger and bag..."><?= htmlspecialchars($_POST['assign_remarks'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="assets_list.php" class="btn btn-light border px-4 shadow-sm">Cancel</a>
                    <button type="submit" name="save_asset" class="btn btn-primary px-5 fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Save Asset</button>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ----------------------------------------------------
        // Existing logic for immediate Assignment Toggle
        // ----------------------------------------------------
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
        statusSelect.addEventListener("change", function() {
            if (!assignNow.checked) {
                previousStatus = statusSelect.value;
            }
        });

        document.querySelector("form").addEventListener("submit", function() {
            statusSelect.removeAttribute("disabled");
        });
        toggleAssignmentFields();


        // ----------------------------------------------------
        // NEW LOGIC: Main Category -> Sub Category -> Model Dynamic Filtering
        // ----------------------------------------------------
        const mainCatSelect = document.getElementById("main_category_id");
        const subCatSelect = document.getElementById("sub_category_id");
        const modelSelect = document.getElementById("model_id");

        // Store original options so we can re-filter later
        const allSubCats = Array.from(subCatSelect.options);
        const allModels = Array.from(modelSelect.options);

        // Fetch previous PHP POST values for validation failure re-rendering
        const prevSub = "<?= htmlspecialchars($_POST['sub_category_id'] ?? '') ?>";
        const prevModel = "<?= htmlspecialchars($_POST['model_id'] ?? '') ?>";

        function filterSubCategories() {
            const parentId = mainCatSelect.value;

            // Reset Subcategory Dropdown
            subCatSelect.innerHTML = '<option value="">Select Subcategory</option>';

            // Filter Subcategories where data-parent matches Main Category
            allSubCats.forEach(option => {
                if (option.value === "") return;
                if (option.getAttribute('data-parent') === parentId) {
                    subCatSelect.appendChild(option.cloneNode(true));
                }
            });

            // Restore previous subcategory selection if it still exists in the new list
            if (prevSub && Array.from(subCatSelect.options).some(opt => opt.value === prevSub)) {
                subCatSelect.value = prevSub;
            }

            // Trigger Model filtering immediately after Category/Sub updates
            filterModels();
        }

        function filterModels() {
            const mainId = mainCatSelect.value;
            const subId = subCatSelect.value;

            // If subcategory is chosen, filter by subcategory. Otherwise, filter by main category.
            const targetCategoryId = subId !== "" ? subId : mainId;

            // Reset Model Dropdown
            modelSelect.innerHTML = '<option value="">Select Model</option>';

            // Filter Models where data-category matches target Category ID
            allModels.forEach(option => {
                if (option.value === "") return;
                if (targetCategoryId === "" || option.getAttribute('data-category') === targetCategoryId) {
                    modelSelect.appendChild(option.cloneNode(true));
                }
            });

            // Restore previous model selection if it still exists in the new list
            if (prevModel && Array.from(modelSelect.options).some(opt => opt.value === prevModel)) {
                modelSelect.value = prevModel;
            }
        }

        mainCatSelect.addEventListener('change', filterSubCategories);
        subCatSelect.addEventListener('change', filterModels);

        // Trigger filters on page load to set correct dropdown states
        if (mainCatSelect.value !== "") {
            filterSubCategories();
        }


        // ----------------------------------------------------
        // NEW LOGIC: Model Auto-fill Data Fetching
        // ----------------------------------------------------
        const vendorSelect = document.getElementById("vendor_id");
        const purchaseDateInput = document.getElementById("purchase_date");
        const warrantyExpiryInput = document.getElementById("warranty_expiry");
        const costInput = document.getElementById("cost");

        modelSelect.addEventListener('change', function() {
            const selectedModelId = this.value;

            // If cleared, empty the fields
            if (!selectedModelId) {
                vendorSelect.value = '';
                purchaseDateInput.value = '';
                warrantyExpiryInput.value = '';
                costInput.value = '';
                return;
            }

            // Fetch data for the selected model via AJAX pointing back to this same file
            fetch(window.location.pathname + `?action=get_model_details&model_id=${selectedModelId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && Object.keys(data).length > 0) {
                        if (data.vendor_id) vendorSelect.value = data.vendor_id;
                        if (data.purchase_date) purchaseDateInput.value = data.purchase_date;
                        if (data.warranty_expiry) warrantyExpiryInput.value = data.warranty_expiry;
                        if (data.cost) costInput.value = data.cost;
                    }
                })
                .catch(error => {
                    console.error('Error fetching model details:', error);
                });
        });

        // ----------------------------------------------------
        // Location -> User Dynamic Filtering
        // ----------------------------------------------------
        const locationSelect = document.getElementById("location_id");
        const userSelect = document.getElementById("user_id");

        if (locationSelect && userSelect) {
            // Store original user options so we can re-filter later
            const allUsers = Array.from(userSelect.options);

            locationSelect.addEventListener('change', function() {
                const selectedLocationId = this.value;

                // Reset the User dropdown
                userSelect.innerHTML = '<option value="">-- Choose Employee --</option>';

                // Repopulate matching users based on Location ID
                allUsers.forEach(option => {
                    if (option.value === "") return; // Skip the placeholder

                    // If no location is selected, or the user's location matches the selected location
                    if (selectedLocationId === "" || option.getAttribute('data-location') === selectedLocationId) {
                        userSelect.appendChild(option.cloneNode(true));
                    }
                });
            });

            // Initialize user filter if a location is already selected (e.g. page refresh)
            if (locationSelect.value) {
                locationSelect.dispatchEvent(new Event('change'));
            }
        }
    });
</script>

<?php 
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>