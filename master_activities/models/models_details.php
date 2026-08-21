<?php
ob_start(); // CRITICAL: Protects the Excel/CSV Export Headers from failing
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

// Safely capture the ID
$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '0';

/* ---------- MODEL BASIC INFO ---------- */
// Joined asset_categories a second time to fetch the Parent Category Name
$model_query = "
    SELECT m.*, 
           c.category_name, 
           c.parent_id,
           pc.category_name AS parent_category_name,
           v.vendor_name
    FROM asset_models m
    LEFT JOIN asset_categories c ON m.category_id = c.category_id
    LEFT JOIN asset_categories pc ON c.parent_id = pc.category_id
    LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
    WHERE m.model_id = '$id'
";
$model = mysqli_fetch_assoc(mysqli_query($conn, $model_query));

if (!$model) {
    include("../../includes/header.php");
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm border-0'><i class='bi bi-exclamation-triangle-fill me-2'></i> Model not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

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
    s.status_name, 
    l.dept_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN locations l ON a.location_id = l.location_id
/* ONLY CURRENT ASSIGNMENT */
LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id AND asn.returned_date IS NULL
LEFT JOIN users u ON asn.user_id = u.user_id
$where
GROUP BY a.asset_id   
ORDER BY a.asset_id DESC
";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV) - CORRECTED
   ========================================================= */
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    
    // Aggressively clear output buffer to prevent corrupted files
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
    fputcsv($output, ['Sr No', 'Asset Name', 'Serial No', 'Category', 'Status', 'Location', 'Assigned To'], $delimiter);

    $sr = 1;
    while ($r = mysqli_fetch_assoc($export_res)) {
        fputcsv($output, [
            $sr++,
            $r['asset_name'],
            $r['serial_number'],
            $r['category_name'] ?? 'N/A',
            $r['status_name'] ?? 'N/A',
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

// Total count for the model (unfiltered)
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE model_id = '$id'");
$total_assets = mysqli_fetch_assoc($total_query)['total'];

// Generate Export URLs keeping filters intact
$exportParams = $_GET;
$exportParams['export'] = 'excel';
$exportExcelUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'csv';
$exportCsvUrl = '?' . http_build_query($exportParams);
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-dark"><i class="bi bi-box-seam me-2 text-primary"></i> <?= htmlspecialchars($model['model_name']) ?></h2>
            <div class="text-muted mt-1 small">Detailed Profile & Linked Assets</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            
            <!-- EXPORT DROPDOWN WITH FIXED HOVER STYLING & NATIVE JS -->
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

    <div class="row">
        <!-- LEFT COLUMN: MODEL INFO & IMAGE -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4 border-0 border-top border-primary border-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary fw-bold"><i class="bi bi-info-circle-fill me-1"></i> Model Information</h5>
                </div>
                <div class="card-body">
                    <!-- MODEL LOGO / IMAGE DISPLAY -->
                    <div class="text-center mb-4 p-3 bg-light border rounded shadow-sm">
                        <?php if (!empty($model['model_image'])): ?>
                            <img src="../../<?= htmlspecialchars($model['model_image']) ?>" class="img-fluid rounded" style="max-height: 150px; object-fit: contain;" alt="Model Image/Logo">
                        <?php else: ?>
                            <div class="text-muted py-4">
                                <i class="bi bi-image fs-1 d-block mb-1"></i>
                                <small>No Image / Logo Uploaded</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <table class="table table-sm table-borderless">
                        <tr><th width="40%" class="text-muted">Model Name</th><td class="fw-bold"><?= htmlspecialchars($model['model_name']) ?></td></tr>
                        <tr><th class="text-muted">Make</th><td><?= htmlspecialchars($model['make_name'] ?: 'N/A') ?></td></tr>
                        
                        <!-- DYNAMIC CATEGORY DISPLAY (Shows Parent » Child) -->
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
                        
                        <!-- QUANTITY & COST -->
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
            <!-- FILTER FORM -->
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
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <!-- ADDED ID="assetsTable" HERE FOR PDF EXPORT -->
                        <table class="table table-hover align-middle mb-0" id="assetsTable">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th class="ps-3 border-bottom-0">Asset Name</th>
                                    <th class="border-bottom-0">Serial No</th>
                                    <th class="border-bottom-0">Status</th>
                                    <th class="border-bottom-0">Location</th>
                                    <th class="border-bottom-0">Assigned To</th>
                                    <th class="text-center no-export border-bottom-0">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($filtered_count > 0): ?>
                                    <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                            
                                            <td><code class="bg-primary bg-opacity-10 text-primary px-2 py-1 rounded"><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                            
                                            <td>
                                                <?php
                                                $badge_class = 'bg-secondary';
                                                if (($asset['status_name'] ?? '') == 'Assigned') $badge_class = 'bg-primary';
                                                elseif (in_array(($asset['status_name'] ?? ''), ['Available', 'Working'])) $badge_class = 'bg-success';
                                                elseif (($asset['status_name'] ?? '') == 'Under Repair') $badge_class = 'bg-warning text-dark';
                                                elseif (in_array(($asset['status_name'] ?? ''), ['Retired', 'Condemned'])) $badge_class = 'bg-danger';
                                                ?>
                                                <span class="badge <?= $badge_class ?> rounded-pill">
                                                    <?= htmlspecialchars($asset['status_name'] ?? 'N/A') ?>
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
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-inboxes fs-2 d-block mb-2 opacity-50"></i>
                                            <h6 class="mb-0">No assets found matching your criteria.</h6>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     PDF EXPORT SCRIPT & DROPDOWN TOGGLE JS
     ========================================================= -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    // JS PDF LOGIC
    function exportToPDF() {
        if (typeof window.jspdf === 'undefined') {
            alert("PDF library is still loading. Please wait a moment.");
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        doc.setFontSize(16);
        doc.text("Assets in Model: <?= addslashes($model['model_name']) ?>", 14, 15);

        // Temporarily hide the Action column
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

        // Restore the Action column in the HTML view
        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = '';
        });

        const safeFilename = "<?= addslashes($model['model_name']) ?>".replace(/[^a-zA-Z0-9_-]/g, "_");
        doc.save("Model_Assets_" + safeFilename + "_<?= date('Y-m-d') ?>.pdf");
    }

    // EXPORT DROPDOWN FALLBACK LOGIC
    document.addEventListener("DOMContentLoaded", function() {
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
</script>

<?php 
if(ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>