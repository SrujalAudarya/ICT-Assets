<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

/* ---------- MODEL BASIC INFO ---------- */
$model_query = "
    SELECT m.*, 
           c.category_name, 
           v.vendor_name
    FROM asset_models m
    LEFT JOIN asset_categories c ON m.category_id = c.category_id
    LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
    WHERE m.model_id = '$id'
";
$model = mysqli_fetch_assoc(mysqli_query($conn, $model_query));

if (!$model) {
    include("../../includes/header.php");
    echo "<div class='container mt-4'><div class='alert alert-danger'>Model not found.</div></div>";
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
SELECT a.*, u.name as user_name, c.category_name, s.status_name, l.dept_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN locations l ON a.location_id = l.location_id
LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id
LEFT JOIN users u ON asn.user_id = u.user_id
$where
ORDER BY a.asset_id DESC
";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV)
   ========================================================= */
if (isset($_GET['export'])) {
    $export_res = mysqli_query($conn, $assets_query);
    $clean_model_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $model['model_name']);
    $filename = "Model_Assets_" . $clean_model_name . "_" . date('Y-m-d');

    /* ---------------- EXCEL EXPORT ---------------- */
    if ($_GET['export'] == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><style>td{border:0.5pt solid #000;} th{background-color:#D3D3D3; font-weight:bold; border:0.5pt solid #000;}</style></head><body>';
        echo '<table><tr><th colspan="6" style="font-size:14pt;">Assets in Model: ' . htmlspecialchars($model['model_name']) . '</th></tr>';
        echo '<tr><th>Asset Name</th><th>User Name</th><th>Serial No</th><th>Category</th><th>Status</th><th>Location</th></tr>';
        
        while ($r = mysqli_fetch_assoc($export_res)) {
            echo "<tr>
                    <td>{$r['asset_name']}</td>
                    <td>" . ($r['user_name'] ?? 'N/A') . "</td>
                    <td>{$r['serial_number']}</td>
                    <td>" . ($r['category_name'] ?? 'N/A') . "</td>
                    <td>" . ($r['status_name'] ?? 'N/A') . "</td>
                    <td>" . ($r['dept_name'] ?? 'N/A') . "</td>
                  </tr>";
        }
        echo '</table></body></html>';
        exit();
    }
    
    /* ---------------- CSV EXPORT ---------------- */
    if ($_GET['export'] == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Asset Name', 'User Name', 'Serial No', 'Category', 'Status', 'Location']);
        
        while ($r = mysqli_fetch_assoc($export_res)) {
            fputcsv($output, [
                $r['asset_name'],
                $r['user_name'] ?? 'N/A',
                $r['serial_number'],
                $r['category_name'] ?? 'N/A',
                $r['status_name'] ?? 'N/A',
                $r['dept_name'] ?? 'N/A'
            ]);
        }
        fclose($output);
        exit();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");

$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);

// Total count for the model (unfiltered)
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE model_id = '$id'");
$total_assets = mysqli_fetch_assoc($total_query)['total'];
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Model Profile: <?= htmlspecialchars($model['model_name']) ?></h2>
        <div class="d-flex gap-2 flex-wrap">
            <!-- Export Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger"></i> Export as PDF</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&status=<?= $status ?>&location=<?= $location ?>&export=excel"><i class="bi bi-file-earmark-excel text-success"></i> Export as Excel</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&status=<?= $status ?>&location=<?= $location ?>&export=csv"><i class="bi bi-file-earmark-text text-primary"></i> Export as CSV</a></li>
                </ul>
            </div>
            <a href="<?= ROUTE_MODELS_EDIT ?>?id=<?= $id ?>" class="btn btn-warning">Edit Model</a>
            <a href="<?= ROUTE_MODELS ?>" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: MODEL INFO -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Model Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th width="40%">Model Name</th><td><?= htmlspecialchars($model['model_name']) ?></td></tr>
                        <tr><th>Make</th><td><?= htmlspecialchars($model['make_name'] ?: 'N/A') ?></td></tr>
                        <tr><th>Category</th><td><?= htmlspecialchars($model['category_name'] ?: 'N/A') ?></td></tr>
                        <tr><th>Vendor</th><td><?= htmlspecialchars($model['vendor_name'] ?: 'N/A') ?></td></tr>
                        <tr><th>Contract No</th><td><?= htmlspecialchars($model['contract_no'] ?: 'N/A') ?></td></tr>
                        <tr><th>Quantity</th><td><?= (int)($model['quantity'] ?? 0) ?></td></tr>
                        <tr><th>Date</th><td><?= !empty($model['purchase_date']) ? date('d M Y', strtotime($model['purchase_date'])) : 'N/A' ?></td></tr>
                        <tr><th>F.Y.</th><td><?= htmlspecialchars($model['financial_year'] ?: 'N/A') ?></td></tr>
                        <tr><th>Created At</th><td><?= !empty($model['created_at']) ? date('d M Y', strtotime($model['created_at'])) : 'N/A' ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Specifications</h5>
                </div>
                <div class="card-body">
                    <div class="bg-light p-3 border rounded">
                        <?= nl2br(htmlspecialchars($model['specifications'] ?: 'No specifications provided.')) ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Assets of this Model</h6>
                    <h2 class="display-4 fw-bold text-info"><?= $total_assets ?></h2>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: ASSETS LIST & FILTERS -->
        <div class="col-md-8">
            <!-- FILTER FORM -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="col-md-4">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
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
                            <label class="form-label small">Location</label>
                            <select name="location" class="form-select form-select-sm">
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

                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2 w-100">Filter</button>
                            <a href="models_details.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Assets under "<?= htmlspecialchars($model['model_name']) ?>"</h5>
                    <span class="badge bg-dark"><?= $filtered_count ?> Results</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <!-- ADDED ID="assetsTable" HERE FOR PDF EXPORT -->
                        <table class="table table-hover table-striped mb-0" id="assetsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset Name</th>
                                    <th>User Name</th>
                                    <th>Serial No</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <!-- ADDED "no-export" CLASS HERE -->
                                    <th class="text-center no-export">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($filtered_count > 0): ?>
                                    <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($asset['user_name'] ?? 'N/A') ?></td>
                                            <td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                            <td><?= htmlspecialchars($asset['category_name'] ?? 'N/A') ?></td>
                                            <td><span class="badge bg-info"><?= htmlspecialchars($asset['status_name'] ?? 'N/A') ?></span></td>
                                            <td><?= htmlspecialchars($asset['dept_name'] ?? 'N/A') ?></td>
                                            <!-- ADDED "no-export" CLASS HERE -->
                                            <td class="text-center no-export">
                                                <a href="../assets/asset_details.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No assets found matching your criteria.</td>
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
     PDF EXPORT SCRIPT
     ========================================================= -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        // Using landscape since tables with many columns fit better
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
                cellPadding: 2
            },
            headStyles: {
                fillColor: [52, 58, 64] // Matches standard dark header
            }
        });

        // Restore the Action column in the HTML view
        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = '';
        });

        const safeFilename = "<?= addslashes($model['model_name']) ?>".replace(/[^a-zA-Z0-9_-]/g, "_");
        doc.save("Model_Assets_" + safeFilename + "_<?= date('Y-m-d') ?>.pdf");
    }
</script>

<?php include("../../includes/footer.php"); ?>