<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */

function parsePurchaseDate($rawDate)
{
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

function parseCsvLine($line)
{
    $line = trim($line);
    if ($line === '') return [];
    if (strpos($line, "\t") !== false) return str_getcsv($line, "\t");
    elseif (strpos($line, ";") !== false) return str_getcsv($line, ";");
    else return str_getcsv($line, ",");
}

function getCategoryId($conn, $categoryName)
{
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

function getLocationId($conn, $deptName)
{
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

function getModelId($conn, $modelName, $category_id = null, $vendor_id = null)
{
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
    mysqli_query($conn, "INSERT INTO asset_models (model_name, category_id, vendor_id) VALUES ('$modelNameEsc', $categorySql, $vendorSql)");
    return mysqli_insert_id($conn);
}

function getVendorId($conn, $vendorName)
{
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

function getStatusId($conn, $hasAssignedUser = false)
{
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
                    if ($rowCount == 1) continue;
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

                    if (
                        $assignedUserName === '' && $categoryName === '' && $serialNumber === '' &&
                        $deptName === '' && $vendorName === '' && $modelName === '' &&
                        $assetName === '' && $purchaseDateRaw === ''
                    ) {
                        continue;
                    }

                    if ($serialNumber === '' || $assetName === '') {
                        die("Validation Failed<br>Serial = [" . $serialNumber . "]<br>Asset = [" . $assetName . "]");
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

                    $category_id = getCategoryId($conn, $categoryName);
                    $vendor_id   = getVendorId($conn, $vendorName);
                    $location_id = getLocationId($conn, $deptName);
                    $model_id    = getModelId($conn, $modelName, $category_id, $vendor_id);
                    $status_id   = getStatusId($conn, !empty($assignedUserName));

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
        a.serial_number LIKE '%$search_escaped%' OR
        u.name LIKE '%$search_escaped%'
    )";
}

if ($category != "") {
    $category = (int)$category;
    // SMART CATEGORY FILTER: Fetch the main category AND all its subcategories
    $cat_ids = [$category];
    $sub_cats_query = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE parent_id = $category");
    if ($sub_cats_query) {
        while ($sub = mysqli_fetch_assoc($sub_cats_query)) {
            $cat_ids[] = $sub['category_id'];
        }
    }
    $cat_ids_str = implode(',', $cat_ids);
    $where .= " AND a.category_id IN ($cat_ids_str)";
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
   EXPORT LOGIC
   ========================================================= */
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {

    $isExcel = ($_GET['export'] === 'excel');

    $exportQuery = "
        SELECT 
            a.*,
            c.category_name,
            s.status_name,
            l.dept_name,
            m.model_name,
            v.vendor_name,
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
    ";

    $exportResult = mysqli_query($conn, $exportQuery);

    if ($isExcel) {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Assets_Inventory_' . date('Y-m-d_H-i') . '.xls"');
        $delimiter = "\t";
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Assets_Inventory_' . date('Y-m-d_H-i') . '.csv"');
        $delimiter = ",";
    }

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Sr No', 'Asset Name', 'Serial No', 'Category', 'Model', 'Vendor', 'Status', 'Location', 'Assigned To', 'Purchase Date', 'Warranty Expiry Date'], $delimiter);

    $sr = 1;
    while ($row = mysqli_fetch_assoc($exportResult)) {
        $purchaseDate = !empty($row['purchase_date']) ? date('d-m-Y', strtotime($row['purchase_date'])) : '-';
        $warrantyExpiry = !empty($row['warranty_expiry']) ? date('d-m-Y', strtotime($row['warranty_expiry'])) : '-';

        fputcsv($output, [
            $sr++,
            $row['asset_name'],
            $row['serial_number'],
            $row['category_name'] ?? 'N/A',
            $row['model_name'] ?? 'N/A',
            $row['vendor_name'] ?? 'N/A',
            $row['status_name'] ?? 'N/A',
            $row['dept_name'] ?? 'N/A',
            $row['assigned_user_name'] ?? 'Not Assigned',
            $purchaseDate,
            $warrantyExpiry
        ], $delimiter);
    }
    fclose($output);
    exit();
}

include("../../includes/header.php");
include("../../includes/sidebar.php");

/* =========================================================
   PAGINATION 
   ========================================================= */
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$query = "
SELECT 
    a.*,
    c.category_name,
    s.status_name,
    l.dept_name,
    l.floor,
    m.model_name,
    m.model_image,
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
ORDER BY a.asset_id DESC
LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);

$exportParams = $_GET;
$exportParams['export'] = 'csv';
$exportCsvUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'excel';
$exportExcelUrl = '?' . http_build_query($exportParams);
?>

<div class="container-fluid mt-4">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-0 text-dark"><i class="bi bi-pc-display-horizontal text-primary me-2"></i> Assets Inventory</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="../dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">All Assets</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <!-- EXPORT DROPDOWN -->
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle fw-bold shadow-sm" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu shadow" aria-labelledby="exportDropdown">
                    <li>
                        <a class="dropdown-item py-2" href="<?= $exportCsvUrl ?>">
                            <i class="bi bi-filetype-csv text-primary me-2"></i> Export as CSV
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?= $exportExcelUrl ?>">
                            <i class="bi bi-file-earmark-excel text-success me-2"></i> Export as Excel
                        </a>
                    </li>
                </ul>
            </div>

            <a href="assets_add.php" class="btn btn-primary fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New Asset
            </a>

            <form method="post" enctype="multipart/form-data" class="d-inline mb-0">
                <button type="button" class="btn btn-success fw-bold shadow-sm" onclick="document.getElementById('assetExcelFile').click();">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV
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
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger d-flex align-items-center shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success d-flex align-items-center shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> <?= $success_msg ?></div>
    <?php endif; ?>

    <!-- COLLAPSIBLE SMART FILTER FORM -->
    <div class="card mb-4 shadow-sm border-0 border-top border-primary border-4 bg-light">
        <div class="card-header bg-transparent border-bottom-0 d-flex justify-content-between align-items-center pt-3 pb-0">
            <h6 class="mb-0 text-muted fw-bold text-uppercase"><i class="bi bi-funnel-fill me-1"></i> Filter Inventory</h6>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="true" aria-controls="filterPanel">
                <i class="bi bi-arrows-collapse"></i> Toggle Filters
            </button>
        </div>

        <div class="collapse show" id="filterPanel">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Search</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0"
                                placeholder="Name / Serial / User..."
                                value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Category</label>
                        <select name="category" id="filter_category" class="form-select shadow-sm">
                            <option value="">All Categories</option>
                            <?php
                            $cats = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id = 0 OR parent_id IS NULL ORDER BY category_name ASC");
                            while ($c = mysqli_fetch_assoc($cats)) {
                                $selected = ($category == $c['category_id']) ? 'selected' : '';
                                echo "<option value='{$c['category_id']}' $selected>" . htmlspecialchars($c['category_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Model</label>
                        <select name="model" id="filter_model" class="form-select shadow-sm">
                            <option value="">All Models</option>
                            <?php
                            $mods = mysqli_query($conn, "SELECT * FROM asset_models ORDER BY model_name ASC");
                            while ($m = mysqli_fetch_assoc($mods)) {
                                $selected = ($model == $m['model_id']) ? 'selected' : '';
                                echo "<option value='{$m['model_id']}' data-category='{$m['category_id']}' $selected>" . htmlspecialchars($m['model_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Status</label>
                        <select name="status" class="form-select shadow-sm fw-semibold text-primary">
                            <option value="">All Statuses</option>
                            <?php
                            $sts = mysqli_query($conn, "SELECT * FROM asset_status WHERE status_name IN ('Assigned', 'Available') ORDER BY status_name ASC");
                            while ($s = mysqli_fetch_assoc($sts)) {
                                $selected = ($status == $s['status_id']) ? 'selected' : '';
                                echo "<option value='{$s['status_id']}' $selected>" . htmlspecialchars($s['status_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Location</label>
                        <select name="location" class="form-select shadow-sm">
                            <option value="">All Locations</option>
                            <?php
                            $loc = mysqli_query($conn, "SELECT * FROM locations ORDER BY dept_name ASC");
                            while ($l = mysqli_fetch_assoc($loc)) {
                                $selected = ($location == $l['location_id']) ? 'selected' : '';
                                echo "<option value='{$l['location_id']}' $selected>" . htmlspecialchars($l['dept_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100 shadow-sm fw-bold"><i class="bi bi-funnel"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th class="ps-3 border-bottom-0">#</th>
                            <th class="text-center border-bottom-0" style="width: 60px;">Image</th>
                            <th class="border-bottom-0">Asset Details</th>
                            <th class="border-bottom-0">Classification</th>
                            <th class="border-bottom-0">Status & Location</th>
                            <th class="border-bottom-0">Assignment & Warranty</th>
                            <th class="text-center border-bottom-0">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php $sr = $offset + 1; ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="ps-3 text-muted fw-bold"><?= $sr++ ?></td>

                                    <td class="text-center">
                                        <?php if (!empty($row['model_image'])): ?>
                                            <div class="bg-white border rounded p-1 shadow-sm d-inline-block" style="width: 45px; height: 45px;">
                                                <img src="../../<?= htmlspecialchars($row['model_image']) ?>" alt="Model" style="width: 100%; height: 100%; object-fit: contain;">
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-light border text-muted d-flex align-items-center justify-content-center rounded shadow-sm d-inline-flex" style="width: 45px; height: 45px;">
                                                <i class="bi bi-pc-display fs-5"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <a href="asset_details.php?id=<?= $row['asset_id'] ?>" class="text-decoration-none fw-bold text-dark d-block">
                                            <?= htmlspecialchars($row['asset_name']) ?>
                                        </a>
                                        <div class="d-flex align-items-center mt-1">
                                            <code class="small text-primary bg-primary bg-opacity-10 px-2 py-1 rounded me-2" id="sn-<?= $row['asset_id'] ?>"><?= htmlspecialchars($row['serial_number']) ?></code>
                                            <button class="btn btn-sm btn-light border py-0 px-1 text-muted" onclick="copyToClipboard('<?= htmlspecialchars($row['serial_number']) ?>')" title="Copy Serial Number">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge bg-secondary mb-1"><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></span>
                                        <div class="small text-muted text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($row['model_name'] ?? '') ?>">
                                            <?= htmlspecialchars($row['model_name'] ?? 'N/A') ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php
                                        $badge_class = 'bg-secondary';
                                        if (($row['status_name'] ?? '') == 'Assigned') $badge_class = 'bg-primary';
                                        elseif (in_array(($row['status_name'] ?? ''), ['Available', 'Working'])) $badge_class = 'bg-success';
                                        elseif (($row['status_name'] ?? '') == 'Under Repair') $badge_class = 'bg-warning text-dark';
                                        elseif (in_array(($row['status_name'] ?? ''), ['Retired', 'Condemned'])) $badge_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $badge_class ?> rounded-pill mb-1 d-inline-block">
                                            <?= htmlspecialchars($row['status_name'] ?? 'N/A') ?>
                                        </span>
                                        <div class="small text-dark fw-semibold">
                                            <i class="bi bi-geo-alt text-danger"></i>
                                            <?= htmlspecialchars($row['dept_name'] ?? 'N/A') ?>
                                            <?= !empty($row['floor']) ? " <span class='text-muted'>({$row['floor']})</span>" : "" ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (!empty($row['assigned_user_name'])): ?>
                                            <div class="fw-bold text-dark"><i class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($row['assigned_user_name']) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Not Assigned</span>
                                        <?php endif; ?>

                                        <div class="small text-muted mt-1 d-flex align-items-center">
                                            <span title="Purchase Date"><i class="bi bi-calendar3 me-1"></i><?= !empty($row['purchase_date']) ? date('d M Y', strtotime($row['purchase_date'])) : '-' ?></span>

                                            <?php
                                            if (!empty($row['warranty_expiry'])) {
                                                $exp_time = strtotime($row['warranty_expiry']);
                                                $days_left = floor(($exp_time - time()) / (60 * 60 * 24));

                                                if ($days_left < 0) {
                                                    echo "<span class='badge bg-danger bg-opacity-10 text-danger border border-danger ms-2' title='Warranty Expired'><i class='bi bi-shield-x'></i> Expired</span>";
                                                } elseif ($days_left <= 30) {
                                                    echo "<span class='badge bg-warning bg-opacity-10 text-warning border border-warning ms-2' title='Expires in {$days_left} days'><i class='bi bi-shield-exclamation'></i> Exp. Soon</span>";
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm shadow-sm">
                                            <a href="asset_details.php?id=<?= $row['asset_id'] ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <a href="assets_edit.php?id=<?= $row['asset_id'] ?>" class="btn btn-outline-warning" title="Edit Asset">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <a href="asset_delete.php?id=<?= $row['asset_id'] ?>" class="btn btn-outline-danger" title="Delete Asset" onclick="return confirm('Are you sure you want to delete this asset?')">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inboxes fs-1 d-block mb-3 opacity-50"></i>
                                    <h5>No assets found.</h5>
                                    <p class="mb-0">Try adjusting your filters or add a new asset.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PAGINATION -->
        <?php
        $countQuery = "
            SELECT COUNT(DISTINCT a.asset_id) AS total
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
        ";
        $countResult = mysqli_query($conn, $countQuery);
        $totalRows = 0;
        if ($countResult) {
            $countRow = mysqli_fetch_assoc($countResult);
            $totalRows = (int)$countRow['total'];
        }
        $totalPages = ceil($totalRows / $limit);
        ?>

        <?php if ($totalPages > 0): ?>
            <div class="card-footer bg-white border-0 py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="text-muted small mb-2 mb-md-0">
                        Showing <span class="fw-bold text-dark"><?= $offset + 1 ?></span> to <span class="fw-bold text-dark"><?= min($offset + $limit, $totalRows) ?></span> of <span class="fw-bold text-dark"><?= $totalRows ?></span> entries
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0 shadow-sm">
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link text-dark" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&model=<?= urlencode($model) ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link <?= ($page == $i) ? 'bg-primary border-primary' : 'text-dark' ?>"
                                            href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&model=<?= urlencode($model) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                    <a class="page-link text-dark" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&model=<?= urlencode($model) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- PREPARE CATEGORY HIERARCHY FOR JAVASCRIPT FILTERING -->
    <?php
    $cat_hierarchy = [];
    $all_cats_query = mysqli_query($conn, "SELECT category_id, parent_id FROM asset_categories");
    while ($cat_row = mysqli_fetch_assoc($all_cats_query)) {
        $id = $cat_row['category_id'];
        $parent = (!empty($cat_row['parent_id']) && $cat_row['parent_id'] > 0) ? $cat_row['parent_id'] : $id;

        if (!isset($cat_hierarchy[$parent])) {
            $cat_hierarchy[$parent] = [];
        }
        $cat_hierarchy[$parent][] = (string)$id;
    }
    ?>

    <script>
        function copyToClipboard(text) {
            // Modern approach (Requires HTTPS or localhost)
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Copied Serial Number: ' + text);
                }).catch(function(err) {
                    console.error('Could not copy text: ', err);
                });
            } else {
                // Fallback approach (Works on standard HTTP)
                let textArea = document.createElement("textarea");
                textArea.value = text;

                // Prevent scrolling to the bottom of the page
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                textArea.style.top = "-999999px";

                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    document.execCommand('copy');
                    alert('Copied Serial Number: ' + text);
                } catch (err) {
                    console.error('Fallback copy failed: ', err);
                    alert('Failed to copy. Please copy manually.');
                }

                textArea.remove();
            }
        }
        // Dynamic Filter for Models based on Category hierarchy
        document.addEventListener("DOMContentLoaded", function() {
            const catSelect = document.getElementById("filter_category");
            const modelSelect = document.getElementById("filter_model");

            if (catSelect && modelSelect) {
                const allModels = Array.from(modelSelect.options);
                const catHierarchy = <?= json_encode($cat_hierarchy) ?>;

                function filterModels() {
                    const selectedCat = catSelect.value;
                    const currentModel = modelSelect.value;

                    modelSelect.innerHTML = '<option value="">All Models</option>';

                    let allowedCats = [];
                    if (selectedCat !== "") {
                        // Get all subcategories tied to this main category
                        allowedCats = catHierarchy[selectedCat] || [selectedCat];
                        if (!allowedCats.includes(selectedCat)) allowedCats.push(selectedCat);
                    }

                    allModels.forEach(option => {
                        if (option.value === "") return;

                        const modelCat = option.getAttribute('data-category');
                        // If no category selected, show all. If category selected, check if it's in the allowed hierarchy list
                        if (selectedCat === "" || allowedCats.includes(modelCat)) {
                            modelSelect.appendChild(option.cloneNode(true));
                        }
                    });

                    // Keep the previously selected model if it still matches the filter
                    if (currentModel && Array.from(modelSelect.options).some(opt => opt.value === currentModel)) {
                        modelSelect.value = currentModel;
                    }
                }

                catSelect.addEventListener('change', filterModels);

                // Trigger immediately if a category was already selected via PHP GET request
                if (catSelect.value !== "") {
                    const urlParams = new URLSearchParams(window.location.search);
                    const initialModel = urlParams.get('model');
                    filterModels();
                    if (initialModel) {
                        modelSelect.value = initialModel;
                    }
                }
            }
        });
    </script>

    <div class="mt-3">
        <div class="alert alert-light border shadow-sm text-muted small">
            <i class="bi bi-info-circle-fill me-2 text-primary"></i>
            <strong>CSV format for import:</strong> Asset Name, Serial Number, Category, Model Name, Vendor, Status, Department, Assigned User Name, Purchase Date
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>