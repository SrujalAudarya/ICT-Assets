<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

/* ---------- CATEGORY BASIC INFO ---------- */
$cat_query = "SELECT * FROM asset_categories WHERE category_id = '$id'";
$category = mysqli_fetch_assoc(mysqli_query($conn, $cat_query));

if (!$category) {
    include("../../includes/header.php");
    echo "<div class='container mt-4'><div class='alert alert-danger'>Category not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* ---------- FILTER HANDLING ---------- */
$model = $_GET['model'] ?? '';
$status = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';

$where = "WHERE a.category_id = '$id'";
if ($model != "") {
    $where .= " AND a.model_id = '" . mysqli_real_escape_string($conn, $model) . "'";
}
if ($status != "") {
    $where .= " AND a.status_id = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if ($location != "") {
    $where .= " AND a.location_id = '" . mysqli_real_escape_string($conn, $location) . "'";
}

$assets_query = "SELECT a.*,u.name as user_name, s.status_name, l.dept_name, m.model_name
                 FROM assets a
                 LEFT JOIN asset_status s ON a.status_id = s.status_id
                 LEFT JOIN locations l ON a.location_id = l.location_id
                 LEFT JOIN asset_models m ON a.model_id = m.model_id
                 LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id
                 LEFT JOIN users u ON asn.user_id = u.user_id
                 $where ORDER BY a.asset_id DESC";

/* ---------- EXPORT LOGIC ---------- */
if (isset($_GET['export'])) {
    $export_res = mysqli_query($conn, $assets_query);
    $filename = "Category_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $category['category_name']) . "_" . date('Y-m-d');

    if ($_GET['export'] == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo '<table border="1"><tr><th>Asset Name</th><th>User</th><th>Serial No</th><th>Model</th><th>Status</th><th>Location</th></tr>';
        while ($r = mysqli_fetch_assoc($export_res)) {
            echo "<tr><td>{$r['asset_name']}</td><td>{$r['user_name']}</td><td>{$r['serial_number']}</td><td>{$r['model_name']}</td><td>{$r['status_name']}</td><td>{$r['dept_name']}</td></tr>";
        }
        echo '</table>';
        exit();
    }
    if ($_GET['export'] == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $o = fopen('php://output', 'w');
        fputcsv($o, ['Asset Name', 'User', 'Serial No', 'Model', 'Status', 'Location']);
        while ($r = mysqli_fetch_assoc($export_res)) {
            fputcsv($o, [$r['asset_name'], $r['user_name'], $r['serial_number'], $r['model_name'], $r['status_name'], $r['dept_name']]);
        }
        fclose($o);
        exit();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");

$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);
$total_assets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE category_id = '$id'"))['total'];
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Category Profile: <?= htmlspecialchars($category['category_name']) ?></h2>
        <div class="d-flex gap-2">
            <!-- Export Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&model=<?= $model ?>&status=<?= $status ?>&location=<?= $location ?>&export=excel"><i class="bi bi-file-earmark-excel text-success"></i> Excel</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&model=<?= $model ?>&status=<?= $status ?>&location=<?= $location ?>&export=csv"><i class="bi bi-file-earmark-text text-primary"></i> CSV</a></li>
                </ul>
            </div>
            <a href="<?= ROUTE_CATEGORIES_EDIT ?>?id=<?= $id ?>" class="btn btn-warning">Edit Category</a>
            <a href="<?= ROUTE_CATEGORIES ?>" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5>Category Details</h5>
                </div>
                <div class="card-body">
                    <label class="text-muted small d-block">Category Name</label>
                    <span class="h4 fw-bold"><?= htmlspecialchars($category['category_name']) ?></span>
                    <label class="text-muted small d-block mt-3">Description</label>
                    <p class="bg-light p-3 border rounded"><?= nl2br(htmlspecialchars($category['description'] ?: 'No description provided.')) ?></p>
                </div>
            </div>
            <div class="card shadow-sm mb-4 border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Assets</h6>
                    <h2 class="display-4 fw-bold text-info"><?= $total_assets ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="col-md-3"><label class="form-label small">Model</label>
                            <select name="model" class="form-select form-select-sm">
                                <option value="">All Models</option>
                                <?php $mods = mysqli_query($conn, "SELECT DISTINCT m.model_id, m.model_name FROM asset_models m JOIN assets a ON m.model_id = a.model_id WHERE a.category_id = '$id' ORDER BY m.model_name ASC");
                                while ($m = mysqli_fetch_assoc($mods)) {
                                    $sel = ($model == $m['model_id']) ? "selected" : "";
                                    echo "<option value='{$m['model_id']}' $sel>{$m['model_name']}</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Status</option>
                                <?php $sts = mysqli_query($conn, "SELECT DISTINCT s.status_id, s.status_name FROM asset_status s JOIN assets a ON s.status_id = a.status_id WHERE a.category_id = '$id' ORDER BY s.status_name ASC");
                                while ($s = mysqli_fetch_assoc($sts)) {
                                    $sel = ($status == $s['status_id']) ? "selected" : "";
                                    echo "<option value='{$s['status_id']}' $sel>{$s['status_name']}</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label small">Location</label>
                            <select name="location" class="form-select form-select-sm">
                                <option value="">All Locations</option>
                                <?php $locs = mysqli_query($conn, "SELECT DISTINCT l.location_id, l.dept_name FROM locations l JOIN assets a ON l.location_id = a.location_id WHERE a.category_id = '$id' ORDER BY l.dept_name ASC");
                                while ($l = mysqli_fetch_assoc($locs)) {
                                    $sel = ($location == $l['location_id']) ? "selected" : "";
                                    echo "<option value='{$l['location_id']}' $sel>{$l['dept_name']}</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2 w-100">Filter</button>
                            <a href="?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between">
                    <h5>Assets</h5><span class="badge bg-dark"><?= $filtered_count ?> Results</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="assetsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Asset Name</th>
                                <th>User Name</th>
                                <th>Serial No</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th class="text-center no-export">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($filtered_count > 0): while ($asset = mysqli_fetch_assoc($assets_result)): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                        <td><?= htmlspecialchars($asset['user_name'] ?? '-') ?></td>
                                        <td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                        <td><?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-info"><?= $asset['status_name'] ?></span></td>
                                        <td><?= htmlspecialchars($asset['dept_name']) ?></td>
                                        <td class="text-center no-export"><a href="../assets/asset_details.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No assets found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    function exportToPDF() {

        const {
            jsPDF
        } = window.jspdf;
        const doc = new jsPDF('landscape');

        doc.setFontSize(16);
        doc.text("Assets in: <?= addslashes($category['category_name']) ?>", 14, 15);

        // Hide Action column
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
                fillColor: [52, 58, 64]
            }
        });

        // Show Action column again
        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = '';
        });

        doc.save("Category_Assets_<?= date('Y-m-d') ?>.pdf");
    }
</script>

<?php include("../../includes/footer.php"); ?>