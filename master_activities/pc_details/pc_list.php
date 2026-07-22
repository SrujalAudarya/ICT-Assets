<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . "/../../includes/auth.php");
include(__DIR__ . "/../../config/db.php");
include(__DIR__ . "/../../includes/header.php");
include(__DIR__ . "/../../includes/sidebar.php");

// Check DB connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// ✅ TOTAL ASSETS COUNT
$totalQuery = "SELECT COUNT(*) as total FROM pc_details";
$totalResult = mysqli_query($conn, $totalQuery);
$totalRow = mysqli_fetch_assoc($totalResult);
$totalAssets = $totalRow['total'];

// Fetch data
$query = "SELECT
            p.id,
            p.model_id,
            p.supply_order_file,
            m.purchase_date,
            p.expiry_date,
            p.created_at,
            p.quantity AS pc_quantity,

            m.model_name,
            m.quantity AS model_quantity,

            COUNT(a.asset_id) AS total_assets

          FROM pc_details p

          LEFT JOIN asset_models m
                 ON p.model_id = m.model_id

          LEFT JOIN assets a
                 ON m.model_id = a.model_id

          GROUP BY
                p.id,
                p.model_id,
                p.supply_order_file,
                m.purchase_date,
                p.expiry_date,
                p.created_at,
                p.quantity,
                m.model_name,
                m.quantity

          ORDER BY p.id ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}
?>

<div class="container-fluid mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>PC Management</h2>

        <div>
            <!-- Total Assets -->
            <span class="badge bg-success me-3">
                Total Assets: <?= $totalAssets; ?>
            </span>

            <a href="pc_add.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New PC
            </a>
        </div>
    </div>

    <!-- Card -->
    <div class="card shadow-sm">
        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">

                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Qty</th>
                            <th>Total Assets</th>
                            <th>PC Model</th>
                            <th>Supply Order</th>
                            <th>Purchase Date</th>
                            <th>Expiry Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($result) > 0): ?>

                            <?php $count = 1; ?>

                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>

                                    <!-- Serial -->
                                    <td><?= $count++; ?></td>

                                    <!-- ID -->
                                    <td><?= htmlspecialchars($row['model_quantity']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?= $row['total_assets']; ?>
                                        </span>
                                    </td>
                                    <!-- ✅ CLICKABLE MODEL -->
                                    <td class="fw-bold">
                                        <a href="models_details.php?id=<?= $row['id']; ?>"
                                            class="text-decoration-none">
                                            <?= htmlspecialchars($row['model_name']); ?>
                                        </a>
                                    </td>

                                    <!-- PDF -->
                                    <td>
                                        <?php if (!empty($row['supply_order_file'])): ?>
                                            <a href="uploads/<?= urlencode($row['supply_order_file']); ?>"
                                                target="_blank"
                                                class="btn btn-sm btn-info">
                                                View PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Purchase Date -->
                                    <td>
                                        <?= !empty($row['purchase_date'])
                                            ? date("d M Y", strtotime($row['purchase_date']))
                                            : '<span class="text-muted">—</span>'; ?>
                                    </td>

                                    <!-- Expiry -->
                                    <td>
                                        <?= !empty($row['expiry_date'])
                                            ? date("d M Y", strtotime($row['expiry_date']))
                                            : '<span class="text-muted">—</span>'; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">

                                            <a href="pc_edit.php?id=<?= $row['id']; ?>"
                                                class="btn btn-warning">
                                                Edit
                                            </a>

                                            <a href="pc_delete.php?id=<?= $row['id']; ?>"
                                                onclick="return confirm('Delete this record?')"
                                                class="btn btn-danger">
                                                Delete
                                            </a>

                                        </div>
                                    </td>

                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No PC records found.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>

                </table>
            </div>

        </div>
    </div>

</div>

<?php include(__DIR__ . "/../../includes/footer.php"); ?>