<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // Start a transaction so if anything fails, it rolls back safely without breaking your database
    mysqli_begin_transaction($conn);

    try {
        // STEP 1: Unassign/Delete all assignments linked to the assets of this model
        mysqli_query($conn, "DELETE FROM asset_assignments WHERE asset_id IN (SELECT asset_id FROM assets WHERE model_id = '$id')");

        // STEP 2: Delete all the imported assets belonging to this model
        mysqli_query($conn, "DELETE FROM assets WHERE model_id = '$id'");

        // STEP 3: Now that the assets are gone, safely delete the model itself
        mysqli_query($conn, "DELETE FROM asset_models WHERE model_id = '$id'");

        // Commit all deletions
        mysqli_commit($conn);

        // Redirect back with a success message
        header("Location: " . ROUTE_MODELS . "?msg=deleted");
        exit();

    } catch (mysqli_sql_exception $exception) {
        // If an error still occurs, roll back the changes and report it
        mysqli_rollback($conn);
        header("Location: " . ROUTE_MODELS . "?msg=error&err_detail=" . urlencode($exception->getMessage()));
        exit();
    }
} else {
    // If no valid ID is passed, return to the models list safely
    header("Location: " . ROUTE_MODELS);
    exit();
}
?>