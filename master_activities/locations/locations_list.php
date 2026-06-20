<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- FILTER HANDLING ---------- */
$search = trim($_GET['search'] ?? '');
$where = "WHERE 1=1";
if ($search != "") {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (dept_name LIKE '%$search_escaped%' 
                 OR building LIKE '%$search_escaped%'
                 OR floor LIKE '%$search_escaped%')";
}

/* ---------- PAGINATION ---------- */
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/* ---------- MAIN QUERY ---------- */
$query = "SELECT l.*, 
          (SELECT COUNT(*) FROM assets WHERE location_id = l.location_id) as asset_count
          FROM locations l 
          $where 
          ORDER BY l.location_id DESC 
          LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Locations Management</h2>
        <a href="<?= ROUTE_LOCATIONS_ADD ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Location
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Department</th>
                            <th>Floor</th>
                            <th class="text-center">Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['location_id'] ?></td>

                                    <td class="fw-bold">
                                        <a href="locations_details.php?id=<?= $row['location_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['dept_name']) ?>
                                        </a>
                                    </td>

                                    <td><?= htmlspecialchars($row['floor']) ?></td>

                                    <td class="text-center">
                                        <span class="badge bg-info rounded-pill"><?= $row['asset_count'] ?></span>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="locations_details.php?id=<?= $row['location_id'] ?>" class="btn btn-info">View</a>
                                            <a href="locations_edit.php?id=<?= $row['location_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="locations_delete.php?id=<?= $row['location_id'] ?>"
                                                onclick="return confirm('Are you sure? This will affect assets assigned to this location.')"
                                                class="btn btn-danger">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No locations found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM locations $where");
    $total_rows = mysqli_fetch_assoc($total_query)['total'];
    $total_pages = ceil($total_rows / $limit);
    ?>
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include("../../includes/footer.php"); ?>