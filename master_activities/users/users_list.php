<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================
   IMPORT FROM CSV
   CSV format expected:
   Full Name,Phone Number,System Role
   ========================= */
if (isset($_POST['import_excel'])) {
    if (!empty($_FILES['excel_file']['name'])) {

        $fileExt = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));

        // Only CSV allowed
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

                    // Skip header row
                    if ($rowCount == 1) {
                        continue;
                    }

                    /*
                      CSV expected columns:
                      [0] Full Name
                      [1] Phone Number
                      [2] System Role
                    */

                    $name  = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                    $phone = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                    $role  = mysqli_real_escape_string($conn, trim($row[2] ?? ''));

                    // Skip completely empty rows
                    if (empty($name) && empty($phone) && empty($role)) {
                        continue;
                    }

                    // Name and role required
                    if (empty($name)) {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Name is missing";
                        continue;
                    }

                    if (empty($role)) {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Role is missing";
                        continue;
                    }

                    // Valid roles
                    $allowedRoles = ['Employee', 'Admin', 'ICT Staff', 'Server'];
                    if (!in_array($role, $allowedRoles)) {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: Invalid role ($role)";
                        continue;
                    }

                    // Optional phone cleanup
                    if ($phone === '') {
                        $phoneValue = "NULL";
                    } else {
                        $phoneValue = "'" . $phone . "'";
                    }

                    // Since CSV does NOT contain email and password,
                    // both will be stored as NULL
                    $query = "INSERT INTO users (name, email, phone, role, password)
                              VALUES (
                                  '$name',
                                  NULL,
                                  $phoneValue,
                                  '$role',
                                  NULL
                              )";

                    if (mysqli_query($conn, $query)) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $failedRows[] = "Row $rowCount: " . mysqli_error($conn);
                    }
                }

                fclose($handle);

                $success_msg = "Import completed successfully. Added: $successCount, Failed: $failCount";

                if (!empty($failedRows)) {
                    $error = implode("<br>", $failedRows);
                }
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

$where = "WHERE 1=1";

if ($search != "") {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.name LIKE '%$search_escaped%' OR u.email LIKE '%$search_escaped%' OR u.phone LIKE '%$search_escaped%')";
}

if ($role != "") {
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
          (SELECT COUNT(*) 
           FROM asset_assignments 
           WHERE user_id = u.user_id AND returned_date IS NULL) AS active_assets
          FROM users u
          $where
          ORDER BY u.user_id ASC
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Users Management</h2>

        <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
            <a href="users_add.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Add New User
            </a>

            <button type="button" class="btn btn-success" onclick="document.getElementById('excelFile').click();">
                <i class="bi bi-file-earmark-arrow-up"></i> Add From CSV
            </button>

            <input type="file" name="excel_file" id="excelFile" accept=".csv" style="display:none;" onchange="document.getElementById('importBtn').click();">
            <button type="submit" name="import_excel" id="importBtn" style="display:none;">Import</button>
        </form>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success">
            <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <!-- SEARCH & FILTER FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                        placeholder="Search by name, email or phone..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-3">
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

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 w-100">Filter</button>
                    <a href="users_list.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- USERS TABLE -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th class="text-center">Active Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php $sr = $offset + 1; ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <!-- Serial Number shown instead of actual user_id -->
                                    <td><?= $sr++ ?></td>

                                    <td class="fw-bold">
                                        <a href="users_view.php?id=<?= $row['user_id'] ?>" class="text-decoration-none text-uppercase">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </a>
                                    </td>

                                    <td><?= !empty($row['email']) ? htmlspecialchars($row['email']) : '-' ?></td>
                                    <td><?= !empty($row['phone']) ? htmlspecialchars($row['phone']) : '-' ?></td>

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

                                    <td class="text-center">
                                        <span class="badge bg-info rounded-pill"><?= $row['active_assets'] ?></span>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="users_view.php?id=<?= $row['user_id'] ?>" class="btn btn-info">View</a>
                                            <a href="users_edit.php?id=<?= $row['user_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="users_delete.php?id=<?= $row['user_id'] ?>"
                                                class="btn btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this user?')">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No users found matching your criteria.
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
    $total_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users u $where");
    $total_rows = mysqli_fetch_assoc($total_query)['total'];
    $total_pages = ceil($total_rows / $limit);
    ?>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link"
                            href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <div class="mt-3">
        <small class="text-muted">
            CSV format for import: <b>Full Name, Phone Number, System Role</b><br>
            <span class="text-danger">Note:</span> Upload only <b>.csv</b> file. Excel <b>.xlsx</b> file will not import in this version.
        </small>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>