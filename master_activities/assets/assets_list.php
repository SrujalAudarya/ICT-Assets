<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */

/**
 * Parse purchase date into Y-m-d or return NULL
 */
function parsePurchaseDate($rawDate) {
    $rawDate = trim($rawDate);
    if ($rawDate === '') return null;

    // dd-mm-yyyy
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('-', $rawDate);
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) {
            return "$yy-$mm-$dd";
        }
    }

    // dd.mm.yyyy
    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('.', $rawDate);
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) {
            return "$yy-$mm-$dd";
        }
    }

    // dd.mm.yy
    if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('.', $rawDate);
        $yy = '20' . $yy;
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) {
            return "$yy-$mm-$dd";
        }
    }

    // dd-mm-yy
    if (preg_match('/^\d{2}-\d{2}-\d{2}$/', $rawDate)) {
        [$dd, $mm, $yy] = explode('-', $rawDate);
        $yy = '20' . $yy;
        if (checkdate((int)$mm, (int)$dd, (int)$yy)) {
            return "$yy-$mm-$dd";
        }
    }

    // yyyy-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
        return $rawDate;
    }

    return null;
}

/**
 * Detect delimiter and split line
 */
function parseCsvLine($line) {
    $line = trim($line);

    if ($line === '') return [];

    if (strpos($line, "\t") !== false) {
        return str_getcsv($line, "\t");
    } elseif (strpos($line, ";") !== false) {
        return str_getcsv($line, ";");
    } else {
        return str_getcsv($line, ",");
    }
}

/**
 * Get or create category_id from asset_categories
 */
function getCategoryId($conn, $categoryName) {
    $categoryName = trim($categoryName);
    if ($categoryName === '') return null;

    $categoryNameEsc = mysqli_real_escape_string($conn, $categoryName);
    $res = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE category_name = '$categoryNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['category_id'];
    }

    mysqli_query($conn, "INSERT INTO asset_categories (category_name) VALUES ('$categoryNameEsc')");
    return mysqli_insert_id($conn);
}

/**
 * Get or create location_id from locations using dept_name
 */
function getLocationId($conn, $deptName) {
    $deptName = trim($deptName);
    if ($deptName === '') return null;

    $deptNameEsc = mysqli_real_escape_string($conn, $deptName);
    $res = mysqli_query($conn, "SELECT location_id FROM locations WHERE dept_name = '$deptNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['location_id'];
    }

    mysqli_query($conn, "INSERT INTO locations (dept_name) VALUES ('$deptNameEsc')");
    return mysqli_insert_id($conn);
}

/**
 * Get or create model_id from asset_models
 */
function getModelId($conn, $modelName, $category_id = null, $vendor_id = null) {
    $modelName = trim($modelName);
    if ($modelName === '') return null;

    $modelNameEsc = mysqli_real_escape_string($conn, $modelName);
    $res = mysqli_query($conn, "SELECT model_id FROM asset_models WHERE model_name = '$modelNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['model_id'];
    }

    $categorySql = $category_id ? $category_id : "NULL";
    $vendorSql   = $vendor_id ? $vendor_id : "NULL";

    mysqli_query($conn, "
        INSERT INTO asset_models (model_name, category_id, vendor_id)
        VALUES ('$modelNameEsc', $categorySql, $vendorSql)
    ");
    return mysqli_insert_id($conn);
}

/**
 * Get or create vendor_id from vendors
 */
function getVendorId($conn, $vendorName) {
    $vendorName = trim($vendorName);
    if ($vendorName === '') return null;

    $vendorNameEsc = mysqli_real_escape_string($conn, $vendorName);
    $res = mysqli_query($conn, "SELECT vendor_id FROM vendors WHERE vendor_name = '$vendorNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['vendor_id'];
    }

    mysqli_query($conn, "INSERT INTO vendors (vendor_name) VALUES ('$vendorNameEsc')");
    return mysqli_insert_id($conn);
}

/**
 * Get status_id
 */
function getStatusId($conn, $hasAssignedUser = false) {
    if ($hasAssignedUser) {
        $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Assigned' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            return (int)$row['status_id'];
        }
    }

    $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['status_id'];
    }

    $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Working' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['status_id'];
    }

    $res = mysqli_query($conn, "SELECT status_id FROM asset_status ORDER BY status_id ASC LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['status_id'];
    }

    return null;
}

/* =========================================================
   IMPORT ASSETS FROM CSV
   CSV format:
   Assigned User Name,Category,Serial Number,Department,Vendor,Asset Code,Model Name,Asset Name,Purchase Date
   ========================================================= */
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

                    // Skip header
                    if ($rowCount == 1) {
                        continue;
                    }

                    $line = trim($line);
                    if ($line === '') continue;

                    $row = parseCsvLine($line);

                    /*
                      [0] Assigned User Name
                      [1] Category
                      [2] Serial Number
                      [3] Department
                      [4] Vendor
                      [5] Asset Code
                      [6] Model Name
                      [7] Asset Name
                      [8] Purchase Date
                    */

                    if (count($row) < 9 && isset($row[0])) {
                        $row = preg_split('/\s{2,}|\t/', trim($row[0]));
                    }

                    $assignedUserName = trim($row[0] ?? '');
                    $categoryName     = trim($row[1] ?? '');
                    $serialNumber     = trim($row[2] ?? '');
                    $deptName         = trim($row[3] ?? '');
                    $vendorName       = trim($row[4] ?? '');
                    $assetCode        = trim($row[5] ?? ''); // ignore if not needed
                    $modelName        = trim($row[6] ?? '');
                    $assetName        = trim($row[7] ?? '');
                    $purchaseDateRaw  = trim($row[8] ?? '');

                    // Skip fully blank rows
                    if (
                        $assignedUserName === '' &&
                        $categoryName === '' &&
                        $serialNumber === '' &&
                        $deptName === '' &&
                        $vendorName === '' &&
                        $assetCode === '' &&
                        $modelName === '' &&
                        $assetName === '' &&
                        $purchaseDateRaw === ''
                    ) {
                        continue;
                    }

                    // Required fields
                    if ($serialNumber === '' || $assetName === '') {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Serial Number or Asset Name missing";
                        continue;
                    }

                    $assignedUserNameEsc = mysqli_real_escape_string($conn, $assignedUserName);
                    $serialNumberEsc     = mysqli_real_escape_string($conn, $serialNumber);
                    $assetNameEsc        = mysqli_real_escape_string($conn, $assetName);

                    // Duplicate serial check
                    $dupRes = mysqli_query($conn, "SELECT asset_id FROM assets WHERE serial_number = '$serialNumberEsc' LIMIT 1");
                    if ($dupRes && mysqli_num_rows($dupRes) > 0) {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Duplicate serial number ($serialNumber)";
                        continue;
                    }

                    // Foreign keys
                    $category_id = getCategoryId($conn, $categoryName);
                    $vendor_id   = getVendorId($conn, $vendorName);
                    $location_id = getLocationId($conn, $deptName);
                    $model_id    = getModelId($conn, $modelName, $category_id, $vendor_id);
                    $status_id   = getStatusId($conn, !empty($assignedUserName));

                    // Purchase date
                    $parsedDate = parsePurchaseDate($purchaseDateRaw);
                    $purchaseDateSql = $parsedDate ? "'$parsedDate'" : "NULL";

                    // INSERT INTO assets
                    $insertAsset = "
                        INSERT INTO assets (
                            asset_name,
                            model_id,
                            serial_number,
                            category_id,
                            vendor_id,
                            location_id,
                            status_id,
                            purchase_date,
                            cost
                        ) VALUES (
                            '$assetNameEsc',
                            " . ($model_id ? $model_id : "NULL") . ",
                            '$serialNumberEsc',
                            " . ($category_id ? $category_id : "NULL") . ",
                            " . ($vendor_id ? $vendor_id : "NULL") . ",
                            " . ($location_id ? $location_id : "NULL") . ",
                            " . ($status_id ? $status_id : "NULL") . ",
                            $purchaseDateSql,
                            0
                        )
                    ";

                    if (!mysqli_query($conn, $insertAsset)) {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Asset insert failed - " . mysqli_error($conn);
                        continue;
                    }

                    $asset_id = mysqli_insert_id($conn);

                    // Insert assignment if user exists
                    if ($assignedUserName !== '') {
                        $userRes = mysqli_query($conn, "SELECT user_id FROM users WHERE name = '$assignedUserNameEsc' LIMIT 1");

                        if ($userRes && mysqli_num_rows($userRes) > 0) {
                            $userRow = mysqli_fetch_assoc($userRes);
                            $user_id = (int)$userRow['user_id'];

                            $assignQuery = "
                                INSERT INTO asset_assignments (
                                    asset_id,
                                    user_id,
                                    assigned_date,
                                    returned_date,
                                    remarks
                                ) VALUES (
                                    $asset_id,
                                    $user_id,
                                    CURDATE(),
                                    NULL,
                                    NULL
                                )
                            ";

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

include("../../includes/header.php");
include("../../includes/sidebar.php");

/* =========================================================
   FILTERS
   ========================================================= */
$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$status   = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';
$model    = $_GET['model'] ?? '';

$where = "WHERE 1=1";

if ($search != "") {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
        a.asset_name LIKE '%$search_escaped%' OR
        a.serial_number LIKE '%$search_escaped%'
    )";
}

if ($category != "") {
    $category = (int)$category;
    $where .= " AND a.category_id = $category";
}

if ($status != "") {
    $status = (int)$status;
    $where .= " AND a.status_id = $status";
}

if ($location != "") {
    $location = (int)$location;
    $where .= " AND a.location_id = $location";
}

if ($model != "") {
    $model = (int)$model;
    $where .= " AND a.model_id = $model";
}

/* =========================================================
   PAGINATION
   ========================================================= */
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

/* =========================================================
   LIST QUERY
   ========================================================= */
$query = "
SELECT 
    a.*,
    c.category_name,
    s.status_name,
    l.dept_name,
    m.model_name,
    v.vendor_name,
    aa.assignment_id,
    aa.user_id,
    u.name AS assigned_user_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN locations l ON a.location_id = l.location_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
LEFT JOIN vendors v ON a.vendor_id = v.vendor_id
LEFT JOIN asset_assignments aa 
    ON a.asset_id = aa.asset_id 
   AND aa.returned_date IS NULL
LEFT JOIN users u ON aa.user_id = u.user_id
$where
ORDER BY a.asset_id ASC
LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Assets Inventory</h2>

        <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
            <a href="assets_add.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Asset
            </a>

            <button type="button" class="btn btn-success" onclick="document.getElementById('assetExcelFile').click();">
                <i class="bi bi-file-earmark-arrow-up"></i> Add From CSV
            </button>

            <input type="file"
                   name="asset_excel_file"
                   id="assetExcelFile"
                   accept=".csv"
                   style="display:none;"
                   onchange="document.getElementById('assetImportBtn').click();">

            <button type="submit" name="import_assets_excel" id="assetImportBtn" style="display:none;">Import</button>
        </form>
    </div>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success"><?= $success_msg ?></div>
    <?php endif; ?>

    <!-- FILTER FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Asset Name / Serial No"
                           value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php
                        $cats = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY category_name ASC");
                        while($c = mysqli_fetch_assoc($cats)){
                            $selected = ($category == $c['category_id']) ? 'selected' : '';
                            echo "<option value='{$c['category_id']}' $selected>" . htmlspecialchars($c['category_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Model</label>
                    <select name="model" class="form-select">
                        <option value="">All Models</option>
                        <?php
                        $mods = mysqli_query($conn, "SELECT * FROM asset_models ORDER BY model_name ASC");
                        while($m = mysqli_fetch_assoc($mods)){
                            $selected = ($model == $m['model_id']) ? 'selected' : '';
                            echo "<option value='{$m['model_id']}' $selected>" . htmlspecialchars($m['model_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php
                        $sts = mysqli_query($conn, "SELECT * FROM asset_status ORDER BY status_name ASC");
                        while($s = mysqli_fetch_assoc($sts)){
                            $selected = ($status == $s['status_id']) ? 'selected' : '';
                            echo "<option value='{$s['status_id']}' $selected>" . htmlspecialchars($s['status_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Location</label>
                    <select name="location" class="form-select">
                        <option value="">All Locations</option>
                        <?php
                        $loc = mysqli_query($conn, "SELECT * FROM locations ORDER BY dept_name ASC");
                        while($l = mysqli_fetch_assoc($loc)){
                            $selected = ($location == $l['location_id']) ? 'selected' : '';
                            echo "<option value='{$l['location_id']}' $selected>" . htmlspecialchars($l['dept_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

<!-- TABLE -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Sr No</th>
                        <th>Asset Name</th>
                        <th>Serial No</th>
                        <th>Category</th>
                        <th>Model</th>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Assigned To</th>
                        <th>Purchase Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result && mysqli_num_rows($result) > 0): ?>
                        <?php $sr = $offset + 1; ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $sr++ ?></td>

                                <td class="fw-bold">
                                    <a href="asset_details.php?id=<?= $row['asset_id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($row['asset_name']) ?>
                                    </a>
                                </td>

                                <td><code><?= htmlspecialchars($row['serial_number']) ?></code></td>
                                <td><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['model_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['vendor_name'] ?? 'N/A') ?></td>

                                <td>
                                    <?php
                                    $badge_class = 'bg-secondary';
                                    if (($row['status_name'] ?? '') == 'Assigned') $badge_class = 'bg-primary';
                                    elseif (in_array(($row['status_name'] ?? ''), ['Available', 'Working'])) $badge_class = 'bg-success';
                                    elseif (($row['status_name'] ?? '') == 'Under Repair') $badge_class = 'bg-warning text-dark';
                                    elseif (in_array(($row['status_name'] ?? ''), ['Retired', 'Condemned'])) $badge_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= htmlspecialchars($row['status_name'] ?? 'N/A') ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($row['dept_name'] ?? 'N/A') ?></td>

                                <td>
                                    <?php if(!empty($row['assigned_user_name'])): ?>
                                        <?= htmlspecialchars($row['assigned_user_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not Assigned</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= !empty($row['purchase_date']) ? date('d-m-Y', strtotime($row['purchase_date'])) : '-' ?>
                                </td>

                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="asset_details.php?id=<?= $row['asset_id'] ?>" class="btn btn-info">View</a>
                                        <a href="assets_edit.php?id=<?= $row['asset_id'] ?>" class="btn btn-warning">Edit</a>
                                        <a href="asset_delete.php?id=<?= $row['asset_id'] ?>"
                                           class="btn btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this asset?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">No assets found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- PAGINATION -->
    <?php
    $countQuery = "SELECT COUNT(*) AS total FROM assets a $where";
    $countResult = mysqli_query($conn, $countQuery);
    $totalRows = 0;
    if ($countResult) {
        $countRow = mysqli_fetch_assoc($countResult);
        $totalRows = (int)$countRow['total'];
    }
    $totalPages = ceil($totalRows / $limit);
    ?>

    <?php if($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&model=<?= urlencode($model) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <div class="mt-3">
        <small class="text-muted">
            CSV format for import:<br>
            <b>Asset Name, Serial Number, Category, Model Name, Vendor, Status, Department, Assigned User Name, Purchase Date</b>
        </small>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>