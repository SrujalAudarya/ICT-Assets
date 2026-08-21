<?php
ob_start(); // CRITICAL: Protects the Excel/CSV Export Headers from failing
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '0');

/* ---------- LOCATION BASIC INFO ---------- */
$loc_query = "SELECT * FROM locations WHERE location_id = '$id'";
$location = mysqli_fetch_assoc(mysqli_query($conn, $loc_query));

if (!$location) {
    while (ob_get_level() > 0) ob_end_clean();
    include("../../includes/header.php");
    include("../../includes/sidebar.php");
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm border-0'><i class='bi bi-exclamation-triangle-fill me-2'></i> Location not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* ---------- FILTER HANDLING ---------- */
$category = $_GET['category'] ?? '';
$model = $_GET['model'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE a.location_id = '$id'";

if($category != ""){
    $category_escaped = mysqli_real_escape_string($conn, $category);
    $where .= " AND a.category_id = '$category_escaped'";
}

if($model != ""){
    $model_escaped = mysqli_real_escape_string($conn, $model);
    $where .= " AND a.model_id = '$model_escaped'";
}

if($status != ""){
    $status_escaped = mysqli_real_escape_string($conn, $status);
    $where .= " AND a.status_id = '$status_escaped'";
}

/* ---------- MASTER SQL QUERY ---------- */
$assets_query = "
SELECT 
    a.*, 
    c.category_name, 
    s.status_name, 
    m.model_name, 
    m.model_image,
    u.name AS user_name,
    l.dept_name,
    l.floor
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
LEFT JOIN locations l ON a.location_id = l.location_id
/* ONLY CURRENT ASSIGNMENT */
LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id AND asn.returned_date IS NULL
LEFT JOIN users u ON asn.user_id = u.user_id
$where
ORDER BY a.asset_id DESC
";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV) - RUNS BEFORE HTML
   ========================================================= */
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    
    // Aggressively clear output buffer to prevent corrupted files
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $export_res = mysqli_query($conn, $assets_query);
    $clean_loc_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $location['dept_name']);
    $filename = "Location_Assets_" . $clean_loc_name . "_" . date('Y-m-d');
    $isExcel = ($_GET['export'] === 'excel');

    if ($isExcel) {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        $delimiter = "\t";
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $delimiter = ",";
    }

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Sr No', 'Asset Name', 'Serial No', 'Category', 'Model', 'Status', 'Assigned To', 'Purchase Date', 'Cost'], $delimiter);

    $sr = 1;
    while ($r = mysqli_fetch_assoc($export_res)) {
        $pDate = !empty($r['purchase_date']) ? date('d-m-Y', strtotime($r['purchase_date'])) : '-';
        fputcsv($output, [
            $sr++,
            $r['asset_name'],
            $r['serial_number'],
            $r['category_name'] ?? 'N/A',
            $r['model_name'] ?? 'N/A',
            $r['status_name'] ?? 'N/A',
            $r['user_name'] ?? 'Not Assigned',
            $pDate,
            $r['cost'] ?? '0'
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

// Count Total for Pagination
$countQuery = "
    SELECT COUNT(DISTINCT a.asset_id) AS total
    FROM assets a
    LEFT JOIN asset_categories c ON a.category_id = c.category_id
    LEFT JOIN asset_status s ON a.status_id = s.status_id
    LEFT JOIN asset_models m ON a.model_id = m.model_id
    LEFT JOIN locations l ON a.location_id = l.location_id
    LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id AND asn.returned_date IS NULL
    LEFT JOIN users u ON asn.user_id = u.user_id
    $where
";
$totalRows = mysqli_fetch_assoc(mysqli_query($conn, $countQuery))['total'] ?? 0;
$totalPages = ceil($totalRows / $limit);

$assets_result = mysqli_query($conn, $assets_query . " LIMIT $limit OFFSET $offset");
$filtered_count = mysqli_num_rows($assets_result);

$exportParams = $_GET;
$exportParams['export'] = 'excel';
$exportExcelUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'csv';
$exportCsvUrl = '?' . http_build_query($exportParams);

// Total absolute count for the location profile block
$total_assets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE location_id = '$id'"))['total'];
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="mb-0 text-dark"><i class="bi bi-geo-alt-fill me-2 text-danger"></i> <?= htmlspecialchars($location['dept_name']) ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="locations_list.php" class="text-decoration-none">Locations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Profile</li>
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
            
            <a href="locations_edit.php?id=<?= $id ?>" class="btn btn-warning fw-bold text-dark"><i class="bi bi-pencil-square me-1"></i> Edit Location</a>
            <a href="locations_list.php" class="btn btn-secondary fw-bold"><i class="bi bi-arrow-left me-1"></i> Back</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: LOCATION INFO -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm mb-4 border-0 border-top border-danger border-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-danger fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Department Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><th width="40%" class="text-muted">ID:</th><td class="fw-bold fs-5 text-dark">#<?= $location['location_id'] ?></td></tr>
                        <tr><th class="text-muted">Department:</th><td class="fw-bold text-dark"><?= htmlspecialchars($location['dept_name'] ?: 'N/A') ?></td></tr>
                        <tr><th class="text-muted">Floor:</th><td><?= htmlspecialchars($location['floor'] ?: 'N/A') ?></td></tr>
                        <tr><th class="text-muted">Created At:</th><td><?= date('d M Y', strtotime($location['created_at'])) ?></td></tr>
                        
                        <tr><th class="text-muted pb-0 pt-3" colspan="2">Remarks / Notes:</th></tr>
                        <tr>
                            <td colspan="2" class="pt-1">
                                <div class="bg-light p-2 border rounded small">
                                    <?= nl2br(htmlspecialchars($location['remarks'] ?: 'No additional remarks.')) ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-0 border-top border-info border-4 bg-info bg-opacity-10">
                <div class="card-body text-center py-4">
                    <h6 class="text-muted fw-bold mb-2 text-uppercase">Total Assets at this Location</h6>
                    <h1 class="display-3 fw-bolder text-info mb-0"><?= $total_assets ?></h1>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: ASSETS LIST & FILTERS -->
        <div class="col-xl-8 col-lg-7">
            <!-- FILTER FORM -->
            <div class="card mb-4 shadow-sm border-0 bg-light">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Category</label>
                            <select name="category" class="form-select form-select-sm shadow-sm">
                                <option value="">All Categories</option>
                                <?php
                                $cats_query = "SELECT DISTINCT c.category_id, c.category_name 
                                               FROM asset_categories c
                                               JOIN assets a ON c.category_id = a.category_id
                                               WHERE a.location_id = '$id'
                                               ORDER BY c.category_name ASC";
                                $cats = mysqli_query($conn, $cats_query);
                                while($c = mysqli_fetch_assoc($cats)){
                                    $selected = ($category == $c['category_id']) ? "selected" : "";
                                    echo "<option value='{$c['category_id']}' $selected>{$c['category_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Model</label>
                            <select name="model" class="form-select form-select-sm shadow-sm">
                                <option value="">All Models</option>
                                <?php
                                $mods_query = "SELECT DISTINCT m.model_id, m.model_name 
                                               FROM asset_models m
                                               JOIN assets a ON m.model_id = a.model_id
                                               WHERE a.location_id = '$id'
                                               ORDER BY m.model_name ASC";
                                $mods = mysqli_query($conn, $mods_query);
                                while($m = mysqli_fetch_assoc($mods)){
                                    $selected = ($model == $m['model_id']) ? "selected" : "";
                                    echo "<option value='{$m['model_id']}' $selected>{$m['model_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                            <select name="status" class="form-select form-select-sm shadow-sm">
                                <option value="">All Status</option>
                                <?php
                                $sts_query = "SELECT DISTINCT s.status_id, s.status_name 
                                              FROM asset_status s
                                              JOIN assets a ON s.status_id = a.status_id
                                              WHERE a.location_id = '$id'
                                              ORDER BY s.status_name ASC";
                                $sts = mysqli_query($conn, $sts_query);
                                while($s = mysqli_fetch_assoc($sts)){
                                    $selected = ($status == $s['status_id']) ? "selected" : "";
                                    echo "<option value='{$s['status_id']}' $selected>{$s['status_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm fw-bold"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="locations_details.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm px-4 shadow-sm text-dark">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABLE AND MODAL SECTION -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">Assets Currently Here</h5>
                    <span class="badge bg-dark rounded-pill px-3"><?= $filtered_count ?> Results</span>
                </div>
                <div class="card-body p-0">
                    
                    <!-- FORM WRAPPER FOR LABEL GENERATION -->
                    <form id="bulkLabelForm" action="../assets/generate_labels.php" method="POST" target="_blank">
                        <input type="hidden" name="select_all_pages" id="selectAllPagesInput" value="0">
                        <input type="hidden" name="filter_location" value="<?= $id ?>">
                        <input type="hidden" name="filter_category" value="<?= htmlspecialchars($category) ?>">
                        <input type="hidden" name="filter_model" value="<?= htmlspecialchars($model) ?>">
                        <input type="hidden" name="filter_status" value="<?= htmlspecialchars($status) ?>">

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
                                            <input class="form-check-input" type="checkbox" name="label_fields[]" value="location" id="f_loc" checked>
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
                                        <th class="border-bottom-0">Classification</th>
                                        <th class="border-bottom-0">Status</th>
                                        <th class="border-bottom-0">Assignment</th>
                                        <th class="text-center border-bottom-0 no-export">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- MULTI-PAGE SELECT ALL BANNER -->
                                    <tr id="selectAllBanner" class="no-export" style="display: none; background-color: #e3f2fd;">
                                        <td colspan="8" class="text-center py-3 border-bottom-0">
                                            <span id="selectAllText" class="text-dark">All <?= min($limit, $totalRows) ?> assets on this page are selected.</span>
                                            <a href="javascript:void(0);" id="selectAllPagesBtn" class="fw-bold text-primary text-decoration-none ms-2">Select all <?= $totalRows ?> matching assets.</a>
                                        </td>
                                    </tr>

                                    <?php if ($filtered_count > 0): ?>
                                        <?php $sr = $offset + 1; ?>
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
                                                </td>
                                                
                                                <td>
                                                    <?php if (!empty($row['user_name'])): ?>
                                                        <div class="fw-bold text-dark"><i class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($row['user_name']) ?></div>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Not Assigned</span>
                                                    <?php endif; ?>
                                                    <div class="small text-muted mt-1 d-flex align-items-center">
                                                        <span title="Purchase Date"><i class="bi bi-calendar3 me-1"></i><?= !empty($row['purchase_date']) ? date('d M Y', strtotime($row['purchase_date'])) : '-' ?></span>
                                                    </div>
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
                                            <a class="page-link text-dark" href="?page=<?= $page - 1 ?>&id=<?= $id ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&model=<?= urlencode($model) ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                                <a class="page-link <?= ($page == $i) ? 'bg-primary border-primary' : 'text-dark' ?>"
                                                   href="?page=<?= $i ?>&id=<?= $id ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&model=<?= urlencode($model) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link text-dark" href="?page=<?= $page + 1 ?>&id=<?= $id ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&model=<?= urlencode($model) ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
        doc.text("Assets in Location: <?= addslashes($location['dept_name']) ?>", 14, 15);

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

        const safeFilename = "<?= addslashes($location['dept_name']) ?>".replace(/[^a-zA-Z0-9_-]/g, "_");
        doc.save("Location_Assets_" + safeFilename + "_<?= date('Y-m-d') ?>.pdf");
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