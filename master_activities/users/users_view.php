<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

$user_id = mysqli_real_escape_string($conn, $_GET['id'] ?? 0);

$user_query = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>User not found.</div></div>";
    include("../../includes/footer.php");
    exit;
}

$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$where = "WHERE aa.user_id = $user_id";

if($category != "") { $where .= " AND a.category_id = '" . mysqli_real_escape_string($conn, $category) . "'"; }
if($status != "") { $where .= " AND a.status_id = '" . mysqli_real_escape_string($conn, $status) . "'"; }

$current_assets_query = "SELECT a.*, aa.assigned_date, aa.remarks, ac.category_name, s.status_name, m.model_name
                         FROM asset_assignments aa
                         JOIN assets a ON aa.asset_id = a.asset_id
                         LEFT JOIN asset_categories ac ON a.category_id = ac.category_id
                         LEFT JOIN asset_status s ON a.status_id = s.status_id
                         LEFT JOIN asset_models m ON a.model_id = m.model_id
                         $where AND aa.returned_date IS NULL";
$current_assets_result = mysqli_query($conn, $current_assets_query);
$active_count = mysqli_num_rows($current_assets_result);

$history_query = "SELECT a.*, aa.assigned_date, aa.returned_date, aa.remarks, ac.category_name, s.status_name, m.model_name
                  FROM asset_assignments aa
                  JOIN assets a ON aa.asset_id = a.asset_id
                  LEFT JOIN asset_categories ac ON a.category_id = ac.category_id
                  LEFT JOIN asset_status s ON a.status_id = s.status_id
                  LEFT JOIN asset_models m ON a.model_id = m.model_id
                  $where AND aa.returned_date IS NOT NULL
                  ORDER BY aa.returned_date DESC";
$history_result = mysqli_query($conn, $history_query);
$history_count = mysqli_num_rows($history_result);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>User Profile: <?= htmlspecialchars($user['name']) ?></h2>
        <div>
            <a href="users_edit.php?id=<?= $user_id ?>" class="btn btn-warning">Edit User</a>
            <a href="users_list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Status</label>
                        <span class="badge <?= ($user['status'] == 'Active') ? 'bg-success' : 'bg-danger' ?> fs-6">
                            <?= htmlspecialchars($user['status']) ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Full Name</label>
                        <span class="h5 fw-bold"><?= htmlspecialchars($user['name']) ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Email Address</label>
                        <span><?= htmlspecialchars($user['email'] ?: 'N/A') ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Role</label>
                        <span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mb-4 border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Active Assets</h6>
                    <h2 class="display-4 fw-bold text-info"><?= $active_count ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-8">
             <!-- Assets Section (same structure as original) -->
             <div class="card mb-4 shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Currently Assigned Assets</h5>
                    <span class="badge bg-dark"><?= $active_count ?> Active</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset Name</th>
                                    <th>Category</th>
                                    <th>Assigned Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($active_count > 0): while ($row = mysqli_fetch_assoc($current_assets_result)): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['asset_name']) ?></td>
                                        <td><?= htmlspecialchars($row['category_name']) ?></td>
                                        <td><?= date('d M Y', strtotime($row['assigned_date'])) ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">No active assets found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Asset History -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Asset Assignment History</h5>
                    <span class="badge bg-dark"><?= $history_count ?> Returned</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset Name</th>
                                    <th>Assigned</th>
                                    <th>Returned</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history_count > 0): while ($row = mysqli_fetch_assoc($history_result)): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['asset_name']) ?></td>
                                        <td><?= date('d M Y', strtotime($row['assigned_date'])) ?></td>
                                        <td><?= date('d M Y', strtotime($row['returned_date'])) ?></td>
                                        <td><small><?= htmlspecialchars($row['remarks']) ?></small></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No history found.</td></tr>
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