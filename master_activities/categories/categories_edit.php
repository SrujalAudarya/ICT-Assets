<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '0';
$error = "";

if(isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['category_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    
    $parent_id_sql = ($parent_id > 0) ? "'$parent_id'" : "NULL";

    $query = "UPDATE asset_categories SET 
              category_name='$name', 
              parent_id=$parent_id_sql,
              description='$description' 
              WHERE category_id=$id";

    if(mysqli_query($conn, $query)) {
        header("Location: " . ROUTE_CATEGORIES);
        exit();
    } else {
        $error = mysqli_error($conn);
    }
}

$result = mysqli_query($conn, "SELECT * FROM asset_categories WHERE category_id=$id");
$row = mysqli_fetch_assoc($result);

if (!$row) {
    include("../../includes/header.php");
    include("../../includes/sidebar.php");
    echo "<div class='container mt-4'><div class='alert alert-danger'>Category not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0">Edit Category</h4>
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
                        // Fetch only main categories and exclude itself to prevent recursive errors
                        $cat_res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE (parent_id IS NULL OR parent_id = 0) AND category_id != $id ORDER BY category_name ASC");
                        while($cat = mysqli_fetch_assoc($cat_res)) {
                            $selected = ($cat['category_id'] == $row['parent_id']) ? "selected" : "";
                            echo "<option value='{$cat['category_id']}' $selected>{$cat['category_name']}</option>";
                        }
                        ?>
                    </select>
                    <small class="text-muted">Select a parent category if this should be a sub-category.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="category_name" value="<?= htmlspecialchars($row['category_name']) ?>" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($row['description']) ?></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" name="update" class="btn btn-primary px-4">Update Category</button>
                    <a href="<?= ROUTE_CATEGORIES ?>" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>