<?php
/**
 * 500 - Internal Server Error
 */

// Prevent error handler loops on this page
set_exception_handler(null);
ini_set('display_errors', '0');

require '../inc/bootstrap.php';

http_response_code(500);

require '../inc/header.php';
?>

<div class="error-page-wrapper">
    <main class="error-page-main">
        <div class="error-page-card">

            <div class="error-page-icon error-page-icon--warning">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                    <path d="M12 9v4"/>
                    <circle cx="12" cy="16.5" r="0.5" fill="currentColor"/>
                </svg>
            </div>

            <div class="error-page-code">500</div>

            <h1 class="error-page-title">Something Went Wrong</h1>

            <p class="error-page-description">
                We're sorry — something went wrong on our end. Our team has been
                notified and is working to fix the issue. Please try again shortly.
            </p>

            <div class="error-page-actions">
                <a href="index.php" class="error-page-btn error-page-btn--primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Go to Homepage
                </a>
                <a href="chatbotShowcase.php" class="error-page-btn error-page-btn--secondary">
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
