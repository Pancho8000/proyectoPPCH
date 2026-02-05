<?php if (!defined('SECURE_ACCESS')) die('Direct access not permitted'); ?>
            </div>
        </div>
        <!-- /#page-content-wrapper -->
    </div>
    <!-- /#wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var toggleButton = document.getElementById("menu-toggle");
        var sidebar = document.getElementById("sidebar-wrapper");

        toggleButton.onclick = function () {
            if (sidebar.style.marginLeft === "-250px") {
                sidebar.style.marginLeft = "0";
            } else {
                sidebar.style.marginLeft = "-250px";
            }
        };
        
        // Check screen size on load
        function checkScreenSize() {
            if (window.innerWidth <= 768) {
                sidebar.style.marginLeft = "-250px";
            } else {
                sidebar.style.marginLeft = "0";
            }
        }
        
        // Initial check
        checkScreenSize();
        
        // Check on resize
        window.addEventListener('resize', checkScreenSize);
    </script>
</body>
</html>
