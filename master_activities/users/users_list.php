<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================
   IMPORT FROM CSV
   ========================= */
if (isset($_POST['import_excel'])) {
    if (!empty($_FILES['excel_file']['name'])) {
        $fileExt = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));

        if ($fileExt !== 'csv') {
            $error = "Please upload only a CSV file. Excel (.xlsx) file will not work directly.";
        } else {
            $fileName = $_FILES['excel_file']['tmp_name'];
            $handle = fopen($fileName, "r");

            if ($handle !== false) {
                $rowCount = 0;
                $successCount = 0;
                $failCount = 0;
                $failedRows = [];

                while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                    $rowCount++;
                    if ($rowCount == 1) continue;

                    $name  = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                    $phone = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                    $role  = mysqli_real_escape_string($conn, trim($row[2] ?? ''));

                    if (empty($name) && empty($phone) && empty($role)) continue;

                    if (empty($name)) { $failCount++; $failedRows[] = "Row $rowCount: Name is missing"; continue; }
                    if (empty($role)) { $failCount++; $failedRows[] = "Row $rowCount: Role is missing"; continue; }

                    $allowedRoles = ['Employee', 'Admin', 'ICT Staff', 'Server'];
                    if (!in_array($role, $allowedRoles)) {
                        $failCount++; $failedRows[] = "Row $rowCount: Invalid role ($role)"; continue;
                    }

                    $phoneValue = ($phone === '') ? "NULL" : "'" . $phone . "'";

                    // Insert with default Active status
                    $query = "INSERT INTO users (name, email, phone, role, password, status)
                              VALUES ('$name', NULL, $phoneValue, '$role', NULL, 'Active')";

                    if (mysqli_query($conn, $query)) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: " . mysqli_error($conn);
                    }
                }
                fclose($handle);
                $success_msg = "Import completed successfully. Added: $successCount, Failed: $failCount";
                if (!empty($failedRows)) $error = implode("<br>", $failedRows);
            } else {
                $error = "Unable to open the uploaded CSV file.";
            }
        }
    } else {
        $error = "Please select a CSV file.";
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- FILTER HANDLING ---------- */
$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$status_filter = $_GET['status_filter'] ?? 'Active'; // Default to showing Active users
$asset_filter = $_GET['asset_filter'] ?? ''; // New Asset Filter

$where = "WHERE 1=1";

if ($search != "") {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%' OR u.phone LIKE '%$search_escaped%')";
}
if ($role != "") {
    $role_escaped = mysqli_real_escape_string($conn, $role);
    $where .= " AND u.role = '$role_escaped'";
}
if ($status_filter != "") {
    $status_escaped = mysqli_real_escape_string($conn, $status_filter);
    $where .= " AND u.status = '$status_escaped'";
}
if ($asset_filter == 'multiple') {
    // Filter to only show users who have MORE THAN 1 active asset assigned
    $where .= " AND (SELECT COUNT(*) FROM asset_assignments WHERE user_id = u.user_id AND returned_date IS NULL) > 1";
}

/* ---------- PAGINATION LOGIC ---------- */
$limit = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// 1. Get total records matching the filter to calculate total pages
$count_query = "SELECT COUNT(*) AS total FROM users u $where";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $limit);

// 2. Build the query string for pagination links (keeps your search/filters active when changing pages)
$query_params = $_GET;
unset($query_params['page']); // Remove page from current URL parameters
$query_string = http_build_query($query_params);
$query_string = $query_string ? '&' . $query_string : '';

/* ---------- MAIN QUERY ---------- */
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM asset_assignments WHERE user_id = u.user_id AND returned_date IS NULL) AS active_assets
          FROM users u $where ORDER BY u.user_id ASC LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Users Management</h2>
        <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
            <a href="users_add.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add New User</a>
            <button type="button" class="btn btn-success" onclick="document.getElementById('excelFile').click();">
                <i class="bi bi-file-earmark-arrow-up"></i> Add From CSV
            </button>
            <input type="file" name="excel_file" id="excelFile" accept=".csv" style="display:none;" onchange="document.getElementById('importBtn').click();">
            <button type="submit" name="import_excel" id="importBtn" style="display:none;">Import</button>
        </form>
    </div>

    <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if (isset($success_msg)): ?><div class="alert alert-success"><?= $success_msg ?></div><?php endif; ?>
    
    <!-- Status Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'status_updated'): ?>
            <div class="alert alert-success">User status updated successfully! Assets auto-returned if deactivated.</div>
        <?php elseif ($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-success">User permanently deleted and assets returned!</div>
        <?php elseif ($_GET['msg'] == 'error'): ?>
            <div class="alert alert-danger">An error occurred while processing your request.</div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- SEARCH & FILTER FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="Admin" <?= ($role == 'Admin') ? 'selected' : '' ?>>Admin</option>
                        <option value="ICT Staff" <?= ($role == 'ICT Staff') ? 'selected' : '' ?>>ICT Staff</option>
                        <option value="Employee" <?= ($role == 'Employee') ? 'selected' : '' ?>>Employee</option>
                        <option value="Server" <?= ($role == 'Server') ? 'selected' : '' ?>>Server</option>
                        <option value="DRC Room" <?= ($role == 'DRC Room') ? 'selected' : '' ?>>DRC Room</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status_filter" class="form-select">
                        <option value="">All Status</option>
                        <option value="Active" <?= ($status_filter == 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= ($status_filter == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Asset Filter</label>
                    <select name="asset_filter" class="form-select">
                        <option value="">All Users</option>
                        <option value="multiple" <?= ($asset_filter == 'multiple') ? 'selected' : '' ?>>Multiple Assets (>1)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 w-100">Filter</button>
                    <a href="users_list.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- USERS TABLE -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-center">Active Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php $sr = $offset + 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $sr++ ?></td>
                                    <td class="fw-bold">
                                        <a href="users_view.php?id=<?= $row['user_id'] ?>" class="text-decoration-none text-uppercase">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </a>
                                    </td>
                                    <td><?= !empty($row['email']) ? htmlspecialchars($row['email']) : '-' ?></td>
                                    <td>
                                        <?php
                                        $role_class = 'bg-secondary';
                                        if ($row['role'] == 'Admin') $role_class = 'bg-danger';
                                        if ($row['role'] == 'ICT Staff') $role_class = 'bg-primary';
                                        if ($row['role'] == 'Employee') $role_class = 'bg-success';
                                        if ($row['role'] == 'Server') $role_class = 'bg-dark';
                                        if ($row['role'] == 'DRC Room') $role_class = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?= $role_class ?>"><?= htmlspecialchars($row['role']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= ($row['status'] == 'Active') ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= htmlspecialchars($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info rounded-pill"><?= $row['active_assets'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="users_view.php?id=<?= $row['user_id'] ?>" class="btn btn-info">View</a>
                                            <a href="users_edit.php?id=<?= $row['user_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="users_delete.php?id=<?= $row['user_id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to permanently delete this user? All their active assets will be auto-returned.')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- PAGINATION CONTROLS -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4 pb-4">
            <ul class="pagination justify-content-center">
                <!-- Previous Button -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= max(1, $page - 1) ?><?= $query_string ?>">Previous</a>
                </li>
                
                <!-- Page Numbers -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $query_string ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <!-- Next Button -->
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?><?= $query_string ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
    
</div>
<?php include("../../includes/footer.php"); ?>