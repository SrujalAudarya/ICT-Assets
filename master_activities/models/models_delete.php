<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

mysqli_query($conn, "DELETE FROM asset_models WHERE model_id=$id");

header("Location: " . ROUTE_MODELS);
exit();
?>