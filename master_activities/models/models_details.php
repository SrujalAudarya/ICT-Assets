<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '0';

/* =========================================================
   HANDLE BULK STATE UPDATE (In Use / Not In Use)
========================================================= */
if (isset($_POST['bulk_action']) && !empty($_POST['selected_assets'])) {
    $action_state = mysqli_real_escape_string($conn, $_POST['bulk_action']);
    $asset_ids = array_map('intval', $_POST['selected_assets']);
    $ids_string = implode(',', $asset_ids);

    // If marked as 'Not In Use', automatically unassign from user and set status to Available
    if ($action_state === 'Not In Use') {
        // 1. Close active assignments
        mysqli_query($conn, "
            UPDATE asset_assignments 
            SET returned_date = CURDATE() 
            WHERE returned_date IS NULL 
              AND asset_id IN ($ids_string)
        ");

        // 2. Change operational status to 'Available' so it clears out of assigned tracking
        $avail_q = mysqli_query($conn, "SELECT status_id FROM asset_status WHERE status_name = 'Available' LIMIT 1");
        if ($avail_q && mysqli_num_rows($avail_q) > 0) {
            $avail_status_id = mysqli_fetch_assoc($avail_q)['status_id'];
            mysqli_query($conn, "UPDATE assets SET status_id = '$avail_status_id' WHERE asset_id IN ($ids_string)");
        }
    }

    // Updates ONLY the asset_state column, leaving general structure intact
    $update_state_q = "UPDATE assets SET asset_state = '$action_state' WHERE asset_id IN ($ids_string) AND model_id = '$id'";
    @mysqli_query($conn, $update_state_q);

    header("Location: models_details.php?id=" . $id . "&msg=status_updated");
    exit();
}

/* ---------- MODEL BASIC INFO ---------- */
$model_query = "
    SELECT m.*, 
           c.category_name, 
           c.parent_id,
           pc.category_name AS parent_category_name,
           v.vendor_name,
           s.status_name AS model_status_name
    FROM asset_models m
    LEFT JOIN asset_categories c ON m.category_id = c.category_id
    LEFT JOIN asset_categories pc ON c.parent_id = pc.category_id
    LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
    LEFT JOIN asset_status s ON m.status_id = s.status_id
    WHERE m.model_id = '$id'
";
$model = mysqli_fetch_assoc(mysqli_query($conn, $model_query));

if (!$model) {
    include("../../includes/header.php");
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm border-0'><i class='bi bi-exclamation-triangle-fill me-2'></i> Model not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

// Check if this specific model's state is Survey Off (Provisional or Final)
$model_status_check = $model['model_status_name'] ?? '';
$is_survey_off_model = (strpos($model_status_check, 'Survey Off') !== false);

/* ---------- FILTER HANDLING ---------- */
$status = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';

$where = "WHERE a.model_id = '$id'";

if($status != ""){
    $status_escaped = mysqli_real_escape_string($conn, $status);
    $where .= " AND a.status_id = '$status_escaped'";
}

if($location != ""){
    $location_escaped = mysqli_real_escape_string($conn, $location);
    $where .= " AND a.location_id = '$location_escaped'";
}

/* ---------- ASSETS FROM THIS MODEL ---------- */
$assets_query = "
SELECT 
    a.*, 
    u.name AS user_name, 
    c.category_name, 
    s.status_name AS asset_status_name, 
    ms.status_name AS model_status_name,
    l.dept_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
LEFT JOIN asset_status ms ON m.status_id = ms.status_id
LEFT JOIN locations l ON a.location_id = l.location_id
LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id AND asn.returned_date IS NULL
LEFT JOIN users u ON asn.user_id = u.user_id
$where
GROUP BY a.asset_id   
ORDER BY a.asset_id DESC
";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV)
   ========================================================= */
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $export_res = mysqli_query($conn, $assets_query);
    $clean_model_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $model['model_name']);
    $filename = "Model_Assets_" . $clean_model_name . "_" . date('Y-m-d');
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
    fputcsv($output, ['Sr No', 'Asset Name', 'Serial No', 'Category', 'Status', 'State', 'Location', 'Assigned To'], $delimiter);

    $sr = 1;
    while ($r = mysqli_fetch_assoc($export_res)) {
        fputcsv($output, [
            $sr++,
            $r['asset_name'],
            $r['serial_number'],
            $r['category_name'] ?? 'N/A',
            $r['asset_status_name'] ?? 'N/A',
            $r['asset_state'] ?? 'Working',
            $r['dept_name'] ?? 'N/A',
            $r['user_name'] ?? 'Not Assigned'
        ], $delimiter);
    }
    fclose($output);
    exit();
}

include("../../includes/header.php");
include("../../includes/sidebar.php");

$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);

$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE model_id = '$id'");
$total_assets = mysqli_fetch_assoc($total_query)['total'];

$exportParams = $_GET;
$exportParams['export'] = 'excel';
$exportExcelUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'csv';
$exportCsvUrl = '?' . http_build_query($exportParams);

$main_cat_param = !empty($model['parent_id']) ? $model['parent_id'] : $model['category_id'];
$sub_cat_param  = !empty($model['parent_id']) ? $model['category_id'] : '';
$add_asset_link = "../assets/assets_add.php?model_id={$id}&main_category_id={$main_cat_param}&sub_category_id={$sub_cat_param}";
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-dark"><i class="bi bi-box-seam me-2 text-primary"></i> <?= htmlspecialchars($model['model_name']) ?></h2>
            <div class="text-muted mt-1 small">Detailed Profile & Linked Assets</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= $add_asset_link ?>" class="btn btn-primary fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add Asset
            </a>

            <div class="dropdown position-relative d-inline-block">
                <button class="btn btn-light bg-white border border-secondary text-dark dropdown-toggle fw-bold shadow-sm" type="button" id="btnExportDropdown">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu shadow" id="exportDropdownMenu" style="display: none; position: absolute; top: 100%; left: 0; z-index: 1000;">
                    <li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger me-2"></i> Export as PDF</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportExcelUrl ?>"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export as Excel (.xls)</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportCsvUrl ?>"><i class="bi bi-file-earmark-text text-primary me-2"></i> Export as CSV</a></li>
                </ul>
            </div>
            
            <a href="<?= ROUTE_MODELS_EDIT ?>?id=<?= $id ?>" class="btn btn-warning fw-bold text-dark"><i class="bi bi-pencil-fill me-1"></i> Edit Model</a>
            <a href="<?= ROUTE_MODELS ?>" class="btn btn-secondary fw-bold"><i class="bi bi-arrow-left me-1"></i> Back</a>
        </div>
    </div>

    <!-- NOTIFICATION ALERT -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'status_updated'): ?>
        <div class="alert alert-success shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i> Selected assets state updated successfully.
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- LEFT COLUMN: MODEL INFO & IMAGE -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4 border-0 border-top border-primary border-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Model Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4 p-3 bg-light border rounded shadow-sm">
                        <?php if (!empty($model['model_image'])): ?>
                            <img src="../../<?= htmlspecialchars($model['model_image']) ?>" class="img-fluid rounded" style="max-height: 150px; object-fit: contain;" alt="Model Image">
                        <?php else: ?>
                            <div class="text-muted py-4">
                                <i class="bi bi-image fs-1 d-block mb-1"></i>
                                <small>No Image / Logo Uploaded</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <table class="table table-sm table-borderless">
                        <tr><th width="40%" class="text-muted">Model Name</th><td class="fw-bold"><?= htmlspecialchars($model['model_name']) ?></td></tr>
                        <tr>
                            <th class="text-muted align-middle">Model State</th>
                            <td>
                                <?php
                                $m_status = $model['model_status_name'] ?? 'Working';
                                $m_badge = 'bg-secondary';
                                $m_icon = '';

                                if ($m_status == 'Working') {
                                    $m_badge = 'bg-success bg-opacity-75 text-white border border-success';
                                    $m_icon = '<i class="bi bi-activity me-1"></i>';
                                } elseif ($m_status == 'Provisional Survey Off') {
                                    $m_badge = 'bg-info text-dark border border-info';
                                    $m_icon = '<i class="bi bi-clipboard-minus-fill me-1"></i>';
                                } elseif ($m_status == 'Final Survey Off') {
                                    $m_badge = 'bg-dark text-white';
                                    $m_icon = '<i class="bi bi-clipboard-x-fill me-1"></i>';
                                }
                                ?>
                                <span class="badge <?= $m_badge ?> rounded-pill px-3 py-2 shadow-sm">
                                    <?= $m_icon . htmlspecialchars($m_status) ?>
                                </span>
                            </td>
                        </tr>
                        <tr><th class="text-muted">Make</th><td><?= htmlspecialchars($model['make_name'] ?: 'N/A') ?></td></tr>
                        <tr>
                            <th class="text-muted">Category</th>
                            <td>
                                <?php 
                                    if (!empty($model['parent_category_name'])) {
                                        echo htmlspecialchars($model['parent_category_name']) . ' &raquo; <span class="fw-bold">' . htmlspecialchars($model['category_name']) . '</span>';
                                    } else {
                                        echo '<span class="fw-bold">' . htmlspecialchars($model['category_name'] ?: 'N/A') . '</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                        <tr><th class="text-muted">Vendor</th><td><?= htmlspecialchars($model['vendor_name'] ?: 'N/A') ?></td></tr>
                        <tr><th class="text-muted">Contract No</th><td><code><?= htmlspecialchars($model['contract_no'] ?: 'N/A') ?></code></td></tr>
                        <tr class="border-top"><th class="text-muted pt-2">Quantity</th><td class="pt-2"><span class="badge bg-secondary"><?= (int)($model['quantity'] ?? 0) ?> Units</span></td></tr>
                        <tr><th class="text-muted">Unit Cost</th><td class="text-success fw-bold">₹ <?= number_format((float)($model['cost'] ?? 0), 2) ?></td></tr>
                        <tr><th class="text-muted">Total Value</th><td class="text-primary fw-bold">₹ <?= number_format(((int)($model['quantity'] ?? 0) * (float)($model['cost'] ?? 0)), 2) ?></td></tr>
                        <tr><th class="text-muted">F.Y.</th><td><?= htmlspecialchars($model['financial_year'] ?: 'N/A') ?></td></tr>
                        <tr class="border-top"><th class="text-muted pt-2">Purchase Date</th><td class="pt-2 fw-bold"><?= !empty($model['purchase_date']) ? date('d M Y', strtotime($model['purchase_date'])) : 'N/A' ?></td></tr>
                        <tr>
                            <th class="text-muted">Warranty Expiry</th>
                            <td>
                                <?php 
                                    if (!empty($model['expiry_date'])) {
                                        $is_exp = strtotime($model['expiry_date']) < time();
                                        echo "<span class='fw-bold " . ($is_exp ? "text-danger" : "text-success") . "'>" . date('d M Y', strtotime($model['expiry_date'])) . "</span>";
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="align-middle text-muted">Supply Order</th>
                            <td>
                                <?php if (!empty($model['supply_order_doc'])): ?>
                                    <a href="../../<?= htmlspecialchars($model['supply_order_doc']) ?>" target="_blank" class="btn btn-sm btn-outline-danger w-100 fw-bold"><i class="bi bi-file-pdf-fill me-1"></i> View Doc</a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Not Uploaded</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="bi bi-card-text me-2"></i> Specifications</h6>
                </div>
                <div class="card-body bg-light">
                    <div class="p-2">
                        <?= nl2br(htmlspecialchars($model['specifications'] ?: 'No specifications provided.')) ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-0 border-top border-info border-4 bg-info bg-opacity-10">
                <div class="card-body text-center py-4">
                    <h6 class="text-muted fw-bold mb-2 text-uppercase">Total Assets of this Model</h6>
                    <h1 class="display-3 fw-bolder text-info mb-0"><?= $total_assets ?></h1>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: ASSETS LIST & FILTERS -->
        <div class="col-md-8">
            <div class="card mb-4 shadow-sm border-0 bg-light">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Status</label>
                            <select name="status" class="form-select form-select-sm shadow-sm">
                                <option value="">All Status</option>
                                <?php
                                $sts_query = "SELECT DISTINCT s.status_id, s.status_name 
                                              FROM asset_status s
                                              JOIN assets a ON s.status_id = a.status_id
                                              WHERE a.model_id = '$id'
                                              ORDER BY s.status_name ASC";
                                $sts = mysqli_query($conn, $sts_query);
                                while($s = mysqli_fetch_assoc($sts)){
                                    $selected = ($status == $s['status_id']) ? "selected" : "";
                                    echo "<option value='{$s['status_id']}' $selected>{$s['status_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted text-uppercase">Location</label>
                            <select name="location" class="form-select form-select-sm shadow-sm">
                                <option value="">All Locations</option>
                                <?php
                                $loc_query = "SELECT DISTINCT l.location_id, l.dept_name 
                                              FROM locations l
                                              JOIN assets a ON l.location_id = a.location_id
                                              WHERE a.model_id = '$id'
                                              ORDER BY l.dept_name ASC";
                                $locs = mysqli_query($conn, $loc_query);
                                while($l = mysqli_fetch_assoc($locs)){
                                    $selected = ($location == $l['location_id']) ? "selected" : "";
                                    echo "<option value='{$l['location_id']}' $selected>{$l['dept_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm fw-bold"><i class="bi bi-funnel"></i> Filter</button>
                            <a href="models_details.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm w-100 shadow-sm">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">Linked Assets</h5>
                    <span class="badge bg-dark rounded-pill px-3"><?= $filtered_count ?> Results</span>
                </div>
                
                <!-- BULK ACTIONS FORM WRAPPER -->
                <form method="POST">
                    
                    <!-- SHOW QUICK STATE UPDATE BUTTONS ONLY FOR SURVEY OFF MODELS -->
                    <?php if ($is_survey_off_model): ?>
                        <div class="bg-light p-3 border-bottom d-flex align-items-center gap-2 flex-wrap">
                            <span class="small fw-bold text-muted text-uppercase me-2">Quick State Update (Survey Off):</span>
                            <button type="submit" name="bulk_action" value="In Use" class="btn btn-sm btn-success fw-bold shadow-sm" onclick="return confirm('Set selected assets state to In Use?')">
                                <i class="bi bi-check-circle-fill me-1"></i> Mark Selected as In Use
                            </button>
                            <button type="submit" name="bulk_action" value="Not In Use" class="btn btn-sm btn-outline-secondary fw-bold shadow-sm" onclick="return confirm('Set selected assets state to Not In Use?')">
                                <i class="bi bi-dash-circle me-1"></i> Mark Selected as Not In Use
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="assetsTable">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <!-- SHOW CHECKBOX COLUMN ONLY FOR SURVEY OFF MODELS -->
                                        <?php if ($is_survey_off_model): ?>
                                            <th class="ps-3 border-bottom-0" style="width: 40px;">
                                                <input class="form-check-input shadow-sm" type="checkbox" id="selectAll">
                                            </th>
                                        <?php endif; ?>
                                        <th class="<?= $is_survey_off_model ? '' : 'ps-3' ?> border-bottom-0">Asset Name</th>
                                        <th class="border-bottom-0">Serial No</th>
                                        <th class="border-bottom-0">Status</th>
                                        <th class="border-bottom-0">State</th>
                                        <th class="border-bottom-0">Location</th>
                                        <th class="border-bottom-0">Assigned To</th>
                                        <th class="text-center no-export border-bottom-0">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($filtered_count > 0): ?>
                                        <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                                            <tr>
                                                <?php if ($is_survey_off_model): ?>
                                                    <td class="ps-3">
                                                        <input class="form-check-input shadow-sm asset-checkbox" type="checkbox" name="selected_assets[]" value="<?= $asset['asset_id'] ?>">
                                                    </td>
                                                <?php endif; ?>
                                                <td class="<?= $is_survey_off_model ? '' : 'ps-3' ?> fw-bold text-dark"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                                <td><code class="bg-primary bg-opacity-10 text-primary px-2 py-1 rounded"><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                                
                                                <!-- STATUS (Assigned, Available, etc.) -->
                                                <td>
                                                    <?php
                                                    $badge_class = 'bg-secondary';
                                                    $st_name = $asset['asset_status_name'] ?? '';
                                                    if ($st_name == 'Assigned') $badge_class = 'bg-primary';
                                                    elseif ($st_name == 'Available') $badge_class = 'bg-success';
                                                    elseif ($st_name == 'Under Repair') $badge_class = 'bg-warning text-dark';
                                                    elseif (in_array($st_name, ['Retired', 'Condemned'])) $badge_class = 'bg-danger';
                                                    ?>
                                                    <span class="badge <?= $badge_class ?> rounded-pill">
                                                        <?= htmlspecialchars($st_name ?: 'N/A') ?>
                                                    </span>
                                                </td>

                                                <!-- STATE (Independent text field tracking In Use / Not In Use) -->
                                                <td>
                                                    <?php
                                                    $state_class = 'bg-secondary';
                                                    $state_val = $asset['asset_state'] ?? 'Working';
                                                    if ($state_val == 'In Use') $state_class = 'bg-success';
                                                    elseif ($state_val == 'Not In Use') $state_class = 'bg-secondary text-dark';
                                                    ?>
                                                    <span class="badge <?= $state_class ?> rounded-pill">
                                                        <?= htmlspecialchars($state_val) ?>
                                                    </span>
                                                </td>

                                                <td><?= htmlspecialchars($asset['dept_name'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php if (!empty($asset['user_name'])): ?>
                                                        <div class="fw-bold text-dark"><i class="bi bi-person text-muted me-1"></i><?= htmlspecialchars($asset['user_name']) ?></div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Not Assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center no-export">
                                                    <a href="../assets/asset_details.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm fw-bold">View</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="<?= $is_survey_off_model ? 8 : 7 ?>" class="text-center py-5 text-muted">
                                                <i class="bi bi-inboxes fs-2 d-block mb-2 opacity-50"></i>
                                                <h6 class="mb-0">No assets found matching your criteria.</h6>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const selectAll = document.getElementById("selectAll");
        const checkboxes = document.querySelectorAll(".asset-checkbox");

        if (selectAll) {
            selectAll.addEventListener("change", function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

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
    });

    function exportToPDF() {
        if (typeof window.jspdf === 'undefined') {
            alert("PDF library is still loading. Please wait a moment.");
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        doc.setFontSize(16);
        doc.text("Assets in Model: <?= addslashes($model['model_name']) ?>", 14, 15);

        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = 'none';
        });

        doc.autoTable({
            html: '#assetsTable',
            startY: 25,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [52, 58, 64] }
        });

        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = '';
        });

        const safeFilename = "<?= addslashes($model['model_name']) ?>".replace(/[^a-zA-Z0-9_-]/g, "_");
        doc.save("Model_Assets_" + safeFilename + "_<?= date('Y-m-d') ?>.pdf");
    }
</script>

<?php 
if(ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>