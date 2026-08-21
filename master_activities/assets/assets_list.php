<?php
ob_start(); // CRITICAL: Prevents "Headers already sent" errors for Excel/CSV/PDF exports
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

// FIXED: Upgraded to support creating BOTH Main and Sub Categories automatically from CSV
function getCategoryId($conn, $mainCatName, $subCatName = '')
{
    $mainCatName = trim($mainCatName);
    if ($mainCatName === '') return null;
    
    $mainEsc = mysqli_real_escape_string($conn, $mainCatName);
    $resMain = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE category_name = '$mainEsc' AND (parent_id = 0 OR parent_id IS NULL) LIMIT 1");
    if ($resMain && mysqli_num_rows($resMain) > 0) {
        $mainId = (int)mysqli_fetch_assoc($resMain)['category_id'];
    } else {
        mysqli_query($conn, "INSERT INTO asset_categories (category_name, parent_id) VALUES ('$mainEsc', 0)");
        $mainId = mysqli_insert_id($conn);
    }

    $subCatName = trim($subCatName);
    if ($subCatName === '') return $mainId;

    $subEsc = mysqli_real_escape_string($conn, $subCatName);
    $resSub = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE category_name = '$subEsc' AND parent_id = '$mainId' LIMIT 1");
    if ($resSub && mysqli_num_rows($resSub) > 0) {
        return (int)mysqli_fetch_assoc($resSub)['category_id'];
    } else {
        mysqli_query($conn, "INSERT INTO asset_categories (category_name, parent_id) VALUES ('$subEsc', '$mainId')");
        return mysqli_insert_id($conn);
    }
}

function getLocationId($conn, $deptName)
{
    $deptName = trim($deptName);
    if ($deptName === '') return null;
    $deptNameEsc = mysqli_real_escape_string($conn, $deptName);
    $res = mysqli_query($conn, "SELECT location_id FROM locations WHERE dept_name = '$deptNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['location_id'];
    mysqli_query($conn, "INSERT INTO locations (dept_name) VALUES ('$deptNameEsc')");
    return mysqli_insert_id($conn);
}

function getModelId($conn, $modelName, $category_id = null, $vendor_id = null)
{
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

function getVendorId($conn, $vendorName)
{
    $vendorName = trim($vendorName);
    if ($vendorName === '') return null;
    $vendorNameEsc = mysqli_real_escape_string($conn, $vendorName);
    $res = mysqli_query($conn, "SELECT vendor_id FROM vendors WHERE vendor_name = '$vendorNameEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['vendor_id'];
    mysqli_query($conn, "INSERT INTO vendors (vendor_name) VALUES ('$vendorNameEsc')");
    return mysqli_insert_id($conn);
}

function getStatusId($conn, $statusName, $hasAssignedUser = false)
{
    if ($hasAssignedUser) {
        $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Assigned' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['status_id'];
    }
    if ($statusName !== '') {
        $statusNameEsc = mysqli_real_escape_string($conn, $statusName);
        $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = '$statusNameEsc' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['status_id'];
    }
    $res = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) return (int)mysqli_fetch_assoc($res)['status_id'];
    return null;
}

/* =========================================================
   IMPORT ASSETS FROM CSV
   ========================================================= */
if (isset($_POST['import_assets_excel'])) {
    if (!empty($_FILES['asset_excel_file']['name'])) {
        $fileName = $_FILES['asset_excel_file']['tmp_name'];
        $handle = fopen($fileName, "r");
        if ($handle !== false) {
            $rowCount = 0;
            $successCount = 0;
            $failCount = 0;
            while (($line = fgets($handle)) !== false) {
                $rowCount++;
                if ($rowCount == 1) continue; // Skip header
                $row = parseCsvLine($line);
                if (empty($row)) continue;

                // FIXED: Updated Array Mapping to perfectly match your 12-column CSV file!
                $assetName        = trim($row[0] ?? '');
                $serialNumber     = trim($row[1] ?? '');
                $mainCategoryName = trim($row[2] ?? '');
                $subCategoryName  = trim($row[3] ?? '');
                $modelName        = trim($row[4] ?? '');
                $vendorName       = trim($row[5] ?? '');
                $deptName         = trim($row[6] ?? '');
                $statusName       = trim($row[7] ?? '');
                $purchaseDateRaw  = trim($row[8] ?? '');
                $warrantyRaw      = trim($row[9] ?? '');
                $costRaw          = trim($row[10] ?? '');
                $assignedUserName = trim($row[11] ?? '');

                if ($assetName === '' || $serialNumber === '') continue;

                $assetNameEsc        = mysqli_real_escape_string($conn, $assetName);
                $serialNumberEsc     = mysqli_real_escape_string($conn, $serialNumber);
                $assignedUserNameEsc = mysqli_real_escape_string($conn, $assignedUserName);

                $dupRes = mysqli_query($conn, "SELECT asset_id FROM assets WHERE serial_number = '$serialNumberEsc' LIMIT 1");
                if ($dupRes && mysqli_num_rows($dupRes) > 0) {
                    $failCount++;
                    continue;
                }

                // FIXED: Assigns main and subcategories
                $category_id = getCategoryId($conn, $mainCategoryName, $subCategoryName);
                $vendor_id   = getVendorId($conn, $vendorName);
                $location_id = getLocationId($conn, $deptName);
                $model_id    = getModelId($conn, $modelName, $category_id, $vendor_id);
                $status_id   = getStatusId($conn, $statusName, !empty($assignedUserName));

                $parsedPurDate = parsePurchaseDate($purchaseDateRaw);
                $purchaseDateSql = $parsedPurDate ? "'$parsedPurDate'" : "NULL";
                
                $parsedWarDate = parsePurchaseDate($warrantyRaw);
                $warrantySql = $parsedWarDate ? "'$parsedWarDate'" : "NULL";

                $costSql = is_numeric($costRaw) ? $costRaw : "0.00";

                // FIXED: Appended warranty_expiry to SQL insert
                $insertAsset = "INSERT INTO assets (asset_name, model_id, serial_number, category_id, vendor_id, location_id, status_id, purchase_date, warranty_expiry, cost) 
                                VALUES ('$assetNameEsc', " . ($model_id ?: "NULL") . ", '$serialNumberEsc', " . ($category_id ?: "NULL") . ", " . ($vendor_id ?: "NULL") . ", " . ($location_id ?: "NULL") . ", " . ($status_id ?: "NULL") . ", $purchaseDateSql, $warrantySql, $costSql)";

                if (mysqli_query($conn, $insertAsset)) {
                    $asset_id = mysqli_insert_id($conn);
                    if ($assignedUserName !== '') {
                        $userRes = mysqli_query($conn, "SELECT user_id FROM users WHERE name = '$assignedUserNameEsc' LIMIT 1");
                        if ($userRes && mysqli_num_rows($userRes) > 0) {
                            $user_id = (int)mysqli_fetch_assoc($userRes)['user_id'];
                            mysqli_query($conn, "INSERT INTO asset_assignments (asset_id, user_id, assigned_date, returned_date, remarks) VALUES ($asset_id, $user_id, CURDATE(), NULL, 'Assigned via Bulk Import')");
                        }
                    }
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            fclose($handle);
            $success_msg = "Import completed successfully. Assets Added: $successCount, Skipped (Duplicates): $failCount";
        } else {
            $error = "Failed to read the uploaded CSV file.";
        }
    } else {
        $error = "Please upload a valid CSV file.";
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
    $where .= " AND (a.asset_name LIKE '%$search_escaped%' OR a.serial_number LIKE '%$search_escaped%' OR u.name LIKE '%$search_escaped%')";
}
if ($category != "") {
    $category = (int)$category;
    $cat_ids = [$category];
    $sub_cats_query = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE parent_id = $category");
    while ($sub = mysqli_fetch_assoc($sub_cats_query)) {
        $cat_ids[] = $sub['category_id'];
    }
    $where .= " AND a.category_id IN (" . implode(',', $cat_ids) . ")";
}
if ($status != "") $where .= " AND a.status_id = " . (int)$status;
if ($location != "") $where .= " AND a.location_id = " . (int)$location;
if ($model != "") $where .= " AND a.model_id = " . (int)$model;

/* =========================================================
   EXPORT LOGIC (PDF, EXCEL, CSV)
   ========================================================= */
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel', 'pdf'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $exportType = $_GET['export'];
    $exportQuery = "
        SELECT a.*, c.category_name, s.status_name, l.dept_name, m.model_name, v.vendor_name, u.name AS assigned_user_name
        FROM assets a
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        LEFT JOIN asset_status s ON a.status_id = s.status_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN asset_models m ON a.model_id = m.model_id
        LEFT JOIN vendors v ON a.vendor_id = v.vendor_id
        LEFT JOIN asset_assignments aa ON a.asset_id = aa.asset_id AND aa.returned_date IS NULL
        LEFT JOIN users u ON aa.user_id = u.user_id
        $where ORDER BY a.asset_id ASC
    ";
    $exportResult = mysqli_query($conn, $exportQuery);

    if ($exportType === 'pdf') {
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <title>Generating PDF...</title>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding-top: 100px;
                    background: #f8f9fa;
                }

                .loader {
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #0d6efd;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 20px;
                }

                @keyframes spin {
                    0% {
                        transform: rotate(0deg);
                    }

                    100% {
                        transform: rotate(360deg);
                    }
                }
            </style>
        </head>

        <body>
            <div class="loader"></div>
            <h2>Generating Full PDF... Please wait.</h2>
            <p style="color: #666;">This window will automatically close once the download starts.</p>

            <table id="pdfTable" style="display:none;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset Name</th>
                        <th>Serial No</th>
                        <th>Category</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Assigned To</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sr = 1;
                    while ($r = mysqli_fetch_assoc($exportResult)) {
                        $pDate = !empty($r['purchase_date']) ? date('d-m-Y', strtotime($r['purchase_date'])) : '-';
                        echo "<tr>
                            <td>{$sr}</td>
                            <td>" . htmlspecialchars($r['asset_name']) . "</td>
                            <td>" . htmlspecialchars($r['serial_number']) . "</td>
                            <td>" . htmlspecialchars($r['category_name'] ?? 'N/A') . "</td>
                            <td>" . htmlspecialchars($r['model_name'] ?? 'N/A') . "</td>
                            <td>" . htmlspecialchars($r['status_name'] ?? 'N/A') . "</td>
                            <td>" . htmlspecialchars($r['dept_name'] ?? 'N/A') . "</td>
                            <td>" . htmlspecialchars($r['assigned_user_name'] ?? 'Not Assigned') . "</td>
                            <td>{$pDate}</td>
                        </tr>";
                        $sr++;
                    }
                    ?>
                </tbody>
            </table>

            <script>
                window.onload = function() {
                    const {
                        jsPDF
                    } = window.jspdf;
                    const doc = new jsPDF('landscape');
                    doc.setFontSize(16);
                    doc.text("Assets Inventory", 14, 15);
                    doc.autoTable({
                        html: '#pdfTable',
                        startY: 25,
                        styles: {
                            fontSize: 9,
                            cellPadding: 3
                        },
                        headStyles: {
                            fillColor: [52, 58, 64]
                        }
                    });
                    doc.save("Assets_Inventory_<?= date('Y-m-d') ?>.pdf");
                    setTimeout(() => window.close(), 1500);
                };
            </script>
        </body>

        </html>
<?php
        exit();
    }

    if ($exportType === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="Assets_Inventory_' . date('Y-m-d_H-i') . '.xls"');
        echo '<table border="1"><tr><th>Sr No</th><th>Asset Name</th><th>Serial No</th><th>Category</th><th>Model</th><th>Vendor</th><th>Status</th><th>Location</th><th>Assigned To</th><th>Purchase Date</th><th>Cost</th></tr>';
        $sr = 1;
        while ($r = mysqli_fetch_assoc($exportResult)) {
            $pDate = !empty($r['purchase_date']) ? date('d-m-Y', strtotime($r['purchase_date'])) : '-';
            echo "<tr><td>{$sr}</td><td>{$r['asset_name']}</td><td>{$r['serial_number']}</td><td>{$r['category_name']}</td><td>{$r['model_name']}</td><td>{$r['vendor_name']}</td><td>{$r['status_name']}</td><td>{$r['dept_name']}</td><td>{$r['assigned_user_name']}</td><td>{$pDate}</td><td>{$r['cost']}</td></tr>";
            $sr++;
        }
        echo '</table>';
        exit();
    }

    if ($exportType === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Assets_Inventory_' . date('Y-m-d_H-i') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Sr No', 'Asset Name', 'Serial No', 'Category', 'Model', 'Vendor', 'Status', 'Location', 'Assigned To', 'Purchase Date', 'Cost']);
        $sr = 1;
        while ($row = mysqli_fetch_assoc($exportResult)) {
            $purchaseDate = !empty($row['purchase_date']) ? date('d-m-Y', strtotime($row['purchase_date'])) : '-';
            fputcsv($output, [$sr++, $row['asset_name'], $row['serial_number'], $row['category_name'] ?? 'N/A', $row['model_name'] ?? 'N/A', $row['vendor_name'] ?? 'N/A', $row['status_name'] ?? 'N/A', $row['dept_name'] ?? 'N/A', $row['assigned_user_name'] ?? 'Not Assigned', $purchaseDate, $row['cost'] ?? '0']);
        }
        fclose($output);
        exit();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");

/* =========================================================
   PAGINATION
   ========================================================= */
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$countQuery = "
    SELECT COUNT(DISTINCT a.asset_id) AS total 
    FROM assets a
    LEFT JOIN asset_categories c ON a.category_id = c.category_id
    LEFT JOIN asset_status s ON a.status_id = s.status_id
    LEFT JOIN locations l ON a.location_id = l.location_id
    LEFT JOIN asset_models m ON a.model_id = m.model_id
    LEFT JOIN vendors v ON a.vendor_id = v.vendor_id
    LEFT JOIN asset_assignments aa ON a.asset_id = aa.asset_id AND aa.returned_date IS NULL
    LEFT JOIN users u ON aa.user_id = u.user_id
    $where
";
$totalRows = mysqli_fetch_assoc(mysqli_query($conn, $countQuery))['total'] ?? 0;
$totalPages = ceil($totalRows / $limit);

$query = "
SELECT a.*, c.category_name, s.status_name, l.dept_name, l.floor, m.model_name, m.model_image, v.vendor_name, aa.assignment_id, aa.user_id, u.name AS assigned_user_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN locations l ON a.location_id = l.location_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
LEFT JOIN vendors v ON a.vendor_id = v.vendor_id
LEFT JOIN asset_assignments aa ON a.asset_id = aa.asset_id AND aa.returned_date IS NULL
LEFT JOIN users u ON aa.user_id = u.user_id
$where ORDER BY a.asset_id DESC LIMIT $limit OFFSET $offset
";
$result = mysqli_query($conn, $query);

$exportParams = $_GET;
$exportParams['export'] = 'csv';
$exportCsvUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'excel';
$exportExcelUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'pdf';
$exportPdfUrl = '?' . http_build_query($exportParams);
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
            <!-- PRINT LABELS BUTTON -->
            <button type="button" class="btn btn-dark fw-bold shadow-sm" id="btnOpenLabelModal">
                <i class="bi bi-qr-code-scan me-1"></i> Print Labels
            </button>

            <!-- EXPORT DROPDOWN WITH NATIVE JS TOGGLE -->
            <div class="dropdown position-relative d-inline-block">
                <button class="btn btn-light bg-white border border-secondary text-dark dropdown-toggle fw-bold shadow-sm" type="button" id="btnExportDropdown">
                    <i class="bi bi-download me-1"></i> Export Inventory
                </button>

                <ul class="dropdown-menu shadow" id="exportDropdownMenu" style="display: none; position: absolute; top: 100%; left: 0; z-index: 1000;">
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportPdfUrl ?>" target="_blank"><i class="bi bi-file-earmark-pdf text-danger me-2"></i> Export as PDF</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportExcelUrl ?>"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export as Excel (.xls)</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportCsvUrl ?>"><i class="bi bi-file-earmark-text text-primary me-2"></i> Export as CSV</a></li>
                </ul>
            </div>

            <a href="assets_add.php" class="btn btn-primary fw-bold shadow-sm"><i class="bi bi-plus-circle me-1"></i> Add Asset</a>

            <button type="button" class="btn btn-success fw-bold shadow-sm" onclick="document.getElementById('assetExcelFile').click();"><i class="bi bi-upload me-1"></i> Import</button>
            <form method="post" enctype="multipart/form-data" class="d-none" id="importForm">
                <input type="file" name="asset_excel_file" id="assetExcelFile" accept=".csv" onchange="document.getElementById('importForm').submit();">
                <input type="hidden" name="import_assets_excel" value="1">
            </form>
        </div>
    </div>

    <!-- ADDED: IMPORT SUCCESS / ERROR MESSAGES -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> 
            <?= $error ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i> 
            <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <!-- DYNAMIC ALERTS -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-success shadow-sm border-0 d-flex align-items-center mb-4"><i class="bi bi-trash-fill me-2 fs-5"></i> Asset completely deleted from inventory.</div>
        <?php elseif ($_GET['msg'] == 'cannot_delete_assigned'): ?>
            <div class="alert alert-warning shadow-sm border-0 d-flex align-items-center mb-4"><i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> <strong>Action Blocked:</strong> Cannot delete this asset because it is currently assigned to a user.</div>
        <?php elseif ($_GET['msg'] == 'updated'): ?>
            <div class="alert alert-success shadow-sm border-0 d-flex align-items-center mb-4"><i class="bi bi-check-circle-fill me-2 fs-5"></i> Asset details updated successfully.</div>
        <?php elseif ($_GET['msg'] == 'error'): ?>
            <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4"><i class="bi bi-x-circle-fill me-2 fs-5"></i> <strong>Error:</strong> <?= htmlspecialchars($_GET['err_detail'] ?? 'Unknown database error occurred.') ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- FILTER FORM -->
    <div class="card mb-4 shadow-sm border-0 border-top border-primary border-4 bg-light">
        <div class="card-body">
            <form method="GET" class="row g-3" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label fw-bold text-muted small text-uppercase">Search</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Name / Serial / User..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-bold text-muted small text-uppercase">Category</label>
                    <select name="category" id="filter_category" class="form-select shadow-sm">
                        <option value="">All Categories</option>
                        <?php
                        // Fetch Main Categories
                        $main_cats = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id = 0 OR parent_id IS NULL ORDER BY category_name ASC");
                        while ($mc = mysqli_fetch_assoc($main_cats)) {
                            $selected = ($category == $mc['category_id']) ? 'selected' : '';
                            echo "<option value='{$mc['category_id']}' $selected class='fw-bold'>" . htmlspecialchars($mc['category_name']) . "</option>";
                            
                            // Fetch Sub-Categories for this Main Category
                            $sub_cats = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id = '{$mc['category_id']}' ORDER BY category_name ASC");
                            while ($sc = mysqli_fetch_assoc($sub_cats)) {
                                $sub_selected = ($category == $sc['category_id']) ? 'selected' : '';
                                echo "<option value='{$sc['category_id']}' $sub_selected>&nbsp;&nbsp;&nbsp;↳ " . htmlspecialchars($sc['category_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-bold text-muted small text-uppercase">Model</label>
                    <select name="model" id="filter_model" class="form-select shadow-sm">
                        <option value="">All Models</option>
                        <?php
                        // UPDATED: Now fetches parent_id so we can filter models by Main Category as well!
                        $mods = mysqli_query($conn, "
                            SELECT m.model_id, m.model_name, m.category_id, c.parent_id 
                            FROM asset_models m 
                            LEFT JOIN asset_categories c ON m.category_id = c.category_id 
                            ORDER BY m.model_name ASC
                        ");
                        while ($m = mysqli_fetch_assoc($mods)) {
                            $selected = ($model == $m['model_id']) ? 'selected' : '';
                            $cat_id = $m['category_id'];
                            $parent_id = !empty($m['parent_id']) ? $m['parent_id'] : $cat_id;
                            
                            echo "<option value='{$m['model_id']}' data-category='{$cat_id}' data-parent='{$parent_id}' $selected>" . htmlspecialchars($m['model_name']) . "</option>";
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
                
                <div class="col-md-1 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm fw-bold"><i class="bi bi-funnel"></i></button>
                    <!-- Quick clear button to easily reset filters -->
                    <a href="assets_list.php" class="btn btn-light border shadow-sm text-muted"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- DYNAMIC MODEL FILTERING SCRIPT (FIXED) -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const catSelect = document.getElementById("filter_category");
        const modelSelect = document.getElementById("filter_model");
        const currentModelId = "<?= htmlspecialchars($model) ?>"; 
        
        // 1. Extract all models into a clean, safe array on page load
        const allModels = [];
        Array.from(modelSelect.options).forEach(opt => {
            if (opt.value !== "") {
                allModels.push({
                    value: opt.value,
                    text: opt.text,
                    category: opt.getAttribute("data-category"),
                    parent: opt.getAttribute("data-parent")
                });
            }
        });

        // 2. Function to rebuild the dropdown based on selected category
        function filterModels() {
            const selectedCat = catSelect.value;
            
            // Clear current dropdown
            modelSelect.innerHTML = '<option value="">All Models</option>';
            
            let modelFound = false;

            allModels.forEach(m => {
                // Show model if: 
                // A) No category is selected 
                // B) Model is directly in this category 
                // C) Model is in a sub-category of this main category
                if (selectedCat === "" || m.category === selectedCat || m.parent === selectedCat) {
                    const opt = document.createElement("option");
                    opt.value = m.value;
                    opt.textContent = m.text;
                    opt.setAttribute("data-category", m.category);
                    opt.setAttribute("data-parent", m.parent);
                    
                    if (m.value === currentModelId) {
                        opt.selected = true;
                        modelFound = true;
                    }
                    modelSelect.appendChild(opt);
                }
            });

            // If the user changed the category and the previously selected model doesn't belong to it, reset the model dropdown
            if (!modelFound && selectedCat !== "") {
                modelSelect.value = "";
            }
        }

        // 3. Listen for changes and run once immediately
        catSelect.addEventListener("change", filterModels);
        filterModels(); 
    });
    </script>

    <!-- TABLE AND MODAL SECTION -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <!-- FORM WRAPPER FOR CHECKBOXES -->
            <form id="bulkLabelForm" action="generate_labels.php" method="POST" target="_blank">

                <!-- HIDDEN INPUTS FOR ALL-PAGES LOGIC -->
                <input type="hidden" name="select_all_pages" id="selectAllPagesInput" value="0">
                <input type="hidden" name="filter_search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="filter_category" value="<?= htmlspecialchars($category) ?>">
                <input type="hidden" name="filter_model" value="<?= htmlspecialchars($model) ?>">
                <input type="hidden" name="filter_status" value="<?= htmlspecialchars($status) ?>">
                <input type="hidden" name="filter_location" value="<?= htmlspecialchars($location) ?>">

                <!-- LABEL OPTIONS MODAL (Centered nicely at the top) -->
                <div class="modal" id="printLabelsModal" tabindex="-1" style="display:none; background: rgba(0,0,0,0.5); z-index: 1050; position: fixed; top: 0; left: 0; width: 100%; height: 100%; overflow: auto;">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px; margin: 40px auto 0 auto;">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header bg-dark text-white border-0">
                                <h5 class="modal-title"><i class="bi bi-printer me-2"></i> Label Settings</h5>
                                <button type="button" class="btn-close btn-close-white" onclick="closeLabelModal()"></button>
                            </div>
                            <div class="modal-body bg-light">
                                <h6 class="fw-bold text-muted text-uppercase mb-3"><i class="bi bi-upc-scan me-1"></i> 1. Select Code Type</h6>
                                <div class="d-flex gap-4 mb-4 bg-white p-3 rounded border">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="code_type" id="codeBoth" value="both" checked>
                                        <label class="form-check-label fw-semibold" for="codeBoth">Both</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="code_type" id="codeQR" value="qr">
                                        <label class="form-check-label fw-semibold" for="codeQR">QR Only</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="code_type" id="codeBarcode" value="barcode">
                                        <label class="form-check-label fw-semibold" for="codeBarcode">Barcode Only</label>
                                    </div>
                                </div>

                                <h6 class="fw-bold text-muted text-uppercase mb-3"><i class="bi bi-list-check me-1"></i> 2. Details to Print</h6>
                                <div class="row bg-white p-3 rounded border mx-0">
                                    <div class="col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="label_fields[]" value="asset_name" id="f_name" checked>
                                            <label class="form-check-label" for="f_name">Asset Name</label>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="label_fields[]" value="serial_number" id="f_sn" checked>
                                            <label class="form-check-label" for="f_sn">Serial No.</label>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="label_fields[]" value="category" id="f_cat" checked>
                                            <label class="form-check-label" for="f_cat">Category</label>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="label_fields[]" value="model" id="f_model">
                                            <label class="form-check-label" for="f_model">Model</label>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-0">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="label_fields[]" value="location" id="f_loc">
                                            <label class="form-check-label" for="f_loc">Location</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0 bg-white">
                                <button type="button" class="btn btn-light border px-4" onclick="closeLabelModal()">Cancel</button>
                                <button type="button" class="btn btn-success fw-bold px-4" onclick="submitLabelForm()"><i class="bi bi-check-circle me-1"></i> Generate Labels</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="assetsTable">
                        <thead class="table-light text-secondary">
                            <tr>
                                <th class="ps-3 border-bottom-0" style="width: 40px;">
                                    <input class="form-check-input shadow-sm" type="checkbox" id="selectAll">
                                </th>
                                <th class="ps-2 border-bottom-0">#</th>
                                <th class="text-center border-bottom-0" style="width: 60px;">Image</th>
                                <th class="border-bottom-0">Asset Details</th>
                                <th class="border-bottom-0">Classification</th>
                                <th class="border-bottom-0">Status & Location</th>
                                <th class="border-bottom-0">Assignment & Warranty</th>
                                <th class="text-center border-bottom-0">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- MULTI-PAGE SELECT ALL BANNER -->
                            <tr id="selectAllBanner" style="display: none; background-color: #e3f2fd;">
                                <td colspan="8" class="text-center py-3 border-bottom-0">
                                    <span id="selectAllText" class="text-dark">All <?= min($limit, $totalRows) ?> assets on this page are selected.</span>
                                    <a href="javascript:void(0);" id="selectAllPagesBtn" class="fw-bold text-primary text-decoration-none ms-2">Select all <?= $totalRows ?> assets matching your filter.</a>
                                </td>
                            </tr>

                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php $sr = $offset + 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <input class="form-check-input shadow-sm asset-checkbox" type="checkbox" name="asset_ids[]" value="<?= $row['asset_id'] ?>">
                                        </td>
                                        <td class="ps-2 text-muted fw-bold"><?= $sr++ ?></td>
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
                                                <button class="btn btn-sm btn-light border py-0 px-1 text-muted" type="button" onclick="copyToClipboard('<?= htmlspecialchars($row['serial_number']) ?>')" title="Copy Serial Number">
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
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="asset_details.php?id=<?= $row['asset_id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm" title="View Details">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>
                                                <a href="assets_edit.php?id=<?= $row['asset_id'] ?>" class="btn btn-sm btn-outline-warning shadow-sm text-dark" title="Edit Asset">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </a>
                                                <?php if (!empty($row['assigned_user_name'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm" title="Cannot delete: Asset is assigned" style="cursor: not-allowed;" disabled>
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="asset_delete.php?id=<?= $row['asset_id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm" title="Delete Asset" onclick="return confirm('Are you sure you want to delete this asset?')">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-inboxes fs-1 d-block mb-3 opacity-50"></i>
                                        <h5>No assets found.</h5>
                                        <p class="mb-0">Try adjusting your filters or add a new asset.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <!-- PAGINATION -->
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
</div>

<script>
    // Native JS for Modals
    function openLabelModal() {
        document.getElementById('printLabelsModal').style.display = 'block';
    }

    function closeLabelModal() {
        document.getElementById('printLabelsModal').style.display = 'none';
    }

    function submitLabelForm() {
        document.getElementById('bulkLabelForm').submit();
        closeLabelModal();
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => alert('Copied: ' + text));
        } else {
            let t = document.createElement("textarea");
            t.value = text;
            t.style.position = "fixed";
            t.style.left = "-9999px";
            document.body.appendChild(t);
            t.focus();
            t.select();
            document.execCommand('copy');
            t.remove();
            alert('Copied: ' + text);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        // 1. Export Dropdown Handling
        const exportBtn = document.getElementById("btnExportDropdown");
        const exportMenu = document.getElementById("exportDropdownMenu");

        if (exportBtn && exportMenu) {
            exportBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                exportMenu.style.display = (exportMenu.style.display === "block") ? "none" : "block";
            });

            document.addEventListener("click", function(e) {
                if (!exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
                    exportMenu.style.display = "none";
                }
            });
        }

        // 2. Select All and Print Labels Button Logic
        const selectAll = document.getElementById("selectAll");
        const checkboxes = document.querySelectorAll(".asset-checkbox");
        const btnOpenModal = document.getElementById("btnOpenLabelModal");
        const selectAllBanner = document.getElementById("selectAllBanner");
        const selectAllText = document.getElementById("selectAllText");
        const selectAllPagesBtn = document.getElementById("selectAllPagesBtn");
        const selectAllPagesInput = document.getElementById("selectAllPagesInput");

        let totalRows = <?= $totalRows ?>;
        let limit = <?= $limit ?>;
        let allPagesSelected = false;

        btnOpenModal.addEventListener("click", function(e) {
            const checkedCount = document.querySelectorAll(".asset-checkbox:checked").length;
            if (checkedCount === 0 && !allPagesSelected) {
                e.preventDefault();
                alert("Please check the box next to at least one asset to print labels!");
            } else {
                openLabelModal();
            }
        });

        if (selectAll) {
            selectAll.addEventListener("change", function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
                if (this.checked && totalRows > limit) {
                    selectAllBanner.style.display = "table-row";
                    allPagesSelected = false;
                    selectAllPagesInput.value = "0";
                    selectAllText.innerHTML = `All ${checkboxes.length} assets on this page are selected.`;
                    selectAllPagesBtn.innerHTML = `Select all ${totalRows} assets matching filters.`;
                    selectAllPagesBtn.classList.replace("text-danger", "text-primary");
                } else {
                    if (selectAllBanner) selectAllBanner.style.display = "none";
                    allPagesSelected = false;
                    selectAllPagesInput.value = "0";
                }
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener("change", function() {
                if (!this.checked) {
                    selectAll.checked = false;
                    if (selectAllBanner) selectAllBanner.style.display = "none";
                    allPagesSelected = false;
                    selectAllPagesInput.value = "0";
                }
                if (document.querySelectorAll(".asset-checkbox:checked").length === checkboxes.length && checkboxes.length > 0) {
                    selectAll.checked = true;
                    if (totalRows > limit) {
                        selectAllBanner.style.display = "table-row";
                        selectAllText.innerHTML = `All ${checkboxes.length} assets on this page are selected.`;
                        selectAllPagesBtn.innerHTML = `Select all ${totalRows} assets matching filters.`;
                        selectAllPagesBtn.classList.replace("text-danger", "text-primary");
                    }
                }
            });
        });

        if (selectAllPagesBtn) {
            selectAllPagesBtn.addEventListener("click", function() {
                if (!allPagesSelected) {
                    allPagesSelected = true;
                    selectAllPagesInput.value = "1";
                    selectAllText.innerHTML = `<strong>All ${totalRows} assets are selected.</strong>`;
                    this.innerHTML = "Clear selection";
                    this.classList.replace("text-primary", "text-danger");
                } else {
                    selectAll.checked = false;
                    checkboxes.forEach(cb => cb.checked = false);
                    selectAllBanner.style.display = "none";
                    allPagesSelected = false;
                    selectAllPagesInput.value = "0";
                    this.classList.replace("text-danger", "text-primary");
                }
            });
        }
    });
</script>

<?php
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php");
?>