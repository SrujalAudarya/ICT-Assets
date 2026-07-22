<?php
// Define the base URL of the application
// Adjust this if your project folder name is different
const BASE_URL = 'http://172.21.54.7:8080/AMS/';

// Define common routes
const ROUTE_DASHBOARD = BASE_URL . 'dashboard.php';
const ROUTE_LOGOUT = BASE_URL . 'auth/logout.php';

// Master Activities Base Path
const MASTER_PATH = BASE_URL . 'master_activities/';

// Vendors
const ROUTE_VENDORS = MASTER_PATH . 'vendors/vendors_list.php';
const ROUTE_VENDORS_ADD = MASTER_PATH . 'vendors/vendors_add.php';
const ROUTE_VENDORS_EDIT = MASTER_PATH . 'vendors/vendors_edit.php';
const ROUTE_VENDORS_DELETE = MASTER_PATH . 'vendors/vendors_delete.php';

// PC Details
const ROUTE_PC_DETAILS = MASTER_PATH . 'pc_details/pc_list.php';
const ROUTE_PC_ADD = MASTER_PATH . 'pc_details/pc_add.php';
const ROUTE_PC_EDIT = MASTER_PATH . 'pc_details/pc_edit.php';
const ROUTE_PC_DELETE = MASTER_PATH . 'pc_details/pc_delete.php';

// Categories
const ROUTE_CATEGORIES = MASTER_PATH . 'categories/categories_list.php';
const ROUTE_CATEGORIES_ADD = MASTER_PATH . 'categories/categories_add.php';
const ROUTE_CATEGORIES_EDIT = MASTER_PATH . 'categories/categories_edit.php';
const ROUTE_CATEGORIES_DELETE = MASTER_PATH . 'categories/categories_delete.php';

// Models
const ROUTE_MODELS = MASTER_PATH . 'models/models_list.php';
const ROUTE_MODELS_ADD = MASTER_PATH . 'models/models_add.php';
const ROUTE_MODELS_EDIT = MASTER_PATH . 'models/models_edit.php';
const ROUTE_MODELS_DELETE = MASTER_PATH . 'models/models_delete.php';

// Locations
const ROUTE_LOCATIONS = MASTER_PATH . 'locations/locations_list.php';
const ROUTE_LOCATIONS_ADD = MASTER_PATH . 'locations/locations_add.php';
const ROUTE_LOCATIONS_EDIT = MASTER_PATH . 'locations/locations_edit.php';
const ROUTE_LOCATIONS_DELETE = MASTER_PATH . 'locations/locations_delete.php';

// Status
const ROUTE_STATUS = MASTER_PATH . 'status/status_list.php';
const ROUTE_STATUS_ADD = MASTER_PATH . 'status/status_add.php';
const ROUTE_STATUS_EDIT = MASTER_PATH . 'status/status_edit.php';
const ROUTE_STATUS_DELETE = MASTER_PATH . 'status/status_delete.php';

// Assets
const ROUTE_ASSETS = MASTER_PATH . 'assets/assets_list.php';
const ROUTE_ASSETS_ADD = MASTER_PATH . 'assets/assets_add.php';
const ROUTE_ASSETS_EDIT = MASTER_PATH . 'assets/assets_edit.php';
const ROUTE_ASSETS_DELETE = MASTER_PATH . 'assets/assets_delete.php';

// Assignments
const ROUTE_ASSIGNMENTS = MASTER_PATH . 'assignments/assignments_list.php';
const ROUTE_ASSIGN_ASSET = MASTER_PATH . 'assignments/assign_asset.php';

// Users
const ROUTE_USERS = MASTER_PATH . 'users/users_list.php';
const ROUTE_USERS_ADD = MASTER_PATH . 'users/users_add.php';
const ROUTE_USERS_EDIT = MASTER_PATH . 'users/users_edit.php';
const ROUTE_USERS_DELETE = MASTER_PATH . 'users/users_delete.php';
