    </main>
    
    <!-- Footer -->
    <footer class="footer mt-5" role="contentinfo" aria-label="Site footer">
        <div class="container">
            <div class="row g-4">
                <!-- Brand Section -->
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <h5 class="d-flex align-items-center mb-3">
                            <i class="fas fa-vote-yea me-2" aria-hidden="true"></i>
                            <?php echo APP_NAME; ?>
                        </h5>
                        <p class="mb-3">Democratic Election Management System for transparent, secure, and efficient electoral processes.</p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-primary">
                                <i class="fas fa-shield-alt me-1" aria-hidden="true"></i>Secure
                            </span>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1" aria-hidden="true"></i>Transparent
                            </span>
                            <span class="badge bg-info">
                                <i class="fas fa-users me-1" aria-hidden="true"></i>Democratic
                            </span>
                        </div>
                        <small class="text-muted d-block">
                            <i class="fas fa-code-branch me-1" aria-hidden="true"></i>
                            Version <?php echo APP_VERSION ?? '1.0.0'; ?>
                        </small>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6 col-sm-6">
                    <h6 class="mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo BASE_URL; ?>" class="footer-link">
                                <i class="fas fa-home me-1" aria-hidden="true"></i>Home
                            </a>
                        </li>
                        <?php if (isAuthenticated()): ?>
                            <li class="mb-2">
                                <a href="<?php echo BASE_URL; ?>profile.php" class="footer-link">
                                    <i class="fas fa-user me-1" aria-hidden="true"></i>Profile
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="<?php echo BASE_URL; ?>logout.php" class="footer-link">
                                    <i class="fas fa-sign-out-alt me-1" aria-hidden="true"></i>Logout
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="mb-2">
                                <a href="<?php echo BASE_URL; ?>login.php" class="footer-link">
                                    <i class="fas fa-sign-in-alt me-1" aria-hidden="true"></i>Login
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Features -->
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <h6 class="mb-3">Features</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <span class="footer-feature">
                                <i class="fas fa-user-check me-1 text-primary" aria-hidden="true"></i>
                                Student Registration
                            </span>
                        </li>
                        <li class="mb-2">
                            <span class="footer-feature">
                                <i class="fas fa-clipboard-list me-1 text-success" aria-hidden="true"></i>
                                Candidate Applications
                            </span>
                        </li>
                        <li class="mb-2">
                            <span class="footer-feature">
                                <i class="fas fa-users-cog me-1 text-info" aria-hidden="true"></i>
                                Role Management
                            </span>
                        </li>
                        <li class="mb-2">
                            <span class="footer-feature">
                                <i class="fas fa-poll me-1 text-warning" aria-hidden="true"></i>
                                Election Monitoring
                            </span>
                        </li>
                        <li class="mb-2">
                            <span class="footer-feature">
                                <i class="fas fa-trophy me-1 text-danger" aria-hidden="true"></i>
                                Results Management
                            </span>
                        </li>
                    </ul>
                </div>
                
                <!-- Contact & Support -->
                <div class="col-lg-3 col-md-6">
                    <h6 class="mb-3">Support & Contact</h6>
                    <div class="contact-info">
                        <div class="mb-2">
                            <i class="fas fa-envelope me-2 text-primary" aria-hidden="true"></i>
                            <a href="mailto:saviojohnym.com" class="footer-link">
                                saviojohnym@gmail.com
                            </a>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-phone me-2 text-success" aria-hidden="true"></i>
                            <a href="tel:8111802625" class="footer-link">
                                8111802625
                            </a>
                        </div>
                        
                        
                        <!-- Social Links -->
                        <div class="social-links">
                            <h6 class="mb-2">Follow Me</h6>
                            <div class="d-flex gap-2">
                                <a href="https://github.com/SavioJohny" class="btn btn-outline-light btn-sm social-btn" 
                                   aria-label="Follow me on GitHub" title="GitHub">
                                    <i class="fab fa-github" aria-hidden="true"></i>
                                </a>
                                <a href="https://www.linkedin.com/in/savio-johny-409b99297" class="btn btn-outline-light btn-sm social-btn" 
                                   aria-label="Follow me on LinkedIn" title="LinkedIn">
                                    <i class="fab fa-linkedin-in" aria-hidden="true"></i>
                                </a>
                                <a href="https://www.instagram.com/thesaviojohny" class="btn btn-outline-light btn-sm social-btn" 
                                   aria-label="Follow me on Instagram" title="Instagram">
                                    <i class="fab fa-instagram" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer Bottom -->
            <hr class="my-4 border-light opacity-25">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-center text-md-start">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-center justify-content-md-end align-items-center gap-3">
                        <small class="text-muted">
                            <i class="fas fa-code me-1" aria-hidden="true"></i>
                            Built with HTML | Bootstrap CSS | PHP | MariaDB
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Back to Top Button -->
            <button type="button" class="btn btn-primary btn-floating position-fixed bottom-0 end-0 m-3 d-none" 
                    id="backToTopBtn" aria-label="Back to top" title="Back to top">
                <i class="fas fa-chevron-up" aria-hidden="true"></i>
            </button>
        </div>
    </footer>
    
    <!-- Bootstrap 5.3.2 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <!-- Custom Styles for Social Media Hover Fix -->
    <style>
        /* Fix social media button hover visibility */
        .social-links .btn-outline-light.social-btn:hover,
        .social-links .btn-outline-light.social-btn:focus {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .social-links .btn-outline-light.social-btn:hover i,
        .social-links .btn-outline-light.social-btn:focus i {
            color: #ffffff !important;
        }
        
        .social-links .btn-outline-light.social-btn {
            transition: all 0.3s ease;
            color: #ffffff;
            border-color: rgba(255,255,255,0.5);
        }
        
        .social-links .btn-outline-light.social-btn i {
            transition: color 0.3s ease;
        }
    </style>
    
    <!-- Custom JavaScript for Enhanced UX and Accessibility -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Back to top button functionality
            const backToTopBtn = document.getElementById('backToTopBtn');
            
            if (backToTopBtn) {
                // Show/hide back to top button based on scroll position
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        backToTopBtn.classList.remove('d-none');
                    } else {
                        backToTopBtn.classList.add('d-none');
                    }
                });
                
                // Smooth scroll to top
                backToTopBtn.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }
            
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
                
                // Escape key to close dropdowns
                if (e.key === 'Escape') {
                    const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
                    openDropdowns.forEach(dropdown => {
                        const toggle = dropdown.previousElementSibling;
                        if (toggle) {
                            bootstrap.Dropdown.getInstance(toggle)?.hide();
                        }
                    });
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
            
            // Enhanced form validation feedback
            const forms = document.querySelectorAll('form[data-bs-validation="true"]');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Focus first invalid field
                        const firstInvalid = form.querySelector(':invalid');
                        if (firstInvalid) {
                            firstInvalid.focus();
                        }
                    }
                    form.classList.add('was-validated');
                });
            });
            
            // Tooltip initialization
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Popover initialization
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // Loading state management
            const loadingButtons = document.querySelectorAll('[data-loading-text]');
            loadingButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalText = button.innerHTML;
                    const loadingText = button.getAttribute('data-loading-text');
                    
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + loadingText;
                    button.disabled = true;
                    
                    // Re-enable after form submission or page navigation
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 3000);
                });
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
    <?php if (file_exists(dirname(__DIR__) . '/assets/js/main.js')): ?>
        <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <?php endif; ?>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($additional_js) && is_array($additional_js)): ?>
        <?php foreach ($additional_js as $js_file): ?>
            <script src="<?php echo BASE_URL . $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
</body>
</html>
