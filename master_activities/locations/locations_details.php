<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

/* ---------- LOCATION BASIC INFO ---------- */
$loc_query = "SELECT * FROM locations WHERE location_id = '$id'";
$location = mysqli_fetch_assoc(mysqli_query($conn, $loc_query));

if (!$location) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Location not found.</div></div>";
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

/* ---------- ASSETS AT THIS LOCATION ---------- */
$assets_query = "
SELECT a.*, c.category_name, s.status_name, m.model_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
$where
ORDER BY a.asset_id DESC
";
$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);

// Total count for the location (unfiltered)
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE location_id = '$id'");
$total_assets = mysqli_fetch_assoc($total_query)['total'];
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Location Profile: <?= htmlspecialchars($location['dept_name']) ?></h2>
        <div>
            <a href="locations_edit.php?id=<?= $id ?>" class="btn btn-warning">Edit Location</a>
            <a href="locations_list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: LOCATION INFO -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Location Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th width="40%">ID</th><td><?= $location['location_id'] ?></td></tr>
                        <tr><th>Building</th><td><?= htmlspecialchars($location['building'] ?: 'N/A') ?></td></tr>
                        <tr><th>Floor</th><td><?= htmlspecialchars($location['floor'] ?: 'N/A') ?></td></tr>
                        <tr><th>Created At</th><td><?= date('d M Y', strtotime($location['created_at'])) ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Remarks / Notes</h5>
                </div>
                <div class="card-body">
                    <div class="bg-light p-3 border rounded">
                        <?= nl2br(htmlspecialchars($location['remarks'] ?: 'No additional remarks.')) ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Assets at this Location</h6>
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
                        
                        <div class="col-md-3">
                            <label class="form-label small">Category</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="">All Categories</option>
                                <?php
                                // Only show categories that have assets at this location
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

                        <div class="col-md-3">
                            <label class="form-label small">Model</label>
                            <select name="model" class="form-select form-select-sm">
                                <option value="">All Models</option>
                                <?php
                                // Only show models that have assets at this location
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

                        <div class="col-md-3">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Status</option>
                                <?php
                                // Only show status that have assets at this location
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

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2 w-100">Filter</button>
                            <a href="locations_details.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Assets Currently at <?= htmlspecialchars($location['dept_name']) ?></h5>
                    <span class="badge bg-dark"><?= $filtered_count ?> Results</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset Name</th>
                                    <th>Serial No</th>
                                    <th>Category</th>
                                    <th>Model</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($filtered_count > 0): ?>
                                    <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                            <td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                            <td><?= htmlspecialchars($asset['category_name']) ?></td>
                                            <td><?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge bg-info"><?= $asset['status_name'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <a href="../assets/asset_details.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No assets found matching your criteria.</td>
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
