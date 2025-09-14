<?php
session_start();

// Check if user was logged out by back button
if (isset($_SESSION['logged_out_by_back_button'])) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

// Prevent caching of this page
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$page_title = 'Admin Dashboard';

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Management Panel</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="students.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-graduation-cap fa-2x mb-2"></i><br>
                            Students
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="staff.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-user-tie fa-2x mb-2"></i><br>
                            Staff & Admins
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="departments.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-building fa-2x mb-2"></i><br>
                            Departments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Kill session ONLY on back button press
window.addEventListener('load', function() {
    // Push state and listen for popstate (back button only)
    window.history.pushState(null, "", window.location.href);
    window.addEventListener('popstate', function() {
        // Back button pressed - kill session and redirect
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../kill_session.php', false);
        xhr.send();

        // Force redirect to login
        window.location.href = '../login.php';
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
