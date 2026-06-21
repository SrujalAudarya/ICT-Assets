<?php
global $conn;
include("includes/auth.php");
include("config/db.php");
include("includes/header.php");
include("includes/sidebar.php");

/* ---------- CONFIGURATION ---------- */
$ASSIGN_OVERDUE_DAYS = 30;

/* ---------- OPTIMIZED METRIC QUERIES ---------- */
$metrics_query = mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM assets) as total,
        (SELECT COUNT(*) FROM assets a JOIN asset_status s ON a.status_id=s.status_id WHERE s.status_name='Assigned') as assigned,
        (SELECT COUNT(*) FROM assets a JOIN asset_status s ON a.status_id=s.status_id WHERE s.status_name='Available') as available,
        (SELECT COUNT(*) FROM assets a JOIN asset_status s ON a.status_id=s.status_id WHERE s.status_name='Under Repair') as repair,
        (SELECT SUM(cost) FROM maintenance_records) as total_maint_cost,
        (SELECT COUNT(*) FROM users) as total_users
");
$metrics = mysqli_fetch_assoc($metrics_query);

/* ---------- CHART DATA QUERIES ---------- */
// Assets by Category
$cat_labels = []; $cat_values = [];
$category_data = mysqli_query($conn, "SELECT c.category_name, COUNT(a.asset_id) as total FROM asset_categories c LEFT JOIN assets a ON a.category_id = c.category_id GROUP BY c.category_id ORDER BY total DESC LIMIT 6");
while($row = mysqli_fetch_assoc($category_data)) {
    $cat_labels[] = $row['category_name'];
    $cat_values[] = $row['total'];
}

// Status Distribution
$status_labels = []; $status_values = [];
$status_data = mysqli_query($conn, "SELECT s.status_name, COUNT(a.asset_id) as total FROM asset_status s LEFT JOIN assets a ON a.status_id = s.status_id GROUP BY s.status_id");
while($row = mysqli_fetch_assoc($status_data)) {
    $status_labels[] = $row['status_name'];
    $status_values[] = $row['total'];
}

/* ---------- ALERTS & ACTIVITY ---------- */
// Overdue Assignments
$overdue_assignments = mysqli_query($conn, "SELECT aa.assignment_id, a.asset_name, u.name, aa.assigned_date, DATEDIFF(CURDATE(), aa.assigned_date) AS days_assigned FROM asset_assignments aa JOIN assets a ON aa.asset_id = a.asset_id JOIN users u ON aa.user_id = u.user_id WHERE aa.returned_date IS NULL AND DATEDIFF(CURDATE(), aa.assigned_date) >= $ASSIGN_OVERDUE_DAYS ORDER BY days_assigned DESC LIMIT 5");
$cnt_overdue_assign = mysqli_num_rows($overdue_assignments);

// Recent Assignments
$recent_activity = mysqli_query($conn, "
    SELECT aa.assigned_date, a.asset_name, u.name, 'assigned' as type
    FROM asset_assignments aa
    JOIN assets a ON aa.asset_id = a.asset_id
    JOIN users u ON aa.user_id = u.user_id
    ORDER BY aa.assignment_id DESC LIMIT 5
");

// Warranty Alerts
$warranty_alerts = mysqli_query($conn, "SELECT asset_name, warranty_expiry, DATEDIFF(warranty_expiry, CURDATE()) AS days_left FROM assets WHERE warranty_expiry IS NOT NULL AND DATEDIFF(warranty_expiry, CURDATE()) <= 30 AND DATEDIFF(warranty_expiry, CURDATE()) >= 0 ORDER BY days_left ASC LIMIT 5");
?>

<style>
    .kpi-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        border-radius: 15px;
    }
    .kpi-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    .icon-shape {
        width: 48px;
        height: 48px;
        background-position: center;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .bg-gradient-primary { background: linear-gradient(87deg, #5e72e4 0, #825ee4 100%) !important; }
    .bg-gradient-success { background: linear-gradient(87deg, #2dce89 0, #2dcecc 100%) !important; }
    .bg-gradient-warning { background: linear-gradient(87deg, #fb6340 0, #fbb140 100%) !important; }
    .bg-gradient-info { background: linear-gradient(87deg, #11cdef 0, #1171ef 100%) !important; }
    
    .activity-feed .activity-item {
        padding: 12px 0;
        border-bottom: 1px solid #f1f1f1;
    }
    .activity-feed .activity-item:last-child { border-bottom: none; }
    
    /* Fix for chart height issue */
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
</style>

<div class="container-fluid">
    <!-- Header & Quick Actions -->
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="fw-bold mb-0">Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></p>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <a href="master_activities/assets/assets_add.php" class="btn btn-primary shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add Asset
                </a>
                <a href="master_activities/assignments/assign_asset.php" class="btn btn-dark shadow-sm">
                    <i class="bi bi-person-plus me-1"></i> Assign
                </a>
            </div>
        </div>
    </div>

    <!-- KPI ROW -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h6 class="text-uppercase text-muted mb-1 small fw-bold">Total Inventory</h6>
                            <span class="h3 fw-bold mb-0"><?= number_format($metrics['total']) ?></span>
                        </div>
                        <div class="col-auto">
                            <div class="icon-shape bg-gradient-primary text-white shadow">
                                <i class="bi bi-box-seam fs-4"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-success mr-2"><i class="bi bi-people"></i> <?= $metrics['total_users'] ?></span>
                        <span class="text-nowrap text-muted">Registered Users</span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h6 class="text-uppercase text-muted mb-1 small fw-bold">Available Assets</h6>
                            <span class="h3 fw-bold mb-0 text-success"><?= number_format($metrics['available']) ?></span>
                        </div>
                        <div class="col-auto">
                            <div class="icon-shape bg-gradient-success text-white shadow">
                                <i class="bi bi-check2-all fs-4"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-nowrap text-muted">Ready for assignment</span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h6 class="text-uppercase text-muted mb-1 small fw-bold">Under Repair</h6>
                            <span class="h3 fw-bold mb-0 text-warning"><?= number_format($metrics['repair']) ?></span>
                        </div>
                        <div class="col-auto">
                            <div class="icon-shape bg-gradient-warning text-white shadow">
                                <i class="bi bi-tools fs-4"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-danger mr-2"><i class="bi bi-arrow-down"></i> <?= $cnt_overdue_assign ?></span>
                        <span class="text-nowrap text-muted">Overdue items</span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h6 class="text-uppercase text-muted mb-1 small fw-bold">Maint. Spend</h6>
                            <span class="h3 fw-bold mb-0">₹<?= number_format($metrics['total_maint_cost'] / 1000, 1) ?>k</span>
                        </div>
                        <div class="col-auto">
                            <div class="icon-shape bg-gradient-info text-white shadow">
                                <i class="bi bi-wallet2 fs-4"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-nowrap text-muted">Lifetime maintenance</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Main Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-transparent border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-uppercase small text-muted">Asset Distribution by Category</h6>
                        <a href="master_activities/categories/categories_list.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- Status Doughnut -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-transparent border-0 py-3">
                    <h6 class="mb-0 fw-bold text-uppercase small text-muted">Inventory Status</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Overdue Alerts -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-clock-history me-2"></i>Overdue Assignments</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                                <?php if($cnt_overdue_assign == 0): ?>
                                    <tr><td class="text-center py-4 text-muted">No overdue items</td></tr>
                                <?php else: while($r = mysqli_fetch_assoc($overdue_assignments)): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold small"><?= htmlspecialchars($r['asset_name']) ?></div>
                                            <div class="text-muted x-small"><?= htmlspecialchars($r['name']) ?></div>
                                        </td>
                                        <td class="text-end pe-3">
                                            <span class="badge bg-soft-danger text-danger rounded-pill"><?= $r['days_assigned'] ?>d</span>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="master_activities/assignments/assignments_list.php" class="small text-primary fw-bold text-decoration-none">Manage All <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-lightning-charge me-2"></i>Recent Activity</h6>
                </div>
                <div class="card-body activity-feed px-3">
                    <?php while($act = mysqli_fetch_assoc($recent_activity)): ?>
                        <div class="activity-item">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="bg-light p-2 rounded-circle">
                                        <i class="bi bi-arrow-left-right text-primary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="small fw-bold"><?= htmlspecialchars($act['asset_name']) ?></div>
                                    <div class="text-muted small">Assigned to <?= htmlspecialchars($act['name']) ?></div>
                                    <div class="text-muted x-small mt-1"><?= date('d M, H:i', strtotime($act['assigned_date'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Warranty Alerts -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-shield-exclamation me-2"></i>Warranty Expiry</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                                <?php if(mysqli_num_rows($warranty_alerts) == 0): ?>
                                    <tr><td class="text-center py-4 text-muted">No upcoming expiries</td></tr>
                                <?php else: while($row = mysqli_fetch_assoc($warranty_alerts)): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold small"><?= htmlspecialchars($row['asset_name']) ?></div>
                                            <div class="text-muted x-small">Expires: <?= date('d M Y', strtotime($row['warranty_expiry'])) ?></div>
                                        </td>
                                        <td class="text-end pe-3">
                                            <span class="badge bg-soft-primary text-primary rounded-pill"><?= $row['days_left'] ?>d</span>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .x-small { font-size: 0.75rem; }
    .bg-soft-danger { background-color: #fee2e2; }
    .bg-soft-primary { background-color: #e0e7ff; }
</style>

<script>
// Category Chart
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($cat_labels) ?>,
        datasets: [{
            label: 'Assets',
            data: <?= json_encode($cat_values) ?>,
            backgroundColor: '#5e72e4',
            borderRadius: 8,
            barThickness: 25
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { borderDash: [2], drawBorder: false },
                ticks: { stepSize: 1 }
            },
            x: { grid: { display: false } }
        }
    }
});

// Status Chart
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($status_labels) ?>,
        datasets: [{
            data: <?= json_encode($status_values) ?>,
            backgroundColor: ['#2dce89', '#f5365c', '#fb6340', '#5e72e4', '#8898aa', '#11cdef'],
            borderWidth: 5,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 11 } } }
        },
        cutout: '80%'
    }
});
</script>

<?php include("includes/footer.php"); ?>
