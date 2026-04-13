<?php
/**
 * Footer Template
 * Close main content and page wrapper
 */
?>
            </div><!-- /main-content -->
        </div><!-- /page-content -->
    </div><!-- /page-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Session warning before timeout
        let sessionTimeout;
        function resetSessionTimer() {
            clearTimeout(sessionTimeout);
            sessionTimeout = setTimeout(function() {
                console.log('Session about to expire...');
                // Could show a modal warning here
            }, (30 * 60 - 60) * 1000); // Warn 1 minute before timeout
        }

        // Reset timer on any user interaction
        document.addEventListener('mousemove', resetSessionTimer);
        document.addEventListener('keypress', resetSessionTimer);
        document.addEventListener('click', resetSessionTimer);

        // Initialize timer
        resetSessionTimer();
    </script>
</body>
</html>

<?php
/*
 * © 2026 TambyTech.
 * This source code is proprietary and confidential.
 * Any unauthorized use, copying, modification, distribution, or disclosure is strictly prohibited.
 * All rights reserved.
 */
?>
