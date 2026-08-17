<?php
ob_start(); // Prevent redirect issues
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

// Safely capture the ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: " . ROUTE_CATEGORIES . "?msg=error&err_detail=" . urlencode("Invalid Category ID"));
    exit();
}

try {
    // 1. CHECK FOR SUB-CATEGORIES
    // (Prevent deleting a Main Category if it has Sub Categories inside it)
    $sub_query = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE parent_id = $id LIMIT 1");
    if ($sub_query && mysqli_num_rows($sub_query) > 0) {
        header("Location: " . ROUTE_CATEGORIES . "?msg=error&err_detail=" . urlencode("Cannot delete: Please delete or reassign its Sub-Categories first."));
        exit();
    }

    // 2. CHECK FOR LINKED MODELS
    // (This is exactly what caused your Fatal Error)
    $models_query = mysqli_query($conn, "SELECT model_id FROM asset_models WHERE category_id = $id LIMIT 1");
    if ($models_query && mysqli_num_rows($models_query) > 0) {
        header("Location: " . ROUTE_CATEGORIES . "?msg=error&err_detail=" . urlencode("Cannot delete: There are Device Models using this Category."));
        exit();
    }

    // 3. CHECK FOR LINKED ASSETS
    $assets_query = mysqli_query($conn, "SELECT asset_id FROM assets WHERE category_id = $id LIMIT 1");
    if ($assets_query && mysqli_num_rows($assets_query) > 0) {
        header("Location: " . ROUTE_CATEGORIES . "?msg=error&err_detail=" . urlencode("Cannot delete: There are actual Assets currently assigned to this Category."));
        exit();
    }

    // 4. SAFE TO DELETE
    if (mysqli_query($conn, "DELETE FROM asset_categories WHERE category_id = $id")) {
        header("Location: " . ROUTE_CATEGORIES . "?msg=deleted");
        exit();
    } else {
        throw new Exception("Database Link Error: " . mysqli_error($conn));
    }

} catch (mysqli_sql_exception $e) {
    // Catch any other hidden database links securely
    header("Location: " . ROUTE_CATEGORIES . "?msg=error&err_detail=" . urlencode("Database constraint error: This category is actively in use."));
    exit();
} catch (Exception $e) {
    header("Location: " . ROUTE_CATEGORIES . "?msg=error&err_detail=" . urlencode($e->getMessage()));
    exit();
}
?>