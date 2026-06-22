<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- FILTER HANDLING ---------- */
$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';

$where = "WHERE 1=1";

if($search != ""){
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%')";
}

if($role != ""){
    $role_escaped = mysqli_real_escape_string($conn, $role);
    $where .= " AND u.role = '$role_escaped'";
}

/* ---------- PAGINATION LOGIC ---------- */
$limit = 10;
$page = $_GET['page'] ?? 1;
$page = max(1, (int)$page);
$offset = ($page - 1) * $limit;

/* ---------- MAIN QUERY ---------- */
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM asset_assignments WHERE user_id = u.user_id AND returned_date IS NULL) as active_assets
          FROM users u 
          $where 
          ORDER BY u.user_id ASC 
          LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Users Management</h2>
        <a href="users_add.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Add New User
        </a>
    </div>

    <!-- SEARCH & FILTER FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name or email..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="Admin" <?= ($role == 'Admin') ? 'selected' : '' ?>>Admin</option>
                        <option value="ICT Staff" <?= ($role == 'ICT Staff') ? 'selected' : '' ?>>ICT Staff</option>
                        <option value="Employee" <?= ($role == 'Employee') ? 'selected' : '' ?>>Employee</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 w-100">Filter</button>
                    <a href="users_list.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th class="text-center">Active Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['user_id'] ?></td>
                                    <td class="fw-bold">
                                        <a href="users_view.php?id=<?= $row['user_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td>
                                        <?php 
                                        $role_class = 'bg-secondary';
                                        if($row['role'] == 'Admin') $role_class = 'bg-danger';
                                        if($row['role'] == 'ICT Staff') $role_class = 'bg-primary';
                                        if($row['role'] == 'Employee') $role_class = 'bg-success';
                                        ?>
                                        <span class="badge <?= $role_class ?>"><?= $row['role'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info rounded-pill"><?= $row['active_assets'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="users_view.php?id=<?= $row['user_id'] ?>" class="btn btn-info">View</a>
                                            <a href="users_edit.php?id=<?= $row['user_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="users_delete.php?id=<?= $row['user_id'] ?>"
                                               onclick="return confirm('Are you sure you want to delete this user?')" 
                                               class="btn btn-danger">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No users found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users u $where");
    $total_rows = mysqli_fetch_assoc($total_query)['total'];
    $total_pages = ceil($total_rows / $limit);
    ?>
    <?php if($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <li class="page-item <?= ($i==$page)?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include("../../includes/footer.php"); ?>
