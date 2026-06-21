<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- FILTER HANDLING ---------- */
$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$status   = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';
$model    = $_GET['model'] ?? '';

$where = "WHERE 1=1";

if ($search != "") {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
                    a.asset_name LIKE '%$search_escaped%' 
                    OR a.serial_number LIKE '%$search_escaped%'
                )";
}

if ($category != "") {
    $category_escaped = mysqli_real_escape_string($conn, $category);
    $where .= " AND a.category_id = '$category_escaped'";
}

if ($status != "") {
    $status_escaped = mysqli_real_escape_string($conn, $status);
    $where .= " AND a.status_id = '$status_escaped'";
}

if ($location != "") {
    $location_escaped = mysqli_real_escape_string($conn, $location);
    $where .= " AND a.location_id = '$location_escaped'";
}

if ($model != "") {
    $model_escaped = mysqli_real_escape_string($conn, $model);
    $where .= " AND a.model_id = '$model_escaped'";
}

/* ---------- PAGINATION ---------- */
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

/* ---------- MAIN QUERY ---------- */
$query = "
SELECT 
    a.*,
    c.category_name,
    s.status_name,
    l.dept_name,
    m.model_name,
    aa.assignment_id,
    aa.user_id AS assigned_user_id,
    u.name AS assigned_user_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_status s ON a.status_id = s.status_id
LEFT JOIN locations l ON a.location_id = l.location_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
LEFT JOIN asset_assignments aa 
       ON a.asset_id = aa.asset_id 
      AND aa.returned_date IS NULL
LEFT JOIN users u ON aa.user_id = u.user_id
$where
ORDER BY a.asset_id DESC
LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Assets Inventory</h2>
        <a href="assets_add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Asset
        </a>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <?php if($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Asset deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['msg'] == 'cannot_delete_assigned'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                This asset cannot be deleted because it has an active assignment.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif($_GET['msg'] == 'error'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Error occurred while processing request.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- FILTER FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Name or Serial No."
                           value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php
                        $cats = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY category_name ASC");
                        while($c = mysqli_fetch_assoc($cats)){
                            $selected = ($category == $c['category_id']) ? "selected" : "";
                            echo "<option value='{$c['category_id']}' $selected>" . htmlspecialchars($c['category_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Model</label>
                    <select name="model" class="form-select">
                        <option value="">All Models</option>
                        <?php
                        $mods = mysqli_query($conn, "SELECT * FROM asset_models ORDER BY model_name ASC");
                        while($m = mysqli_fetch_assoc($mods)){
                            $selected = ($model == $m['model_id']) ? "selected" : "";
                            echo "<option value='{$m['model_id']}' $selected>" . htmlspecialchars($m['model_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php
                        $sts = mysqli_query($conn, "SELECT * FROM asset_status ORDER BY status_name ASC");
                        while($s = mysqli_fetch_assoc($sts)){
                            $selected = ($status == $s['status_id']) ? "selected" : "";
                            echo "<option value='{$s['status_id']}' $selected>" . htmlspecialchars($s['status_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Location</label>
                    <select name="location" class="form-select">
                        <option value="">All Locations</option>
                        <?php
                        $loc = mysqli_query($conn, "SELECT * FROM locations ORDER BY dept_name ASC");
                        while($l = mysqli_fetch_assoc($loc)){
                            $selected = ($location == $l['location_id']) ? "selected" : "";
                            echo "<option value='{$l['location_id']}' $selected>" . htmlspecialchars($l['dept_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <a href="assets_list.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Asset Name</th>
                            <th>Serial No</th>
                            <th>Category</th>
                            <th>Model</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Assigned To</th>
                            <th>Cost</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['asset_id'] ?></td>

                                    <td class="fw-bold">
                                        <a href="asset_details.php?id=<?= $row['asset_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['asset_name']) ?>
                                        </a>
                                    </td>

                                    <td><code><?= htmlspecialchars($row['serial_number']) ?></code></td>

                                    <td><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>

                                    <td><?= htmlspecialchars($row['model_name'] ?? 'N/A') ?></td>

                                    <td>
                                        <?php
                                        $badge_class = 'bg-secondary';
                                        if (in_array($row['status_name'], ['Available', 'Working', 'Spare'])) {
                                            $badge_class = 'bg-success';
                                        } elseif ($row['status_name'] == 'Assigned') {
                                            $badge_class = 'bg-primary';
                                        } elseif ($row['status_name'] == 'Under Repair') {
                                            $badge_class = 'bg-warning text-dark';
                                        } elseif (in_array($row['status_name'], ['Retired', 'Condemned'])) {
                                            $badge_class = 'bg-danger';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= htmlspecialchars($row['status_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>

                                    <td><?= htmlspecialchars($row['dept_name'] ?? 'N/A') ?></td>

                                    <td>
                                        <?php if(!empty($row['assigned_user_name'])): ?>
                                            <span class="fw-bold"><?= htmlspecialchars($row['assigned_user_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Not Assigned</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>₹ <?= number_format((float)$row['cost'], 2) ?></td>

                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="asset_details.php?id=<?= $row['asset_id'] ?>" 
                                               class="btn btn-info" title="View Details">View</a>

                                            <a href="assets_edit.php?id=<?= $row['asset_id'] ?>"
                                               class="btn btn-warning" title="Edit Asset">Edit</a>

                                            <a href="asset_delete.php?id=<?= $row['asset_id'] ?>"
                                               class="btn btn-danger"
                                               onclick="return confirm('Are you sure you want to delete this asset? This action cannot be undone.')"
                                               title="Delete Asset">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    No assets found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets a $where");
    $total_rows = mysqli_fetch_assoc($total_query)['total'];
    $total_pages = ceil($total_rows / $limit);
    ?>

    <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&model=<?= urlencode($model) ?>">
                        Previous
                    </a>
                </li>

                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <li class="page-item <?= ($i==$page)?'active':'' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&model=<?= urlencode($model) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&model=<?= urlencode($model) ?>">
                        Next
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include("../../includes/footer.php"); ?>
