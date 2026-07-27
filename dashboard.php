<?php
global $conn;
include("includes/auth.php"); // Adjust path if your dashboard is in a different folder
include("config/db.php");

// =======================================================
// 1. FETCH KPI STATISTICS
// =======================================================
$total_assets_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets");
$total_assets = mysqli_fetch_assoc($total_assets_query)['total'] ?? 0;

$assigned_assets_query = mysqli_query($conn, "SELECT COUNT(DISTINCT asset_id) as total FROM asset_assignments WHERE returned_date IS NULL");
$assigned_assets = mysqli_fetch_assoc($assigned_assets_query)['total'] ?? 0;

$total_categories_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM asset_categories");
$total_categories = mysqli_fetch_assoc($total_categories_query)['total'] ?? 0;

// Assuming Status ID 1 is usually 'Available' or 'In Stock' - Adjust if needed
// Or just calculate available as (Total - Assigned) for a quick metric
$available_assets = $total_assets - $assigned_assets; 

// =======================================================
// 2. FETCH CHART DATA: ASSETS BY STATUS
// =======================================================
$status_chart_data = [];
$status_query = "SELECT s.status_name, COUNT(a.asset_id) as count 
                 FROM asset_status s 
                 LEFT JOIN assets a ON s.status_id = a.status_id 
                 GROUP BY s.status_id";
$status_res = mysqli_query($conn, $status_query);
while($row = mysqli_fetch_assoc($status_res)) {
    $status_chart_data['labels'][] = $row['status_name'];
    $status_chart_data['counts'][] = $row['count'];
}

// =======================================================
// 3. FETCH CHART DATA: ASSETS BY CATEGORY
// =======================================================
$cat_chart_data = [];
$cat_query = "SELECT c.category_name, COUNT(a.asset_id) as count 
              FROM asset_categories c 
              JOIN assets a ON c.category_id = a.category_id 
              GROUP BY c.category_id 
              ORDER BY count DESC LIMIT 5"; // Top 5 categories
$cat_res = mysqli_query($conn, $cat_query);
while($row = mysqli_fetch_assoc($cat_res)) {
    $cat_chart_data['labels'][] = $row['category_name'];
    $cat_chart_data['counts'][] = $row['count'];
}

// =======================================================
// 4. FETCH RECENT ACTIVITY (Last 5 Assets & Assignments)
// =======================================================
$recent_assets = mysqli_query($conn, "SELECT a.asset_name, a.serial_number, c.category_name 
                                      FROM assets a 
                                      LEFT JOIN asset_categories c ON a.category_id = c.category_id 
                                      ORDER BY a.asset_id DESC LIMIT 5");

$recent_assignments = mysqli_query($conn, "SELECT a.asset_name, u.name as user_name, asn.assigned_date 
                                           FROM asset_assignments asn 
                                           JOIN assets a ON asn.asset_id = a.asset_id 
                                           JOIN users u ON asn.user_id = u.user_id 
                                           ORDER BY asn.assignment_id DESC LIMIT 5");

include("includes/header.php");
include("includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Overview Dashboard</h2>
        <span class="text-muted"><i class="bi bi-calendar-event"></i> <?= date('l, d M Y') ?></span>
    </div>

    <!-- KPI CARDS ROW -->
    <div class="row mb-4">
        <!-- Total Assets -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Assets</div>
                            <div class="h3 mb-0 fw-bold text-dark"><?= $total_assets ?></div>
                        </div>
                        <div class="fs-1 text-gray-300">
                            <i class="bi bi-pc-display text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Assets -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Assigned Assets</div>
                            <div class="h3 mb-0 fw-bold text-dark"><?= $assigned_assets ?></div>
                        </div>
                        <div class="fs-1 text-gray-300">
                            <i class="bi bi-person-workspace text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Assets -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Available to Assign</div>
                            <div class="h3 mb-0 fw-bold text-dark"><?= $available_assets ?></div>
                        </div>
                        <div class="fs-1 text-gray-300">
                            <i class="bi bi-check-circle text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Categories -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Asset Categories</div>
                            <div class="h3 mb-0 fw-bold text-dark"><?= $total_categories ?></div>
                        </div>
                        <div class="fs-1 text-gray-300">
                            <i class="bi bi-tags text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="row mb-4">
        <!-- Status Doughnut Chart -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i> Assets by Status
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <canvas id="statusChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Category Bar Chart -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-bar-chart-line-fill text-primary me-2"></i> Top 5 Asset Categories
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT ACTIVITY ROW -->
    <div class="row">
        <!-- Recently Added Assets -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-plus-square me-2"></i> Recently Added Assets</span>
                    <a href="master_activities/assets/assets_list.php" class="btn btn-sm btn-light py-0">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset Name</th>
                                    <th>Serial No</th>
                                    <th>Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($recent_assets) > 0): ?>
                                    <?php while($ast = mysqli_fetch_assoc($recent_assets)): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($ast['asset_name']) ?></td>
                                            <td><code><?= htmlspecialchars($ast['serial_number']) ?></code></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ast['category_name'] ?? 'N/A') ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No assets added yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Assignments -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-check me-2"></i> Recent Assignments</span>
                    <a href="master_activities/assignments/assignments_list.php" class="btn btn-sm btn-light py-0 text-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset</th>
                                    <th>Assigned To</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($recent_assignments) > 0): ?>
                                    <?php while($asn = mysqli_fetch_assoc($recent_assignments)): ?>
                                        <tr>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($asn['asset_name']) ?></td>
                                            <td><i class="bi bi-person-fill text-muted me-1"></i> <?= htmlspecialchars($asn['user_name']) ?></td>
                                            <td><?= date('d M Y', strtotime($asn['assigned_date'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No recent assignments.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js for Graphics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Status Doughnut Chart Setup
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($status_chart_data['labels'] ?? []) ?>,
            datasets: [{
                data: <?= json_encode($status_chart_data['counts'] ?? []) ?>,
                backgroundColor: ['#198754', '#dc3545', '#ffc107', '#0dcaf0', '#0d6efd', '#6c757d'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });

    // 2. Category Bar Chart Setup
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($cat_chart_data['labels'] ?? []) ?>,
            datasets: [{
                label: 'Number of Assets',
                data: <?= json_encode($cat_chart_data['counts'] ?? []) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

<?php include("includes/footer.php"); ?>