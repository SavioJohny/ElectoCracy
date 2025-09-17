<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ElectoCracy - Democratic Election Management System for transparent and secure elections">
    <meta name="keywords" content="election, democracy, voting, management, system">
    <meta name="author" content="ElectoCracy">
    <meta name="theme-color" content="#457B9D">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?>">
    <meta property="og:description" content="Democratic Election Management System">
    <meta property="og:image" content="<?php echo BASE_URL; ?>assets/images/og-image.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?>">
    <meta property="twitter:description" content="Democratic Election Management System">
    
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
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
        "description": "Democratic Election Management System",
        "applicationCategory": "GovernmentApplication",
        "operatingSystem": "Web Browser"
    }
    </script>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="skip-link visually-hidden-focusable">Skip to main content</a>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg shadow-sm" role="navigation" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>" 
               aria-label="<?php echo APP_NAME; ?> - Home">
                <i class="fas fa-vote-yea me-2" aria-hidden="true"></i>
                <span><?php echo APP_NAME; ?></span>
            </a>
            
            <?php if (isAuthenticated()): ?>
                <!-- Mobile Profile and Logout Links - Always visible on mobile -->
                <div class="d-flex d-lg-none align-items-center gap-2 me-2">
                    <a href="<?php echo BASE_URL; ?>profile.php" 
                       class="btn btn-sm btn-outline-primary d-flex align-items-center"
                       aria-label="Profile">
                        <i class="fas fa-user me-1" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">Profile</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>logout.php" 
                       class="btn btn-sm btn-outline-danger d-flex align-items-center"
                       aria-label="Logout">
                        <i class="fas fa-sign-out-alt me-1" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">Logout</span>
                    </a>
                </div>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <!-- User Menu -->
                    <ul class="navbar-nav ms-auto" role="menubar">
                        <li class="nav-item dropdown" role="none">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                               id="navbarDropdown" role="button" data-bs-toggle="dropdown" 
                               aria-expanded="false" aria-haspopup="true" aria-label="User menu">
                                <i class="fas fa-user-circle me-2" aria-hidden="true"></i>
                                <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                                <span class="d-md-none">Menu</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown" role="menu">
                                <li role="none">
                                    <a class="dropdown-item d-flex align-items-center" 
                                       href="<?php echo BASE_URL; ?>profile.php" role="menuitem">
                                        <i class="fas fa-user me-2" aria-hidden="true"></i>
                                        <span>Profile</span>
                                    </a>
                                </li>
                                <li role="none"><hr class="dropdown-divider"></li>
                                <li role="none">
                                    <a class="dropdown-item d-flex align-items-center text-danger" 
                                       href="<?php echo BASE_URL; ?>logout.php" role="menuitem">
                                        <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i>
                                        <span>Logout</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <!-- Login/Register buttons for non-authenticated users -->
                <div class="d-flex gap-2">
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt me-1" aria-hidden="true"></i>Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main id="main-content" class="container mt-4 flex-grow-1" role="main" tabindex="-1">
        <?php
        // Display flash messages if they exist
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2" aria-hidden="true"></i>
                    ' . htmlspecialchars($_SESSION['success_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['success_message']);
        }
        
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i>
                    ' . htmlspecialchars($_SESSION['error_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['error_message']);
        }
        
        if (isset($_SESSION['warning_message'])) {
            echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                    ' . htmlspecialchars($_SESSION['warning_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['warning_message']);
        }
        
        if (isset($_SESSION['info_message'])) {
            echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
                    ' . htmlspecialchars($_SESSION['info_message']) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            unset($_SESSION['info_message']);
        }
        ?>
