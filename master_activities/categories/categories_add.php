<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$error = "";

if(isset($_POST['save'])) {
    $name = mysqli_real_escape_string($conn, $_POST['category_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    
    $parent_id_sql = ($parent_id > 0) ? "'$parent_id'" : "NULL";

    $query = "INSERT INTO asset_categories (category_name, parent_id, description) VALUES ('$name', $parent_id_sql, '$description')";
    
    if(mysqli_query($conn, $query)) {
        header("Location: " . ROUTE_CATEGORIES);
        exit();
    } else {
        $error = mysqli_error($conn);
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add New Category</h4>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <!-- PARENT CATEGORY SELECTION -->
                <div class="mb-3">
                    <label class="form-label">Parent Category</label>
                    <select name="parent_id" class="form-select">
                        <option value="">-- None (Make Main Category) --</option>
                        <?php
                        $cat_res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY category_name ASC");
                        while($cat = mysqli_fetch_assoc($cat_res)) {
                            echo "<option value='{$cat['category_id']}'>{$cat['category_name']}</option>";
                        }
                        ?>
                    </select>
                    <small class="text-muted">Select a parent category if this is a sub-category (e.g. choose PC for Desktop PC).</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="category_name" class="form-control" placeholder="e.g. Laptops, Printers, Servers" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Brief description of this category..."></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" name="save" class="btn btn-success px-4">Save Category</button>
                    <a href="<?= ROUTE_CATEGORIES ?>" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>