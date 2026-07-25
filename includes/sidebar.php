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

// Pages belonging to the Asset Details Submenu
$asset_details_pages = ['pc_list.php', 'pc_add.php', 'pc_edit.php'];
$category_param = $_GET['category'] ?? '';
$is_details_open = in_array($current_page, $asset_details_pages) || !empty($category_param);
?>

<style>
/* Smooth chevron icon rotation */
.nav-link .bi-chevron-down {
    transition: transform 0.3s ease-in-out;
}
.nav-link.collapsed .bi-chevron-down {
    transform: rotate(-90deg);
}
/* Ensure smooth transition for submenu */
#assetDetailsSubmenu {
    transition: all 0.3s ease-in-out;
}
</style>

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

        <!-- ASSET DETAILS DROPDOWN MODULE -->
        <div class="nav-item">
            <!-- Removed data-bs-toggle to prevent Bootstrap conflicts -->
            <button type="button" 
                    id="assetToggleBtn"
                    class="nav-link w-100 bg-transparent border-0 d-flex align-items-center justify-content-between <?= $is_details_open ? '' : 'collapsed' ?>" 
                    aria-expanded="<?= $is_details_open ? 'true' : 'false' ?>">
                <span>
                    <i class="bi bi-box-seam me-1"></i> Asset Details
                </span>
                <i class="bi bi-chevron-down small ms-auto"></i>
            </button>

            <!-- Submenu Container -->
            <div class="collapse <?= $is_details_open ? 'show' : '' ?> ps-3" id="assetDetailsSubmenu">
                <div class="nav flex-column gap-1 pt-1 border-start ms-2 ps-2">
                    <a href="<?= ROUTE_PC_DETAILS ?>" class="nav-link py-1 text-secondary <?= isActive(['pc_list.php', 'pc_add.php', 'pc_edit.php']) ?>">
                        <i class="bi bi-pc-display me-2"></i> PC Details
                    </a>
                    <a href="<?= ROUTE_ASSETS ?>?category=Printer" class="nav-link py-1 text-secondary <?= ($category_param === 'Printer') ? 'fw-bold text-primary' : '' ?>">
                        <i class="bi bi-printer me-2"></i> Printer Details
                    </a>
                    <a href="<?= ROUTE_ASSETS ?>?category=Xerox" class="nav-link py-1 text-secondary <?= ($category_param === 'Xerox') ? 'fw-bold text-primary' : '' ?>">
                        <i class="bi bi-journal-text me-2"></i> Xerox Details
                    </a>
                    <a href="<?= ROUTE_ASSETS ?>?category=Other" class="nav-link py-1 text-secondary <?= ($category_param === 'Other') ? 'fw-bold text-primary' : '' ?>">
                        <i class="bi bi-cpu me-2"></i> Other Details
                    </a>
                </div>
            </div>
        </div>

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
            <i class="bi bi-boxes"></i> Asset Models
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

<script>
// Foolproof Vanilla JavaScript Toggle (Bypasses Bootstrap Conflicts)
document.addEventListener("DOMContentLoaded", function () {
    const toggleBtn = document.getElementById("assetToggleBtn");
    const submenu = document.getElementById("assetDetailsSubmenu");

    if (toggleBtn && submenu) {
        toggleBtn.addEventListener("click", function (e) {
            e.preventDefault();
            
            // Check if the menu currently has the 'show' class
            const isShowing = submenu.classList.contains("show");

            if (isShowing) {
                // If open, close it
                submenu.classList.remove("show");
                toggleBtn.classList.add("collapsed");
                toggleBtn.setAttribute("aria-expanded", "false");
            } else {
                // If closed, open it
                submenu.classList.add("show");
                toggleBtn.classList.remove("collapsed");
                toggleBtn.setAttribute("aria-expanded", "true");
            }
        });
    }
});
</script>