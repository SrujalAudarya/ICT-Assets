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

$available_assets = $total_assets - $assigned_assets; 

// =======================================================
// 2. DYNAMICALLY FETCH *ONLY MAIN CATEGORIES* FOR THE OVERLAY
// =======================================================
$breakdown_query = "
    SELECT 
        main_cat.category_id, 
        main_cat.category_name, 
        COUNT(DISTINCT a.asset_id) as asset_count
    FROM asset_categories main_cat
    LEFT JOIN asset_categories sub_cat ON sub_cat.parent_id = main_cat.category_id
    JOIN assets a ON (a.category_id = main_cat.category_id OR a.category_id = sub_cat.category_id)
    WHERE (main_cat.parent_id = 0 OR main_cat.parent_id IS NULL)
    GROUP BY main_cat.category_id, main_cat.category_name
    ORDER BY asset_count DESC
";
$breakdown_res = mysqli_query($conn, $breakdown_query);

// Store results in an array
$categories_data = [];
while($cat = mysqli_fetch_assoc($breakdown_res)) {
    $categories_data[] = $cat;
}

// =======================================================
// 3. FETCH CHART DATA: ASSETS BY STATUS
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
// 4. FETCH CHART DATA: ASSETS BY MAIN CATEGORY
// =======================================================
$cat_chart_data = [];
$cat_query = "
    SELECT 
        main_cat.category_name, 
        COUNT(DISTINCT a.asset_id) as count 
    FROM asset_categories main_cat
    LEFT JOIN asset_categories sub_cat ON sub_cat.parent_id = main_cat.category_id
    JOIN assets a ON (a.category_id = main_cat.category_id OR a.category_id = sub_cat.category_id)
    WHERE (main_cat.parent_id = 0 OR main_cat.parent_id IS NULL)
    GROUP BY main_cat.category_id 
    ORDER BY count DESC LIMIT 5
"; 
$cat_res = mysqli_query($conn, $cat_query);
while($row = mysqli_fetch_assoc($cat_res)) {
    $cat_chart_data['labels'][] = $row['category_name'];
    $cat_chart_data['counts'][] = $row['count'];
}

// =======================================================
// 5. FETCH RECENT ACTIVITY
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

<style>
    /* Subtle Hover for KPI Cards */
    .hover-lift {
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }
    .hover-lift:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 15px 30px rgba(13, 110, 253, 0.2) !important;
        border-color: #0d6efd !important;
    }

    /* ==================================================
       THE CRAZY GLASSMORPHISM OVERLAY STYLES
       ================================================== */
    .crazy-overlay {
        position: fixed;
        top: 0; 
        left: 0; 
        width: 100vw; 
        height: 100vh;
        background: rgba(15, 23, 42, 0.65); /* Dark cinematic tint */
        backdrop-filter: blur(25px); /* Massive Blur Effect */
        -webkit-backdrop-filter: blur(25px);
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    .crazy-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    /* Floating Content Container */
    .crazy-content {
        padding: 5rem 2rem;
        transform: translateY(50px) scale(0.95);
        transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
        opacity: 0;
    }
    
    .crazy-overlay.active .crazy-content {
        transform: translateY(0) scale(1);
        opacity: 1;
        transition-delay: 0.1s;
    }

    /* Close Button */
    .crazy-close-btn {
        position: fixed;
        top: 30px; 
        right: 40px;
        width: 60px; 
        height: 60px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex; 
        align-items: center; 
        justify-content: center;
        color: white; 
        font-size: 2rem;
        cursor: pointer;
        transition: all 0.4s ease;
        z-index: 10000;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .crazy-close-btn:hover {
        background: #dc3545;
        transform: rotate(90deg) scale(1.1);
        border-color: #dc3545;
        box-shadow: 0 0 20px rgba(220, 53, 69, 0.6);
    }

    /* Glass Cards */
    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 20px;
        padding: 2rem;
        color: white;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        justify-content: center;
        transition: all 0.4s ease;
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        position: relative;
        overflow: hidden;
        min-height: 180px;
    }
    
    /* Neon Glow Hover Effect */
    .glass-card:hover {
        transform: translateY(-10px);
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
        border-color: rgba(255, 255, 255, 0.4);
        box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 20px rgba(13, 110, 253, 0.3);
        color: white;
    }
    
    .glass-card::before {
        content: '';
        position: absolute;
        top: 0; left: -100%; width: 50%; height: 100%;
        background: linear-gradient(to right, transparent, rgba(255,255,255,0.1), transparent);
        transform: skewX(-25deg);
        transition: 0.5s;
    }
    .glass-card:hover::before {
        left: 200%;
    }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Overview Dashboard</h2>
        <span class="text-muted"><i class="bi bi-calendar-event"></i> <?= date('l, d M Y') ?></span>
    </div>

    <!-- KPI CARDS ROW -->
    <div class="row mb-4">
        
        <!-- Total Assets (TRIGGERS THE CRAZY GLASSMORPHISM OVERLAY) -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100 bg-white hover-lift" 
                 role="button" 
                 onclick="openCrazyOverlay()">
                
                <div class="card-body position-relative overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center position-relative z-1">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1 d-flex align-items-center">
                                Total Assets 
                                <span class="badge bg-primary bg-opacity-10 text-primary ms-2 rounded-pill shadow-sm" style="font-size: 0.65rem;">
                                    View Details <i class="bi bi-magic ms-1"></i>
                                </span>
                            </div>
                            <div class="h2 mb-0 fw-bolder text-dark"><?= $total_assets ?></div>
                        </div>
                        <div class="fs-1 text-primary opacity-25">
                            <i class="bi bi-layers-fill"></i>
                        </div>
                    </div>
                    <!-- Background decorative shape -->
                    <i class="bi bi-pc-display position-absolute opacity-10" style="font-size: 8rem; right: -20px; bottom: -20px;"></i>
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
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold py-3 border-bottom-0">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i> Assets by Status
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <canvas id="statusChart" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Category Bar Chart -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white fw-bold py-3 border-bottom-0">
                    <i class="bi bi-bar-chart-line-fill text-primary me-2"></i> Top 5 Main Categories
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
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center border-0">
                    <span><i class="bi bi-plus-square me-2"></i> Recently Added</span>
                    <a href="master_activities/assets/assets_list.php" class="btn btn-sm btn-light bg-opacity-25 text-white border-0 py-0 px-3">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Asset Name</th>
                                    <th>Serial No</th>
                                    <th>Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($recent_assets) > 0): ?>
                                    <?php while($ast = mysqli_fetch_assoc($recent_assets)): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($ast['asset_name']) ?></td>
                                            <td><code class="bg-light px-2 py-1 rounded border"><?= htmlspecialchars($ast['serial_number']) ?></code></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($ast['category_name'] ?? 'N/A') ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No assets added yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Assignments -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-primary text-white fw-bold py-3 d-flex justify-content-between align-items-center border-0">
                    <span><i class="bi bi-person-check me-2"></i> Recent Assignments</span>
                    <a href="master_activities/assignments/assignments_list.php" class="btn btn-sm btn-light bg-opacity-25 text-white border-0 py-0 px-3">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Asset</th>
                                    <th>Assigned To</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($recent_assignments) > 0): ?>
                                    <?php while($asn = mysqli_fetch_assoc($recent_assignments)): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($asn['asset_name']) ?></td>
                                            <td><div class="d-flex align-items-center"><div class="bg-secondary bg-opacity-10 rounded-circle p-1 me-2"><i class="bi bi-person-fill text-secondary"></i></div> <?= htmlspecialchars($asn['user_name']) ?></div></td>
                                            <td class="text-muted"><?= date('d M Y', strtotime($asn['assigned_date'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">No recent assignments.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- CRAZY FULLSCREEN GLASSMORPHISM OVERLAY -->
<!-- ======================================================= -->
<div id="crazyOverlay" class="crazy-overlay">
    
    <!-- Giant Close Button -->
    <div class="crazy-close-btn" onclick="closeCrazyOverlay()">
        <i class="bi bi-x"></i>
    </div>
    
    <div class="container crazy-content">
        <!-- Floating Header -->
        <div class="text-center mb-5">
            <h1 class="text-white fw-bolder display-4" style="text-shadow: 0 4px 20px rgba(0,0,0,0.5); letter-spacing: -1px;">
                <i class="bi bi-layers-fill text-info me-3"></i>Inventory Types
            </h1>
            <p class="text-white text-opacity-75 fs-5">A comprehensive breakdown of your <strong><?= $total_assets ?></strong> active assets.</p>
        </div>

        <div class="row justify-content-center g-4">
            <?php if(count($categories_data) > 0): ?>
                <?php foreach($categories_data as $cat): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <a href="master_activities/categories/categories_details.php?id=<?= $cat['category_id'] ?>" class="glass-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="bg-white bg-opacity-10 rounded-circle d-flex justify-content-center align-items-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-folder2-open fs-3 text-info"></i>
                                </div>
                                <h2 class="fw-bolder mb-0"><?= $cat['asset_count'] ?></h2>
                            </div>
                            
                            <h5 class="fw-bold mb-0 text-truncate mt-3" title="<?= htmlspecialchars($cat['category_name']) ?>">
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </h5>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center text-white mt-5">
                    <i class="bi bi-inboxes display-1 opacity-50 mb-3"></i>
                    <h2>No Categories Found</h2>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-5">
            <a href="master_activities/assets/assets_list.php" class="btn btn-lg btn-outline-info rounded-pill px-5 border-2 shadow-lg" style="backdrop-filter: blur(5px);">
                View Full Hardware Database
            </a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
// Toggle Overlay Functions
function openCrazyOverlay() {
    document.getElementById('crazyOverlay').classList.add('active');
    document.body.style.overflow = 'hidden'; // Locks the background scrolling
}

function closeCrazyOverlay() {
    document.getElementById('crazyOverlay').classList.remove('active');
    document.body.style.overflow = 'auto'; // Unlocks background
}

// Pressing "Escape" also closes the crazy UI!
document.addEventListener('keydown', function(event){
    if(event.key === "Escape"){
        closeCrazyOverlay();
    }
});

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
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
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
                backgroundColor: 'rgba(13, 110, 253, 0.8)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 0,
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { borderDash: [2, 4], color: '#e9ecef' },
                    ticks: { precision: 0 } 
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

<?php include("includes/footer.php"); ?>