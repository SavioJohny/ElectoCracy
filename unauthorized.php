<?php
session_start();
require_once 'config/config.php';

$page_title = 'Access Denied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo APP_NAME; ?> - Access Denied. You don't have permission to access this page.">
    <meta name="keywords" content="access denied, unauthorized, permission, security">
    <meta name="author" content="ElectoCracy Team">
    <meta name="theme-color" content="#457B9D">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $page_title . ' - ' . APP_NAME; ?>">
    <meta property="og:description" content="Access Denied - You don't have permission to access this page">
    <meta property="og:image" content="<?php echo BASE_URL; ?>assets/images/og-image.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?php echo $page_title . ' - ' . APP_NAME; ?>">
    <meta property="twitter:description" content="Access Denied - You don't have permission to access this page">
    
    <title><?php echo $page_title . ' - ' . APP_NAME; ?></title>
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    
    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome 6.5.0 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" 
          integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw==" crossorigin="anonymous">
    
    <!-- Flat 2.0 Theme -->
    <link href="<?php echo BASE_URL; ?>assets/css/flat-2.0-theme.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>assets/images/apple-touch-icon.png">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "<?php echo APP_NAME; ?>",
        "description": "Democratic Election Management System - Access Denied",
        "applicationCategory": "GovernmentApplication",
        "operatingSystem": "Web Browser"
    }
    </script>
    
    <!-- Additional Unauthorized Page Styles -->
    <style>
        .unauthorized-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--light-mint) 0%, var(--light-blue) 100%);
            padding: 2rem 0;
        }
        
        .unauthorized-card {
            max-width: 500px;
            width: 100%;
            margin: 0 1rem;
        }
        
        .unauthorized-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .unauthorized-brand {
            font-size: 2.5rem;
            color: var(--medium-blue);
            margin-bottom: 0.5rem;
        }
        
        .unauthorized-subtitle {
            color: var(--dark-blue);
            font-weight: var(--font-weight-medium);
            opacity: 0.8;
        }
        
        .error-icon {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .btn-group-custom {
            gap: 0.75rem;
        }
        
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading .btn-text {
            opacity: 0;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .unauthorized-brand {
                font-size: 2rem;
            }
            
            .unauthorized-card {
                margin: 0 0.5rem;
            }
            
            .error-icon {
                font-size: 3rem;
            }
            
            .btn-group-custom {
                flex-direction: column;
            }
            
            .btn-group-custom .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="skip-link visually-hidden-focusable">Skip to main content</a>
    
    <!-- Main Unauthorized Container -->
    <div class="unauthorized-container" id="main-content" tabindex="-1">
        <div class="unauthorized-card">
            <!-- Unauthorized Header -->
            <div class="unauthorized-header">
                <div class="unauthorized-brand">
                    <i class="fas fa-vote-yea me-2" aria-hidden="true"></i>
                    <?php echo APP_NAME; ?>
                </div>
                <p class="unauthorized-subtitle">Democratic Election Management System</p>
            </div>
            
            <!-- Unauthorized Card -->
            <div class="card unauthorized-card-body">
                <div class="card-header bg-danger text-white text-center">
                    <h3 class="mb-0">
                        <i class="fas fa-shield-alt me-2" aria-hidden="true"></i>
                        Access Denied
                    </h3>
                </div>
                <div class="card-body text-center">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    </div>
                    
                    <h4 class="mb-3 text-danger">
                        <i class="fas fa-ban me-2" aria-hidden="true"></i>
                        You don't have permission to access this page
                    </h4>
                    
                    <p class="text-muted mb-4">
                        <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
                        This page requires specific permissions that your account doesn't have. 
                        Please contact your administrator if you believe this is an error.
                    </p>
                    
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-lightbulb me-2" aria-hidden="true"></i>
                        <strong>Need help?</strong> Contact your system administrator or try logging in with a different account.
                    </div>
                    
                    <div class="d-flex justify-content-center btn-group-custom">
                        <a href="<?php echo BASE_URL; ?>index.php" 
                           class="btn btn-primary" 
                           data-loading-text="Redirecting..."
                           aria-label="Go to Dashboard">
                            <i class="fas fa-home me-2" aria-hidden="true"></i>
                            <span class="btn-text">Go to Dashboard</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>logout.php" 
                           class="btn btn-outline-secondary"
                           data-loading-text="Logging out..."
                           aria-label="Logout from current session">
                            <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i>
                            <span class="btn-text">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5.3.2 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript for Enhanced UX and Accessibility -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Alt + M to focus main content (skip navigation)
                if (e.altKey && e.key === 'm') {
                    e.preventDefault();
                    const mainContent = document.getElementById('main-content');
                    if (mainContent) {
                        mainContent.focus();
                        mainContent.scrollIntoView({ behavior: 'smooth' });
                    }
                }
                
                // Escape key to focus first button
                if (e.key === 'Escape') {
                    const firstBtn = document.querySelector('.btn-primary');
                    if (firstBtn) {
                        firstBtn.focus();
                    }
                }
            });
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Loading state management for buttons
            const loadingButtons = document.querySelectorAll('[data-loading-text]');
            loadingButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalText = button.innerHTML;
                    const loadingText = button.getAttribute('data-loading-text');
                    
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + loadingText;
                    button.disabled = true;
                    
                    // Re-enable after navigation delay
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 3000);
                });
            });
            
            // Tooltip initialization
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Announce page changes for screen readers
            const pageTitle = document.title;
            if (pageTitle) {
                const announcement = document.createElement('div');
                announcement.setAttribute('aria-live', 'polite');
                announcement.setAttribute('aria-atomic', 'true');
                announcement.className = 'sr-only';
                announcement.textContent = 'Page loaded: ' + pageTitle;
                document.body.appendChild(announcement);
                
                // Remove announcement after screen reader has time to read it
                setTimeout(() => {
                    document.body.removeChild(announcement);
                }, 1000);
            }
            
            // Auto-focus first interactive element
            const firstBtn = document.querySelector('.btn-primary');
            if (firstBtn) {
                setTimeout(() => {
                    firstBtn.focus();
                }, 100);
            }
        });
        
        // Service Worker registration for PWA capabilities (optional)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed');
                    });
            });
        }
    </script>
    
    <!-- Additional JavaScript files -->
    <?php if (file_exists('assets/js/main.js')): ?>
        <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <?php endif; ?>
</body>
</html>
