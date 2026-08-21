<?php
// 1. Start output buffering immediately
ob_start();

global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '0';

/* =========================================================
   FETCH CATEGORY DETAILS & PARENT RELATIONSHIP
   ========================================================= */
$cat_query = "SELECT c.*, p.category_name AS parent_name 
              FROM asset_categories c 
              LEFT JOIN asset_categories p ON c.parent_id = p.category_id 
              WHERE c.category_id = '$id'";
$cat_res = mysqli_query($conn, $cat_query);
$category = mysqli_fetch_assoc($cat_res);

if (!$category) {
    while (ob_get_level()) ob_end_clean(); // Clear buffer safely
    include("../../includes/header.php");
    include("../../includes/sidebar.php");
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm'><i class='bi bi-exclamation-triangle-fill me-2'></i> Category not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

$is_main_category = empty($category['parent_id']) || $category['parent_id'] == 0;

/* =========================================================
   FETCH SUB-CATEGORIES (IF MAIN CATEGORY)
   ========================================================= */
$sub_cats = [];
if ($is_main_category) {
    $sub_res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id = '$id' ORDER BY category_name ASC");
    while($s = mysqli_fetch_assoc($sub_res)) {
        $sub_cats[] = $s;
    }
}

/* =========================================================
   FILTER HANDLING
   ========================================================= */
$model    = $_GET['model'] ?? '';
$status   = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';

// Determine base category condition
if ($is_main_category) {
    $sub_ids = [$id];
    foreach($sub_cats as $sc) { 
        $sub_ids[] = $sc['category_id']; 
    }
    $ids_str = implode(',', $sub_ids);
    $category_condition = "a.category_id IN ($ids_str)";
} else {
    $category_condition = "a.category_id = '$id'";
}

$where_clauses = [$category_condition];

if (!empty($model)) {
    $where_clauses[] = "a.model_id = '" . mysqli_real_escape_string($conn, $model) . "'";
}
if (!empty($status)) {
    $where_clauses[] = "a.status_id = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if (!empty($location)) {
    $where_clauses[] = "a.location_id = '" . mysqli_real_escape_string($conn, $location) . "'";
}

$where_sql = implode(' AND ', $where_clauses);

/* =========================================================
   MASTER SQL QUERY (Used for HTML View and Exports)
   ========================================================= */
$assets_query = "SELECT a.*, 
                        s.status_name, 
                        l.dept_name, l.floor, 
                        m.model_name, m.model_image,
                        u.name AS assigned_user_name
                 FROM assets a 
                 LEFT JOIN asset_status s ON a.status_id = s.status_id 
                 LEFT JOIN locations l ON a.location_id = l.location_id 
                 LEFT JOIN asset_models m ON a.model_id = m.model_id 
                 LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id AND asn.returned_date IS NULL
                 LEFT JOIN users u ON asn.user_id = u.user_id
                 WHERE $where_sql ORDER BY a.asset_id DESC";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV) - EXECUTED BEFORE HTML
   ========================================================= */
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    
    // 1. Ruthlessly wipe ANY hidden spaces, HTML, or warnings
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $export_res = mysqli_query($conn, $assets_query);
    if (!$export_res) {
        die("Export Error - SQL Failed: " . mysqli_error($conn));
    }
    
    $clean_cat_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $category['category_name']);
    $filename = "Category_Assets_" . $clean_cat_name . "_" . date('Y-m-d');

    if ($_GET['export'] == 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo '<table border="1">';
        echo '<tr><th colspan="7" style="font-size:14pt; text-align:left;">Assets in Category: ' . htmlspecialchars($category['category_name']) . '</th></tr>';
        echo '<tr><th>Sr No</th><th>Asset Name</th><th>Serial No</th><th>Model</th><th>Status</th><th>Location</th><th>Assigned To</th></tr>';
        
        $sr = 1;
        while ($r = mysqli_fetch_assoc($export_res)) {
            echo "<tr>
                    <td>{$sr}</td>
                    <td>{$r['asset_name']}</td>
                    <td>{$r['serial_number']}</td>
                    <td>" . ($r['model_name'] ?? 'N/A') . "</td>
                    <td>" . ($r['status_name'] ?? 'N/A') . "</td>
                    <td>" . ($r['dept_name'] ?? 'N/A') . "</td>
                    <td>" . ($r['assigned_user_name'] ?? 'Not Assigned') . "</td>
                  </tr>";
            $sr++;
        }
        echo '</table>';
        exit();
    }
    
    if ($_GET['export'] == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Sr No', 'Asset Name', 'Serial No', 'Model', 'Status', 'Location', 'Assigned To']);
        
        $sr = 1;
        while ($r = mysqli_fetch_assoc($export_res)) {
            fputcsv($output, [
                $sr++,
                $r['asset_name'],
                $r['serial_number'],
                $r['model_name'] ?? 'N/A',
                $r['status_name'] ?? 'N/A',
                $r['dept_name'] ?? 'N/A',
                $r['assigned_user_name'] ?? 'Not Assigned'
            ]);
        }
        fclose($output);
        exit();
    }
}

// Flush normal HTML if not exporting
if (ob_get_length()) {
    ob_end_flush();
}

$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);

$total_assets_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets a WHERE $category_condition");
$total_assets = mysqli_fetch_assoc($total_assets_query)['total'];

// Generate clean Export URLs keeping filters intact
$exportParams = $_GET;
$exportParams['export'] = 'excel';
$exportExcelUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'csv';
$exportCsvUrl = '?' . http_build_query($exportParams);

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4 mb-5">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-0 text-dark"><i class="bi bi-folder-fill text-primary me-2"></i> Category Profile</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="categories_list.php" class="text-decoration-none">Categories</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($category['category_name']) ?></li>
                </ol>
            </nav>
        </div>
        
        <div class="d-flex gap-2 flex-wrap">
            
            <!-- PRINT LABELS BUTTON -->
            <button type="button" class="btn btn-dark fw-bold shadow-sm" id="btnOpenLabelModal">
                <i class="bi bi-qr-code-scan me-1"></i> Print Labels
            </button>

            <!-- EXPORT DROPDOWN WITH FIXED STYLING & NATIVE JS -->
            <div class="dropdown position-relative d-inline-block">
                <button class="btn btn-light bg-white border border-secondary text-dark dropdown-toggle fw-bold shadow-sm" type="button" id="btnExportDropdown">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu shadow" id="exportDropdownMenu" style="display: none; position: absolute; top: 100%; left: 0; z-index: 1000;">
                    <li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger me-2"></i> Export as PDF</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportExcelUrl ?>"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export as Excel</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportCsvUrl ?>"><i class="bi bi-file-earmark-text text-primary me-2"></i> Export as CSV</a></li>
                </ul>
            </div>

            <a href="categories_edit.php?id=<?= $category['category_id'] ?>" class="btn btn-warning fw-bold text-dark"><i class="bi bi-pencil-square me-1"></i> Edit Category</a>
            <a href="categories_list.php" class="btn btn-secondary fw-bold"><i class="bi bi-arrow-left me-1"></i> Back</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: CATEGORY INFO -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm mb-4 border-0 border-top border-primary border-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Category Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr>
                                <th width="40%" class="text-muted">Name:</th>
                                <td class="fw-bold fs-5 text-dark"><?= htmlspecialchars($category['category_name']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Type:</th>
                                <td>
                                    <?php if ($is_main_category): ?>
                                        <span class="badge bg-primary">Main Category</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sub Category</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!$is_main_category): ?>
                            <tr>
                                <th class="text-muted">Parent Category:</th>
                                <td><a href="categories_details.php?id=<?= $category['parent_id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($category['parent_name']) ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th class="text-muted pb-0 pt-3" colspan="2">Description:</th>
                            </tr>
                            <tr>
                                <td colspan="2" class="pt-1">
                                    <div class="bg-light p-2 border rounded small">
                                        <?= nl2br(htmlspecialchars($category['description'] ?: 'No description provided.')) ?>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-0 border-top border-info border-4 bg-info bg-opacity-10">
                <div class="card-body text-center py-4">
                    <h6 class="text-muted fw-bold mb-2 text-uppercase">Total Assets in Category</h6>
                    <h1 class="display-3 fw-bolder text-info mb-0"><?= $total_assets ?></h1>
                </div>
            </div>

            <?php if ($is_main_category): ?>
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i> Sub-Categories</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (count($sub_cats) > 0): ?>
                            <?php foreach($sub_cats as $sc): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                    <a href="categories_details.php?id=<?= $sc['category_id'] ?>" class="text-decoration-none fw-bold text-dark">
                                        <i class="bi bi-arrow-return-right text-muted me-2"></i> <?= htmlspecialchars($sc['category_name']) ?>
                                    </a>
                                    <a href="categories_details.php?id=<?= $sc['category_id'] ?>" class="btn btn-sm btn-outline-primary fw-bold shadow-sm">View</a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center text-muted py-4 bg-light">No sub-categories created yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: SEARCH, FILTER & ASSETS LIST -->
        <div class="col-xl-8 col-lg-7">
            <!-- FILTER FORM -->
            <div class="card mb-4 shadow-sm border-0 bg-light">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Model</label>
                            <select name="model" class="form-select form-select-sm shadow-sm">
                                <option value="">All Models</option>
                                <?php
                                $mods = mysqli_query($conn, "SELECT DISTINCT m.model_id, m.model_name 
                                                             FROM asset_models m 
                                                             JOIN assets a ON m.model_id = a.model_id 
                                                             WHERE $category_condition ORDER BY m.model_name ASC");
                                while ($m = mysqli_fetch_assoc($mods)) {
                                    $sel = ($model == $m['model_id']) ? "selected" : "";
                                    echo "<option value='{$m['model_id']}' $sel>{$m['model_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                            <select name="status" class="form-select form-select-sm shadow-sm">
                                <option value="">All Status</option>
                                <?php
                                $sts_query = "SELECT DISTINCT s.status_id, s.status_name 
                                              FROM asset_status s
                                              JOIN assets a ON s.status_id = a.status_id
                                              WHERE $category_condition
                                              ORDER BY s.status_name ASC";
                                $sts_res = mysqli_query($conn, $sts_query);
                                while($s = mysqli_fetch_assoc($sts_res)){
                                    $selected = ($status == $s['status_id']) ? "selected" : "";
                                    echo "<option value='{$s['status_id']}' $selected>{$s['status_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Location</label>
                            <select name="location" class="form-select form-select-sm shadow-sm">
                                <option value="">All Locations</option>
                                <?php
                                $loc_query = "SELECT DISTINCT l.location_id, l.dept_name 
                                              FROM locations l
                                              JOIN assets a ON l.location_id = a.location_id
                                              WHERE $category_condition
                                              ORDER BY l.dept_name ASC";
                                $loc_res = mysqli_query($conn, $loc_query);
                                while($l = mysqli_fetch_assoc($loc_res)){
                                    $selected = ($location == $l['location_id']) ? "selected" : "";
                                    echo "<option value='{$l['location_id']}' $selected>{$l['dept_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm fw-bold"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="categories_details.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm w-100 shadow-sm text-dark">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABLE AND MODAL SECTION -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">Assets in this Category</h5>
                    <span class="badge bg-dark rounded-pill px-3"><?= $filtered_count ?> Results</span>
                </div>
                <div class="card-body p-0">
                    
                    <!-- FORM WRAPPER FOR LABEL GENERATION -->
                    <!-- Points correctly to assets folder -->
                    <form id="bulkLabelForm" action="../assets/generate_labels.php" method="POST" target="_blank">

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
                                        <th class="ps-3 border-bottom-0 no-export" style="width: 40px;">
                                            <input class="form-check-input shadow-sm" type="checkbox" id="selectAll">
                                        </th>
                                        <th class="ps-2 border-bottom-0">#</th>
                                        <th class="text-center border-bottom-0" style="width: 60px;">Image</th>
                                        <th class="border-bottom-0">Asset Details</th>
                                        <th class="border-bottom-0">Model</th>
                                        <th class="border-bottom-0">Status & Location</th>
                                        <th class="border-bottom-0">Assignment</th>
                                        <th class="text-center border-bottom-0 no-export">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($filtered_count > 0): ?>
                                        <?php $sr = 1; ?>
                                        <?php while ($row = mysqli_fetch_assoc($assets_result)): ?>
                                            <tr>
                                                <td class="ps-3 no-export">
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
                                                    <a href="../assets/asset_details.php?id=<?= $row['asset_id'] ?>" class="text-decoration-none fw-bold text-dark d-block">
                                                        <?= htmlspecialchars($row['asset_name']) ?>
                                                    </a>
                                                    <div class="d-flex align-items-center mt-1">
                                                        <code class="small text-primary bg-primary bg-opacity-10 px-2 py-1 rounded me-2"><?= htmlspecialchars($row['serial_number']) ?></code>
                                                        <button class="btn btn-sm btn-light border py-0 px-1 text-muted" type="button" onclick="copyToClipboard('<?= htmlspecialchars($row['serial_number']) ?>')" title="Copy Serial Number">
                                                            <i class="bi bi-clipboard"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <div class="text-dark fw-semibold text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($row['model_name'] ?? '') ?>">
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
                                                </td>
                                                
                                                <td class="text-center no-export">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <a href="../assets/asset_details.php?id=<?= $row['asset_id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm" title="View Asset"><i class="bi bi-eye-fill"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="bi bi-inboxes fs-1 d-block mb-3 opacity-50"></i>
                                                <h5>No assets found.</h5>
                                                <p class="mb-0">Try adjusting your filters.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     PDF EXPORT SCRIPT & MULTI-SELECT JS
     ========================================================= -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    // JS PDF LOGIC
    function exportToPDF() {
        if (typeof window.jspdf === 'undefined') {
            alert("Error: The PDF library failed to load. Please check your internet connection.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        doc.setFontSize(16);
        doc.text("Assets in Category: <?= addslashes($category['category_name']) ?>", 14, 15);

        // Temporarily hide the Action column and checkboxes
        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = 'none';
        });

        doc.autoTable({
            html: '#assetsTable',
            startY: 25,
            styles: {
                fontSize: 9,
                cellPadding: 3
            },
            headStyles: {
                fillColor: [52, 58, 64]
            }
        });

        // Restore the hidden elements
        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = '';
        });

        const safeFilename = "<?= addslashes($category['category_name']) ?>".replace(/[^a-zA-Z0-9_-]/g, "_");
        doc.save("Category_Assets_" + safeFilename + "_<?= date('Y-m-d') ?>.pdf");
    }

    // Modal and Checkbox Logic
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
            t.value = text; t.style.position = "fixed"; t.style.left = "-9999px";
            document.body.appendChild(t); t.focus(); t.select();
            document.execCommand('copy'); t.remove(); alert('Copied: ' + text);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Export Dropdown Fallback Logic
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

        // Print Labels and Checkbox Logic
        const selectAll = document.getElementById("selectAll");
        const checkboxes = document.querySelectorAll(".asset-checkbox");
        const btnOpenModal = document.getElementById("btnOpenLabelModal");

        btnOpenModal.addEventListener("click", function(e) {
            const checkedCount = document.querySelectorAll(".asset-checkbox:checked").length;
            if (checkedCount === 0) {
                e.preventDefault();
                alert("Please check the box next to at least one asset to print labels!");
            } else {
                openLabelModal();
            }
        });

        if (selectAll) {
            selectAll.addEventListener("change", function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener("change", function() {
                if (!this.checked) {
                    selectAll.checked = false;
                }
                if (document.querySelectorAll(".asset-checkbox:checked").length === checkboxes.length && checkboxes.length > 0) {
                    selectAll.checked = true;
                }
            });
        });
    });
</script>

<?php 
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>