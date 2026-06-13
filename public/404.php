<?php
/**
 * 404 - Page Not Found
 */

// Prevent error handler loops on this page
set_exception_handler(null);
ini_set('display_errors', '0');

require '../inc/bootstrap.php';

http_response_code(404);

require '../inc/header.php';
?>

<div class="error-page-wrapper">
    <main class="error-page-main">
        <div class="error-page-card">

            <div class="error-page-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                    <path d="M11 8v4"/>
                    <circle cx="11" cy="15" r="0.5" fill="currentColor"/>
                </svg>
            </div>

            <div class="error-page-code">404</div>

            <h1 class="error-page-title">Page Not Found</h1>

            <p class="error-page-description">
                Oops! The page you're looking for doesn't exist or has been moved.
                Let's get you back on track.
            </p>

            <div class="error-page-actions">
                <a href="./" class="error-page-btn error-page-btn--primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Go to Homepage
                </a>
                <a href="chatbots" class="error-page-btn error-page-btn--secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    Browse Chatbots
                </a>
            </div>

        </div>
    </main>
</div>

<?php require '../inc/footer.php'; ?>
