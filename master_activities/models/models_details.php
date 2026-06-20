<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

/* ---------- MODEL BASIC INFO ---------- */
$model_query = "
SELECT m.*, c.category_name, v.vendor_name
FROM asset_models m
LEFT JOIN asset_categories c ON m.category_id = c.category_id
LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
WHERE m.model_id = '$id'
";
$model = mysqli_fetch_assoc(mysqli_query($conn, $model_query));

if (!$model) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Asset Model not found.</div></div>";
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

/* ---------- ASSETS USING THIS MODEL ---------- */
$assets_query = "
SELECT a.*, s.status_name, l.dept_name
FROM assets a
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN locations l ON a.location_id = l.location_id
$where
ORDER BY a.asset_id DESC
";
$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);

// Total count for the model (unfiltered)
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE model_id = '$id'");
$total_assets = mysqli_fetch_assoc($total_query)['total'];

/* ---------- EXPORT LOGIC ---------- */
if(isset($_GET['export'])) {
    $format = $_GET['export'];
    $clean_model_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $model['model_name']);
    $filename = "Inventory_" . $clean_model_name . "_" . date('Y-m-d');

    // Get filter names for the report
    $status_name = "All";
    if($status) {
        $st_res = mysqli_query($conn, "SELECT status_name FROM asset_status WHERE status_id='$status'");
        $status_name = mysqli_fetch_assoc($st_res)['status_name'];
    }
    $dept_name = "All";
    if($location) {
        $loc_res = mysqli_query($conn, "SELECT dept_name FROM locations WHERE location_id='$location'");
        $dept_name = mysqli_fetch_assoc($loc_res)['dept_name'];
    }

    if($format == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');
        
        // Add Model Info and Filters at the top of CSV
        fputcsv($output, ['Model Profile:', $model['model_name']]);
        fputcsv($output, ['Category:', $model['category_name']]);
        fputcsv($output, ['Vendor:', $model['vendor_name']]);
        if($status) fputcsv($output, ['Filter - Status:', $status_name]);
        if($location) fputcsv($output, ['Filter - Location:', $dept_name]);
        fputcsv($output, []); // Empty row
        
        fputcsv($output, ['Asset ID', 'Asset Name', 'Serial Number', 'Status', 'Location', 'Cost', 'Purchase Date']);
        while($row = mysqli_fetch_assoc($assets_result)) {
            fputcsv($output, [$row['asset_id'], $row['asset_name'], $row['serial_number'], $row['status_name'], $row['dept_name'], $row['cost'], $row['purchase_date']]);
        }
        fclose($output);
        exit();
    }

    if($format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><style>
                .header{font-size:16pt; font-weight:bold;}
                .meta{font-size:10pt; color:#555;}
                .filter-box{background-color:#f9f9f9; border:1px solid #ddd; padding:10px;}
                th{background-color:#D3D3D3; font-weight:bold; border:0.5pt solid #000;}
                td{border:0.5pt solid #000;}
              </style></head><body>';
        
        echo '<table>';
        echo '<tr><th colspan="7" class="header">Asset Inventory Report</th></tr>';
        echo '<tr><th colspan="7" class="meta">Generated on: ' . date('d M Y H:i') . '</th></tr>';
        echo '<tr></tr>';
        
        // Model Details Section
        echo '<tr><td colspan="2" style="font-weight:bold; background-color:#eee;">Model Name:</td><td colspan="5">' . htmlspecialchars($model['model_name']) . '</td></tr>';
        echo '<tr><td colspan="2" style="font-weight:bold; background-color:#eee;">Category:</td><td colspan="5">' . htmlspecialchars($model['category_name']) . '</td></tr>';
        echo '<tr><td colspan="2" style="font-weight:bold; background-color:#eee;">Vendor:</td><td colspan="5">' . htmlspecialchars($model['vendor_name']) . '</td></tr>';
        
        // Specifications
        echo '<tr><td colspan="2" style="font-weight:bold; background-color:#eee; vertical-align:top;">Specifications:</td><td colspan="5">' . nl2br(htmlspecialchars($model['specifications'])) . '</td></tr>';
        
        // Active Filters Section (Only if selected)
        if($status || $location) {
            echo '<tr></tr>';
            echo '<tr><th colspan="7" style="background-color:#fff3cd; text-align:left;">Active Filters Applied:</th></tr>';
            if($status) echo '<tr><td colspan="2" style="font-weight:bold;">Status:</td><td colspan="5">' . $status_name . '</td></tr>';
            if($location) echo '<tr><td colspan="2" style="font-weight:bold;">Location:</td><td colspan="5">' . $dept_name . '</td></tr>';
        }
        
        echo '<tr></tr>';
        echo '<tr>';
        echo '<th>Asset ID</th><th>Asset Name</th><th>Serial Number</th><th>Status</th><th>Location</th><th>Cost</th><th>Purchase Date</th>';
        echo '</tr>';
        
        while($row = mysqli_fetch_assoc($assets_result)) {
            echo '<tr>';
            echo '<td>' . $row['asset_id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['asset_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['serial_number']) . '</td>';
            echo '<td>' . htmlspecialchars($row['status_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['dept_name']) . '</td>';
            echo '<td>' . number_format($row['cost'], 2) . '</td>';
            echo '<td>' . $row['purchase_date'] . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit();
    }

    if($format == 'print') {
        echo "<html><head><title>Export - {$model['model_name']}</title>";
        echo "<style>
                body{font-family:sans-serif; padding:20px;}
                table{width:100%; border-collapse:collapse; margin-top:20px;} 
                th,td{border:1px solid #ccc; padding:8px; text-align:left;} 
                th{background:#f4f4f4;}
                .report-header{border-bottom:2px solid #333; padding-bottom:10px; margin-bottom:20px;}
                .filter-info{background:#fff3cd; padding:10px; border:1px solid #ffeeba; margin-bottom:20px;}
              </style></head><body>";
        
        echo "<div class='report-header'>";
        echo "<h1>Asset Inventory: {$model['model_name']}</h1>";
        echo "<p><strong>Category:</strong> {$model['category_name']} | <strong>Vendor:</strong> {$model['vendor_name']}</p>";
        echo "<p><strong>Specifications:</strong><br>" . nl2br(htmlspecialchars($model['specifications'])) . "</p>";
        echo "</div>";

        if($status || $location) {
            echo "<div class='filter-info'>";
            echo "<strong>Applied Filters:</strong> ";
            if($status) echo "Status: <span class='badge'>$status_name</span> ";
            if($location) echo " | Location: <span class='badge'>$dept_name</span>";
            echo "</div>";
        }

        echo "<table><thead><tr><th>ID</th><th>Asset Name</th><th>Serial No</th><th>Status</th><th>Location</th><th>Cost</th></tr></thead><tbody>";
        while($row = mysqli_fetch_assoc($assets_result)) {
            echo "<tr><td>{$row['asset_id']}</td><td>{$row['asset_name']}</td><td>{$row['serial_number']}</td><td>{$row['status_name']}</td><td>{$row['dept_name']}</td><td>{$row['cost']}</td></tr>";
        }
        echo "</tbody></table><script>window.print();</script></body></html>";
        exit();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Model Profile: <?= htmlspecialchars($model['model_name']) ?></h2>
        <div class="btn-group">
            <div class="dropdown me-2">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export Data
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&status=<?= $status ?>&location=<?= $location ?>&export=excel"><i class="bi bi-file-earmark-excel text-success"></i> Export as Excel (.xls)</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&status=<?= $status ?>&location=<?= $location ?>&export=csv"><i class="bi bi-file-earmark-text text-primary"></i> Export as CSV</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&status=<?= $status ?>&location=<?= $location ?>&export=print" target="_blank"><i class="bi bi-printer text-dark"></i> Print Report</a></li>
                </ul>
            </div>
            <a href="<?= ROUTE_MODELS_EDIT ?>?id=<?= $id ?>" class="btn btn-warning">Edit Model</a>
            <a href="<?= ROUTE_MODELS ?>" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: MODEL SPECIFICATIONS -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Model Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th width="40%">Model ID</th><td><?= $model['model_id'] ?></td></tr>
                        <tr><th>Category</th><td><?= htmlspecialchars($model['category_name']) ?></td></tr>
                        <tr><th>Vendor</th><td><?= htmlspecialchars($model['vendor_name']) ?></td></tr>
                        <tr><th>Created At</th><td><?= date('d M Y', strtotime($model['created_at'])) ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Technical Specifications</h5>
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
                                // Only show status that have assets of this model
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
                                // Only show locations that have assets of this model
                                $locs_query = "SELECT DISTINCT l.location_id, l.dept_name 
                                               FROM locations l
                                               JOIN assets a ON l.location_id = a.location_id
                                               WHERE a.model_id = '$id'
                                               ORDER BY l.dept_name ASC";
                                $locs = mysqli_query($conn, $locs_query);
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
                    <h5 class="mb-0">Assets Inventory (<?= htmlspecialchars($model['model_name']) ?>)</h5>
                    <span class="badge bg-dark"><?= $filtered_count ?> Results</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset Name</th>
                                    <th>Serial No</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($filtered_count > 0): ?>
                                    <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                            <td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                            <td>
                                                <span class="badge bg-info"><?= $asset['status_name'] ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($asset['dept_name']) ?></td>
                                            <td class="text-center">
                                                <a href="../assets/asset_details.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-primary">View Asset</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No assets found matching your criteria.</td>
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

<?php include("../../includes/footer.php"); ?>
