    </div> <!-- End Content Body -->

    <footer class="bg-white border-top py-3 mt-auto">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between small">
                <div class="text-muted">&copy; <?= date('Y') ?> ICT Asset Management System</div>
                <div>
                    <a href="#" class="text-decoration-none text-muted me-3">Privacy Policy</a>
                    <a href="#" class="text-decoration-none text-muted">Terms & Conditions</a>
                </div>
            </div>
        </div>
    </footer>
</div> <!-- End Main Content -->

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Bootstrap 5 JavaScript Bundle -->
<script src="../../js/bootstrap.bundle.min.js"></script>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Auto-close sidebar on mobile when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('[onclick="toggleSidebar()"]');
    
    if (window.innerWidth <= 992 && 
        sidebar && 
        !sidebar.contains(event.target) && 
        toggleBtn &&
        !toggleBtn.contains(event.target) && 
        sidebar.classList.contains('show')) {
        sidebar.classList.remove('show');
    }
});
</script>

</body>
</html>
