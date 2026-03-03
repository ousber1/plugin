            </div><!-- /.admin-content -->
        </div><!-- /.admin-main -->
    </div><!-- /.admin-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('show');
    }
    // Fermer sidebar au clic en dehors (mobile)
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('adminSidebar');
        if (window.innerWidth < 992 && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && !e.target.closest('[onclick*="toggleSidebar"]')) {
                sidebar.classList.remove('show');
            }
        }
    });
    </script>
</body>
</html>
