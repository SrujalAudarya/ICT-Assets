<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

mysqli_query($conn, "DELETE FROM locations WHERE location_id=$id");

header("Location: " . ROUTE_LOCATIONS);
exit();
?>