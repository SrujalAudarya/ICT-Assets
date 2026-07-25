<?php
global $conn;

include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

// Search value
$search = trim($_GET['search'] ?? '');
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Asset Categories</h2>
        <a href="categories_add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Category
        </a>
    </div>

    <!-- Search Bar -->
    <form method="GET" class="mb-3">
        <div class="row">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Search category..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="categories_list.php" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Category Name</th>
                            <th class="text-center">Total Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Build search condition if search query exists
                    $search_sql = "";
                    if (!empty($search)) {
                        $search_esc = mysqli_real_escape_string($conn, $search);
                        $search_sql = "WHERE category_name LIKE '%$search_esc%'";
                    }

                    // 1. Fetch Main (Parent) Categories
                    $main_query = "SELECT * FROM asset_categories $search_sql " . (empty($search_sql) ? "WHERE parent_id IS NULL OR parent_id = 0" : "") . " ORDER BY category_name ASC";
                    $main_result = mysqli_query($conn, $main_query);

                    if (mysqli_num_rows($main_result) > 0):
                        $sr = 1;
                        while($main = mysqli_fetch_assoc($main_result)):
                            // Calculate total assets for main category + its subcategories
                            $main_id = $main['category_id'];
                            $count_query = "SELECT COUNT(a.asset_id) as total FROM assets a 
                                            JOIN asset_categories c ON a.category_id = c.category_id 
                                            WHERE c.category_id = '$main_id' OR c.parent_id = '$main_id'";
                            $count_res = mysqli_fetch_assoc(mysqli_query($conn, $count_query));
                            $main_total_assets = $count_res['total'] ?? 0;
                    ?>
                            <!-- MAIN CATEGORY ROW -->
                            <tr class="table-secondary fw-bold">
                                <td><?= $sr++ ?></td>
                                <td>
                                    <i class="bi bi-folder-fill text-warning me-2"></i>
                                    <?= htmlspecialchars($main['category_name']) ?>
                                    <span class="badge bg-primary ms-2 font-monospace">Main Category</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-dark"><?= $main_total_assets ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="categories_details.php?id=<?= $main['category_id'] ?>" class="btn btn-info">View</a>
                                        <a href="categories_edit.php?id=<?= $main['category_id'] ?>" class="btn btn-warning">Edit</a>
                                        <a href="categories_delete.php?id=<?= $main['category_id'] ?>" onclick="return confirm('Delete this category?')" class="btn btn-danger">Delete</a>
                                    </div>
                                </td>
                            </tr>

                            <?php
                            // 2. Fetch Sub-Categories belonging to this Main Category
                            $sub_query = "SELECT sub.*, 
                                          (SELECT COUNT(*) FROM assets a WHERE a.category_id = sub.category_id) AS sub_total 
                                          FROM asset_categories sub 
                                          WHERE sub.parent_id = '$main_id' 
                                          ORDER BY sub.category_name ASC";
                            $sub_result = mysqli_query($conn, $sub_query);

                            if ($sub_result && mysqli_num_rows($sub_result) > 0):
                                while($sub = mysqli_fetch_assoc($sub_result)):
                            ?>
                                    <!-- SUB CATEGORY ROW -->
                                    <tr>
                                        <td>-</td>
                                        <td class="ps-5">
                                            <i class="bi bi-arrow-return-right text-muted me-2"></i>
                                            <?= htmlspecialchars($sub['category_name']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark"><?= $sub['sub_total'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="categories_details.php?id=<?= $sub['category_id'] ?>" class="btn btn-info">View</a>
                                                <a href="categories_edit.php?id=<?= $sub['category_id'] ?>" class="btn btn-warning">Edit</a>
                                                <a href="categories_delete.php?id=<?= $sub['category_id'] ?>" onclick="return confirm('Delete this sub-category?')" class="btn btn-danger">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                            <?php 
                                endwhile; 
                            endif;
                        endwhile;
                    else: 
                    ?>
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