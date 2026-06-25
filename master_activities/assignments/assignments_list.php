<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- FILTER HANDLING ---------- */
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'active'; // active, returned, all

$where = "WHERE 1=1";

if ($status_filter == 'active') {
    $where .= " AND aa.returned_date IS NULL";
} elseif ($status_filter == 'returned') {
    $where .= " AND aa.returned_date IS NOT NULL";
}

if ($search != "") {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (
        a.asset_name LIKE '%$search_escaped%' 
        OR u.name LIKE '%$search_escaped%' 
        OR a.serial_number LIKE '%$search_escaped%'
    )";
}

/* ---------- PAGINATION LOGIC ---------- */
$limit = 10;
$page = $_GET['page'] ?? 1;
$page = max(1, (int)$page);
$offset = ($page - 1) * $limit;

/* ---------- MAIN QUERY ---------- */
$query = "
SELECT 
    aa.assignment_id,
    aa.asset_id,
    aa.user_id,
    aa.assigned_date,
    aa.returned_date,
    aa.remarks,
    a.asset_name,
    a.serial_number,
    u.name AS user_name,
    u.email AS user_email
FROM asset_assignments aa
JOIN assets a ON aa.asset_id = a.asset_id
JOIN users u ON aa.user_id = u.user_id
$where
ORDER BY aa.assignment_id ASC
LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Asset Assignments</h2>
        <a href="assign_asset.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Assignment
        </a>
    </div>

    <!-- SEARCH & FILTER FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by asset name, serial or user..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($status_filter == 'active') ? 'selected' : '' ?>>Active Assignments</option>
                        <option value="returned" <?= ($status_filter == 'returned') ? 'selected' : '' ?>>Returned History</option>
                        <option value="all" <?= ($status_filter == 'all') ? 'selected' : '' ?>>All Records</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 w-100">Filter</button>
                    <a href="assignments_list.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ASSIGNMENTS TABLE -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Sr. No.</th>
                            <th>Asset Name</th>
                            <th>Assigned To</th>
                            <th>Assigned Date</th>
                            <th>Returned Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php 
                            $sr = $offset + 1; 
                            while ($row = mysqli_fetch_assoc($result)): 
                            ?>
                                <tr>
                                    <!-- Proper ascending display id -->
                                    <td><?= $sr++ ?></td>

                                    <td>
                                        <a href="../assets/asset_details.php?id=<?= $row['asset_id'] ?>" class="text-decoration-none fw-bold">
                                            <?= htmlspecialchars($row['asset_name']) ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            SN: <?= htmlspecialchars($row['serial_number']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <a href="../users/users_view.php?id=<?= $row['user_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['user_name']) ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            <?= !empty($row['user_email']) ? htmlspecialchars($row['user_email']) : '-' ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= !empty($row['assigned_date']) ? date('d-m-Y', strtotime($row['assigned_date'])) : '-' ?>
                                    </td>

                                    <td>
                                        <?= !empty($row['returned_date']) ? date('d-m-Y', strtotime($row['returned_date'])) : '-' ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if (!empty($row['returned_date'])): ?>
                                            <span class="badge bg-secondary">Returned</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="assignment_details.php?id=<?= $row['assignment_id'] ?>" class="btn btn-info">View</a>

                                            <?php if (empty($row['returned_date'])): ?>
                                                <a href="return_asset.php?id=<?= $row['assignment_id'] ?>"
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Mark this asset as returned?')">
                                                   Return
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No assignment records found.
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
    $count_query = "
        SELECT COUNT(*) AS total
        FROM asset_assignments aa
        JOIN assets a ON aa.asset_id = a.asset_id
        JOIN users u ON aa.user_id = u.user_id
        $where
    ";
    $total_query = mysqli_query($conn, $count_query);
    $total_rows = ($total_query) ? mysqli_fetch_assoc($total_query)['total'] : 0;
    $total_pages = ceil($total_rows / $limit);
    ?>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include("../../includes/footer.php"); ?>