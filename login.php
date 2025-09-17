<?php
session_start();
require_once 'config/database.php';
require_once 'config/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.email, u.password_hash, r.role_name, u.fname, u.lname 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['full_name'] = trim($user['fname'] . ' ' . $user['lname']);
                
                // Generate and store session token for security
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;
                
                $update_stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE user_id = ?");
                $update_stmt->execute([$session_token, $user['user_id']]);
                
                header('Location: index.php');
                exit();
            } else {
                $error_message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'Login failed. Please try again.';
        }
    }
}

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo APP_NAME; ?> - Secure Login Portal for Democratic Election Management System">
    <meta name="keywords" content="election, democracy, voting, login, secure access">
    <meta name="author" content="ElectoCracy Team">
    <meta name="theme-color" content="#457B9D">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $page_title . ' - ' . APP_NAME; ?>">
    <meta property="og:description" content="Secure Login Portal for Democratic Election Management System">
    <meta property="og:image" content="<?php echo BASE_URL; ?>assets/images/og-image.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="<?php echo $page_title . ' - ' . APP_NAME; ?>">
    <meta property="twitter:description" content="Secure Login Portal for Democratic Election Management System">
    
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
        "description": "Democratic Election Management System - Login Portal",
        "applicationCategory": "GovernmentApplication",
        "operatingSystem": "Web Browser"
    }
    </script>
    
    <!-- Additional Login Page Styles -->
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--light-mint) 0%, var(--light-blue) 100%);
            padding: 2rem 0;
        }
        
        .login-card {
            max-width: 450px;
            width: 100%;
            margin: 0 1rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-brand {
            font-size: 2.5rem;
            color: var(--medium-blue);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--dark-blue);
            font-weight: var(--font-weight-medium);
            opacity: 0.8;
        }
        
        .password-input-wrapper {
            position: relative;
        }
        
        .password-input-wrapper input {
            padding-right: 4.5rem;
        }
        
        .password-input-wrapper input.is-invalid,
        .password-input-wrapper input.is-valid {
            background-position: right 3.5rem center;
        }
        
        /* Apply same validation styling to email field */
        #email.is-invalid,
        #email.is-valid {
            padding-right: 3rem;
            background-position: right 1rem center;
        }
        
        .password-toggle {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            border: none;
            background: transparent;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--medium-blue);
            transition: color var(--transition-fast);
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: var(--dark-blue);
        }
        
        .password-toggle:focus {
            outline: none;
            color: var(--dark-blue);
        }
        
        /* Loading animation */
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
            .login-brand {
                font-size: 2rem;
            }
            
            .login-card {
                margin: 0 0.5rem;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="skip-link visually-hidden-focusable">Skip to main content</a>
    
    <!-- Main Login Container -->
    <div class="login-container">
        <div class="login-card">
            <!-- Login Header -->
            <div class="login-header">
                <div class="login-brand">
                    <i class="fas fa-vote-yea me-2" aria-hidden="true"></i>
                    <?php echo APP_NAME; ?>
                </div>
                <p class="login-subtitle">Secure Access Portal</p>
            </div>
            
            <!-- Login Card -->
            <div class="card login-card-body">
                <div class="card-header">
                    <h3 class="mb-0 text-center">
                        <i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i>
                        Sign In to Your Account
                    </h3>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm" novalidate data-bs-validation="true">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1" aria-hidden="true"></i>
                                Email Address *
                            </label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   required
                                   maxlength="255"
                                   pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
                                   placeholder="Enter your email address"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   autocomplete="email"
                                   aria-describedby="email-error">
                            <div class="invalid-feedback" id="email-error"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1" aria-hidden="true"></i>
                                Password *
                            </label>
                            <div class="password-input-wrapper">
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       required
                                       minlength="8"
                                       maxlength="255"
                                       placeholder="Enter your password"
                                       autocomplete="current-password"
                                       aria-describedby="password-help password-error">
                                <button class="password-toggle" type="button" id="togglePassword"
                                        aria-label="Toggle password visibility" title="Show Password">
                                    <i class="fas fa-eye" id="toggleIcon" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback" id="password-error"></div>
                            <div class="form-text" id="password-help">Password must be at least 8 characters long</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="loginBtn" data-loading-text="Signing in...">
                            <span class="btn-text">
                                <i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i>
                                Sign In
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap 5.3.2 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <!-- Login Page JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');
            const togglePassword = document.getElementById('togglePassword');
            const toggleIcon = document.getElementById('toggleIcon');

            // Email validation regex (comprehensive)
            const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;

            // Password toggle functionality
            if (togglePassword && toggleIcon && passwordInput) {
                togglePassword.addEventListener('click', function(e) {
                    e.preventDefault();

                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    const newType = isPassword ? 'text' : 'password';

                    // Change input type
                    passwordInput.setAttribute('type', newType);

                    // Update icon and title
                    if (isPassword) {
                        toggleIcon.classList.remove('fa-eye');
                        toggleIcon.classList.add('fa-eye-slash');
                        togglePassword.setAttribute('title', 'Hide Password');
                        togglePassword.setAttribute('aria-label', 'Hide password');
                    } else {
                        toggleIcon.classList.remove('fa-eye-slash');
                        toggleIcon.classList.add('fa-eye');
                        togglePassword.setAttribute('title', 'Show Password');
                        togglePassword.setAttribute('aria-label', 'Show password');
                    }

                    // Maintain focus on password input
                    passwordInput.focus();
                });
            }

            // Real-time email validation
            emailInput.addEventListener('input', validateEmail);
            emailInput.addEventListener('blur', validateEmail);

            // Real-time password validation
            passwordInput.addEventListener('input', validatePassword);
            passwordInput.addEventListener('blur', validatePassword);

            // Email validation function
            function validateEmail() {
                const email = emailInput.value.trim();
                const emailError = document.getElementById('email-error');

                // Clear previous validation
                emailInput.classList.remove('is-valid', 'is-invalid');
                emailError.textContent = '';

                if (email === '') {
                    emailInput.classList.add('is-invalid');
                    emailError.textContent = 'Email address is required.';
                    return false;
                }

                if (email.length > 255) {
                    emailInput.classList.add('is-invalid');
                    emailError.textContent = 'Email address is too long (maximum 255 characters).';
                    return false;
                }

                if (!emailRegex.test(email)) {
                    emailInput.classList.add('is-invalid');
                    emailError.textContent = 'Please enter a valid email address.';
                    return false;
                }

                // Check for common email issues
                if (email.includes('..') || email.startsWith('.') || email.endsWith('.')) {
                    emailInput.classList.add('is-invalid');
                    emailError.textContent = 'Email address format is invalid.';
                    return false;
                }

                emailInput.classList.add('is-valid');
                return true;
            }

            // Password validation function
            function validatePassword() {
                const password = passwordInput.value;
                const passwordError = document.getElementById('password-error');

                // Clear previous validation
                passwordInput.classList.remove('is-valid', 'is-invalid');
                passwordError.textContent = '';

                if (password === '') {
                    passwordInput.classList.add('is-invalid');
                    passwordError.textContent = 'Password is required.';
                    return false;
                }

                if (password.length < 8) {
                    passwordInput.classList.add('is-invalid');
                    passwordError.textContent = 'Password must be at least 8 characters long.';
                    return false;
                }

                if (password.length > 255) {
                    passwordInput.classList.add('is-invalid');
                    passwordError.textContent = 'Password is too long (maximum 255 characters).';
                    return false;
                }

                // Check for whitespace only
                if (password.trim() === '') {
                    passwordInput.classList.add('is-invalid');
                    passwordError.textContent = 'Password cannot be empty or contain only spaces.';
                    return false;
                }

                passwordInput.classList.add('is-valid');
                return true;
            }

            // Form submission validation
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Validate all fields
                const isEmailValid = validateEmail();
                const isPasswordValid = validatePassword();

                if (!isEmailValid || !isPasswordValid) {
                    // Focus on first invalid field
                    if (!isEmailValid) {
                        emailInput.focus();
                    } else if (!isPasswordValid) {
                        passwordInput.focus();
                    }
                    return false;
                }

                // Show loading state
                loginBtn.classList.add('btn-loading');
                loginBtn.disabled = true;

                // Submit form after brief delay for UX
                setTimeout(function() {
                    loginForm.submit();
                }, 300);
            });

            // Auto-focus email field on page load
            emailInput.focus();

            // Handle browser autofill
            setTimeout(function() {
                if (emailInput.value) {
                    validateEmail();
                }
                if (passwordInput.value) {
                    validatePassword();
                }
            }, 100);

            // Enhanced keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Escape key to clear form
                if (e.key === 'Escape') {
                    if (document.activeElement === emailInput || document.activeElement === passwordInput) {
                        document.activeElement.blur();
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
        });
    </script>
    
    <!-- Additional JavaScript files -->
    <?php if (file_exists('assets/js/main.js')): ?>
        <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <?php endif; ?>
</body>
</html>
