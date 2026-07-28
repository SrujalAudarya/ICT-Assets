<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

// Strictly cast ID to integer for security
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* =========================================================
   FETCH USER DETAILS
   ========================================================= */
$user_query = "SELECT u.*, l.dept_name, l.floor 
               FROM users u 
               LEFT JOIN locations l ON u.location_id = l.location_id 
               WHERE u.user_id = '$id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    include("../../includes/header.php");
    include("../../includes/sidebar.php");
    echo "<div class='container mt-4'><div class='alert alert-danger'>User not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* =========================================================
   FETCH ASSIGNED ASSETS (ACTIVE & HISTORY)
   ========================================================= */
$assets_query = "SELECT asn.*, a.asset_id, a.asset_name, a.serial_number, c.category_name 
                 FROM asset_assignments asn
                 JOIN assets a ON asn.asset_id = a.asset_id
                 LEFT JOIN asset_categories c ON a.category_id = c.category_id
                 WHERE asn.user_id = '$id'
                 ORDER BY asn.returned_date ASC, asn.assigned_date DESC";
$assets_result = mysqli_query($conn, $assets_query);
$asset_count = mysqli_num_rows($assets_result);

// Determine Status Badge Color
$status_color = ($user['status'] == 'Active') ? 'success' : 'danger';

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-0 text-dark"><i class="bi bi-person-badge text-primary me-2"></i> Employee Profile</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="users_list.php" class="text-decoration-none">Users Directory</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($user['name']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="user_edit.php?id=<?= $user['user_id'] ?>" class="btn btn-warning fw-bold text-dark shadow-sm">
                <i class="bi bi-pencil-square me-1"></i> Edit Profile
            </a>
            <a href="users_list.php" class="btn btn-secondary fw-bold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: USER INFO CARD -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card shadow-sm border-0 border-top border-<?= $status_color ?> border-4 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Account Details</span>
                    <span class="badge bg-<?= $status_color ?> px-3 py-1 rounded-pill">
                        <?= htmlspecialchars($user['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <!-- Identity Header -->
                    <div class="mb-4 pb-3 border-bottom">
                        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                        <div class="text-primary fw-semibold fs-6 mb-2"><?= htmlspecialchars($user['role']) ?></div>
                    </div>

                    <!-- Contact & Department Info -->
                    <div class="mb-3">
                        <label class="text-muted small fw-bold d-block mb-1"><i class="bi bi-envelope me-1 text-primary"></i> Email Address</label>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['email'] ?: 'Not Provided') ?></div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small fw-bold d-block mb-1"><i class="bi bi-telephone me-1 text-primary"></i> Phone Number</label>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['phone'] ?: 'Not Provided') ?></div>
                    </div>

                    <div class="mb-0">
                        <label class="text-muted small fw-bold d-block mb-1"><i class="bi bi-geo-alt me-1 text-primary"></i> Location / Department</label>
                        <div class="fw-bold text-dark">
                            <?php 
                                $loc = $user['dept_name'] ?? 'Not Assigned';
                                if (!empty($user['floor'])) $loc .= " (" . $user['floor'] . ")";
                                echo htmlspecialchars($loc);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: ASSIGNED ASSETS TABLE -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow-sm border-0 border-top border-primary border-4 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 text-dark"><i class="bi bi-pc-display me-2 text-primary"></i> Assigned Hardware & Devices</h5>
                    <span class="badge bg-primary px-2 py-1"><?= $asset_count ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Asset Details</th>
                                    <th>Category</th>
                                    <th>Assignment Period</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($asset_count > 0): ?>
                                    <?php while ($ast = mysqli_fetch_assoc($assets_result)): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($ast['asset_name']) ?></div>
                                                <code class="small text-secondary"><?= htmlspecialchars($ast['serial_number']) ?></code>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ast['category_name'] ?? 'N/A') ?></span></td>
                                            <td>
                                                <div class="small fw-bold text-dark"><?= date('d M Y', strtotime($ast['assigned_date'])) ?></div>
                                                <?php if (!empty($ast['returned_date'])): ?>
                                                    <small class="text-muted">Returned: <?= date('d M Y', strtotime($ast['returned_date'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (empty($ast['returned_date'])): ?>
                                                    <span class="badge bg-success px-2 py-1">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border px-2 py-1">Returned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (empty($ast['returned_date'])): ?>
                                                    <a href="../assets/asset_details.php?id=<?= $ast['asset_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Asset Specification">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                            <p class="mb-0">No IT assets have ever been assigned to this user.</p>
                                        </td>
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

<?php 
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>