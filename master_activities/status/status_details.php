<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

/* ---------- STATUS BASIC INFO ---------- */
$status_query = "SELECT * FROM asset_status WHERE status_id = '$id'";
$status = mysqli_fetch_assoc(mysqli_query($conn, $status_query));

if (!$status) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Status definition not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* ---------- FILTER HANDLING ---------- */
$category = $_GET['category'] ?? '';
$model = $_GET['model'] ?? '';
$location = $_GET['location'] ?? '';

$where = "WHERE a.status_id = '$id'";

if($category != ""){
    $category_escaped = mysqli_real_escape_string($conn, $category);
    $where .= " AND a.category_id = '$category_escaped'";
}

if($model != ""){
    $model_escaped = mysqli_real_escape_string($conn, $model);
    $where .= " AND a.model_id = '$model_escaped'";
}

if($location != ""){
    $location_escaped = mysqli_real_escape_string($conn, $location);
    $where .= " AND a.location_id = '$location_escaped'";
}

/* ---------- ASSETS WITH THIS STATUS ---------- */
$assets_query = "
SELECT a.*, c.category_name, l.dept_name, m.model_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN locations l ON a.location_id = l.location_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
$where
ORDER BY a.asset_id DESC
";
$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);

// Total count for the status (unfiltered)
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE status_id = '$id'");
$total_assets = mysqli_fetch_assoc($total_query)['total'];
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Status Profile: <?= htmlspecialchars($status['status_name']) ?></h2>
        <div>
            <a href="status_edit.php?id=<?= $id ?>" class="btn btn-warning">Edit Status</a>
            <a href="status_list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: STATUS INFO -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Status Definition</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Status Name</label>
                        <span class="h4 fw-bold"><?= htmlspecialchars($status['status_name']) ?></span>
                    </div>
                    <div class="mb-0">
                        <label class="text-muted small d-block">Description</label>
                        <p class="bg-light p-3 border rounded">
                            <?= nl2br(htmlspecialchars($status['description'] ?: 'No description provided.')) ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Assets with this Status</h6>
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
                                // Only show categories that have assets with this status
                                $cats_query = "SELECT DISTINCT c.category_id, c.category_name 
                                               FROM asset_categories c
                                               JOIN assets a ON c.category_id = a.category_id
                                               WHERE a.status_id = '$id'
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
                                // Only show models that have assets with this status
                                $mods_query = "SELECT DISTINCT m.model_id, m.model_name 
                                               FROM asset_models m
                                               JOIN assets a ON m.model_id = a.model_id
                                               WHERE a.status_id = '$id'
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
                            <label class="form-label small">Location</label>
                            <select name="location" class="form-select form-select-sm">
                                <option value="">All Locations</option>
                                <?php
                                // Only show locations that have assets with this status
                                $locs_query = "SELECT DISTINCT l.location_id, l.dept_name 
                                               FROM locations l
                                               JOIN assets a ON l.location_id = a.location_id
                                               WHERE a.status_id = '$id'
                                               ORDER BY l.dept_name ASC";
                                $locs = mysqli_query($conn, $locs_query);
                                while($l = mysqli_fetch_assoc($locs)){
                                    $selected = ($location == $l['location_id']) ? "selected" : "";
                                    echo "<option value='{$l['location_id']}' $selected>{$l['dept_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-2 w-100">Filter</button>
                            <a href="status_details.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Assets marked as "<?= htmlspecialchars($status['status_name']) ?>"</h5>
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
                                            <td><?= htmlspecialchars($asset['category_name']) ?></td>
                                            <td><?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($asset['dept_name']) ?></td>
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
