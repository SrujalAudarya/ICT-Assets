<?php
global $conn;

include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

// Filter & Search values
$search = trim($_GET['search'] ?? '');
$filter_category = $_GET['category_id'] ?? '';
$filter_vendor = $_GET['vendor_id'] ?? '';

// Base query joining categories (parent and sub) and vendors
$query = "SELECT m.*, 
          c.category_name, 
          pc.category_name AS parent_category_name,
          v.vendor_name,
          COUNT(a.asset_id) AS total_assets
          FROM asset_models m
          LEFT JOIN asset_categories c ON m.category_id = c.category_id
          LEFT JOIN asset_categories pc ON c.parent_id = pc.category_id
          LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
          LEFT JOIN assets a ON m.model_id = a.model_id";

// Dynamic WHERE clause building
$conditions = [];

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(m.model_name LIKE '%$search_esc%' 
                      OR c.category_name LIKE '%$search_esc%' 
                      OR pc.category_name LIKE '%$search_esc%' 
                      OR v.vendor_name LIKE '%$search_esc%' 
                      OR m.make_name LIKE '%$search_esc%' 
                      OR m.contract_no LIKE '%$search_esc%' 
                      OR m.financial_year LIKE '%$search_esc%')";
}

if (!empty($filter_category)) {
    $cat_esc = mysqli_real_escape_string($conn, $filter_category);
    // Filter by either subcategory or parent category
    $conditions[] = "(m.category_id = '$cat_esc' OR c.parent_id = '$cat_esc')";
}

if (!empty($filter_vendor)) {
    $ven_esc = mysqli_real_escape_string($conn, $filter_vendor);
    $conditions[] = "m.vendor_id = '$ven_esc'";
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " GROUP BY m.model_id ORDER BY m.model_id ASC";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Asset Models List</h2>
        <a href="<?= ROUTE_MODELS_ADD ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Model
        </a>
    </div>

    <!-- SEARCH & FILTER BAR -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">Search Keyword</label>
                    <input type="text" name="search" class="form-control" placeholder="Model, Make, Contract No..." value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Filter by Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php
                        $cat_res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY category_name ASC");
                        while($cat = mysqli_fetch_assoc($cat_res)) {
                            $sel = ($filter_category == $cat['category_id']) ? 'selected' : '';
                            echo "<option value='{$cat['category_id']}' $sel>{$cat['category_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Filter by Vendor</label>
                    <select name="vendor_id" class="form-select">
                        <option value="">All Vendors</option>
                        <?php
                        $ven_res = mysqli_query($conn, "SELECT * FROM vendors ORDER BY vendor_name ASC");
                        while($ven = mysqli_fetch_assoc($ven_res)) {
                            $sel = ($filter_vendor == $ven['vendor_id']) ? 'selected' : '';
                            echo "<option value='{$ven['vendor_id']}' $sel>{$ven['vendor_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="models_list.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- MODELS TABLE -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="white-space: nowrap;">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Logo</th>
                            <th>Model Name</th>
                            <th>Category</th>
                            <th>Make</th>
                            <th>Pur. Date</th>
                            <th>Exp. Date</th>
                            <th class="text-center">Qynt</th>
                            <th class="text-center">Total Assets</th>
                            <th class="text-center">Supply Order</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php $sr = 1; while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <!-- ID / Serial Number -->
                                <td><?= $sr++ ?></td>

                                <!-- Logo / Image -->
                                <td class="text-center" style="width: 50px;">
                                    <?php if (!empty($row['model_image'])): ?>
                                        <img src="../../<?= htmlspecialchars($row['model_image']) ?>" alt="Logo" style="height: 35px; width: 35px; object-fit: contain;" class="rounded border p-1 bg-white">
                                    <?php else: ?>
                                        <div class="bg-light border text-muted d-flex align-items-center justify-content-center rounded mx-auto" style="height: 35px; width: 35px;">
                                            <i class="bi bi-image fs-6"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Model Name -->
                                <td class="fw-bold">
                                    <a href="models_details.php?id=<?= $row['model_id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($row['model_name']) ?>
                                    </a>
                                </td>

                                <!-- Category Format (Parent » Sub) -->
                                <td>
                                    <?php 
                                        if (!empty($row['parent_category_name'])) {
                                            echo htmlspecialchars($row['parent_category_name']) . ' &raquo; ' . htmlspecialchars($row['category_name']);
                                        } else {
                                            echo htmlspecialchars($row['category_name'] ?? '-');
                                        }
                                    ?>
                                </td>

                                <!-- Make -->
                                <td><?= htmlspecialchars($row['make_name'] ?? '-') ?></td>
                                
                                <!-- Purchase Date -->
                                <td><?= !empty($row['purchase_date']) ? date('d M Y', strtotime($row['purchase_date'])) : '<span class="text-muted">-</span>' ?></td>
                                
                                <!-- Expiry Date -->
                                <td>
                                    <?php 
                                        if (!empty($row['expiry_date'])) {
                                            $is_expired = strtotime($row['expiry_date']) < time();
                                            $color = $is_expired ? 'text-danger fw-bold' : 'text-success fw-bold';
                                            echo "<span class='$color'>" . date('d M Y', strtotime($row['expiry_date'])) . "</span>";
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                    ?>
                                </td>

                                <!-- Quantity (Qynt) -->
                                <td class="text-center fw-bold"><?= (int)($row['quantity'] ?? 0) ?></td>

                                <!-- Total Assets Linked -->
                                <td class="text-center">
                                    <span class="badge bg-info text-dark">
                                        <?= $row['total_assets'] ?>
                                    </span>
                                </td>

                                <!-- Supply Order Direct View Button -->
                                <td class="text-center">
                                    <?php if (!empty($row['supply_order_doc'])): ?>
                                        <a href="../../<?= htmlspecialchars($row['supply_order_doc']) ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="View Supply Order Document">
                                            <i class="bi bi-file-earmark-pdf-fill"></i> View Doc
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/A</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions: View, Edit, Delete -->
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="models_details.php?id=<?= $row['model_id'] ?>" class="btn btn-info" title="View Details">View</a>
                                        <a href="<?= ROUTE_MODELS_EDIT ?>?id=<?= $row['model_id'] ?>" class="btn btn-warning" title="Edit Model">Edit</a>
                                        <a href="<?= ROUTE_MODELS_DELETE ?>?id=<?= $row['model_id'] ?>" 
                                           onclick="return confirm('Are you sure you want to delete this model? All associated linkages will be affected.')" 
                                           class="btn btn-danger" title="Delete Model">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">No asset models found matching your criteria.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>