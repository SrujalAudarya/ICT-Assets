<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to check active state
function isActive($pages) {
    global $current_page;
    if (is_array($pages)) {
        return in_array($current_page, $pages) ? 'active' : '';
    }
    return ($current_page === $pages) ? 'active' : '';
}
?>
<div class="sidebar shadow-sm" id="sidebar">
    <div class="nav flex-column py-3">
        <a href="<?= ROUTE_DASHBOARD ?>" class="nav-link <?= isActive(['dashboard.php', 'index.php']) ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="master-menu-header">Asset Management</div>
        
        <a href="<?= ROUTE_ASSETS ?>" class="nav-link <?= isActive(['assets_list.php', 'assets_add.php', 'assets_edit.php', 'asset_details.php']) ?>">
            <i class="bi bi-laptop"></i> Assets Inventory
        </a>
        
        <a href="<?= ROUTE_ASSIGNMENTS ?>" class="nav-link <?= isActive(['assignments_list.php', 'assign_asset.php', 'assignment_details.php']) ?>">
            <i class="bi bi-person-check"></i> Assignments
        </a>
        
        
        
        <a href="<?= ROUTE_DOCUMENTS ?>" class="nav-link <?= isActive(['documents_list.php', 'documents_upload.php', 'document_details.php']) ?>">
            <i class="bi bi-file-earmark-text"></i> Documents
        </a>

        <div class="master-menu-header">Master Data</div>
        
        <a href="<?= ROUTE_USERS ?>" class="nav-link <?= isActive(['users_list.php', 'users_add.php', 'users_edit.php', 'users_view.php']) ?>">
            <i class="bi bi-people"></i> Users & Staff
        </a>
        
        <a href="<?= ROUTE_VENDORS ?>" class="nav-link <?= isActive(['vendors_list.php', 'vendors_add.php', 'vendors_edit.php', 'vendors_details.php']) ?>">
            <i class="bi bi-shop"></i> Vendors
        </a>
        
        <a href="<?= ROUTE_CATEGORIES ?>" class="nav-link <?= isActive(['categories_list.php', 'categories_add.php', 'categories_edit.php', 'categories_details.php']) ?>">
            <i class="bi bi-tags"></i> Categories
        </a>
        
        <a href="<?= ROUTE_MODELS ?>" class="nav-link <?= isActive(['models_list.php', 'models_add.php', 'models_edit.php', 'models_details.php']) ?>">
            <i class="bi bi-box-seam"></i> Asset Models
        </a>
        
        <a href="<?= ROUTE_LOCATIONS ?>" class="nav-link <?= isActive(['locations_list.php', 'locations_add.php', 'locations_edit.php', 'locations_details.php']) ?>">
            <i class="bi bi-geo-alt"></i> Locations
        </a>
        
        <a href="<?= ROUTE_STATUS ?>" class="nav-link <?= isActive(['status_list.php', 'status_add.php', 'status_edit.php', 'status_details.php']) ?>">
            <i class="bi bi-activity"></i> Status Definitions
        </a>
    </div>
</div>

<div class="main-content" id="main-content">
    <div class="content-body">
