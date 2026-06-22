<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- MAIN QUERY ---------- */
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM assets WHERE category_id = c.category_id) as asset_count
          FROM asset_categories c 
          ORDER BY c.category_id ASc";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Asset Categories</h2>
        <a href="<?= ROUTE_CATEGORIES_ADD ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Category
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th class="text-center">Total Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['category_id'] ?></td>
                                    <td class="fw-bold">
                                        <a href="categories_details.php?id=<?= $row['category_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['category_name']) ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info rounded-pill"><?= $row['asset_count'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="categories_details.php?id=<?= $row['category_id'] ?>" class="btn btn-info">View</a>
                                            <a href="<?= ROUTE_CATEGORIES_EDIT ?>?id=<?= $row['category_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="<?= ROUTE_CATEGORIES_DELETE ?>?id=<?= $row['category_id'] ?>"
                                               onclick="return confirm('Are you sure? This will affect all assets in this category.')" 
                                               class="btn btn-danger">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No categories found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>
