<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

$id = $_GET['id'];

mysqli_query($conn, "DELETE FROM locations WHERE location_id=$id");

// header("Location: locations_list.php");
