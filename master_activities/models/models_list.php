<?php
global $conn;

include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

// Search value
$search = trim($_GET['search'] ?? '');

// Base query
$query = "SELECT m.*, 
          c.category_name, 
          v.vendor_name,
          COUNT(a.asset_id) AS total_assets
          FROM asset_models m
          LEFT JOIN asset_categories c 
                 ON m.category_id = c.category_id
          LEFT JOIN vendors v 
                 ON m.vendor_id = v.vendor_id
          LEFT JOIN assets a 
                 ON m.model_id = a.model_id";

// Apply search filter
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);

    $query .= " WHERE
                m.model_name LIKE '%$search%'
                OR c.category_name LIKE '%$search%'
                OR v.vendor_name LIKE '%$search%'
                OR m.make_name LIKE '%$search%'
                OR m.contract_no LIKE '%$search%'
                OR m.financial_year LIKE '%$search%'";
}

$query .= " GROUP BY m.model_id
            ORDER BY m.model_id ASC";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Asset Models List</h2>

        <a href="<?= ROUTE_MODELS_ADD ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i>
            Add Model
        </a>
    </div>

    <!-- Search Bar -->
    <form method="GET" class="mb-3">
        <div class="row">
            <div class="col-md-6">
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search by Model, Category, Vendor, Make, Contract No..."
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="models_list.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Model Name</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Make</th>
                            <th>Contract No</th>
                            <th>Qty</th>
                            <th>F.Y.</th>
                            <th class="text-center">Total Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php $sr = 1; ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <!-- Serial Number instead of actual model_id -->
                                <td><?= $sr++ ?></td>

                                <td class="fw-bold">
                                    <a href="models_details.php?id=<?= $row['model_id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($row['model_name']) ?>
                                    </a>
                                </td>

                                <td><?= htmlspecialchars($row['category_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['vendor_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['make_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['contract_no'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['quantity'] ?? '0') ?></td>
                                <td><?= htmlspecialchars($row['financial_year'] ?? '') ?></td>

                                <td class="text-center">
                                    <span class="badge bg-info text-dark">
                                        <?= $row['total_assets'] ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="models_details.php?id=<?= $row['model_id'] ?>" class="btn btn-info">View</a>
                                        <a href="<?= ROUTE_MODELS_EDIT ?>?id=<?= $row['model_id'] ?>" class="btn btn-warning">Edit</a>
                                        <a href="<?= ROUTE_MODELS_DELETE ?>?id=<?= $row['model_id'] ?>"
                                           onclick="return confirm('Delete this model?')"
                                           class="btn btn-danger">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                No models found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>

                </table>
            </div>
        </div>
    </div>

</div>

<?php include("../../includes/footer.php"); ?>