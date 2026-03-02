<?php
// Získanie emailu z URL parametra alebo session
$userEmail = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : 
             (isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'your.email@example.com');
?>

</html>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <link rel="stylesheet" href="../css/verificationSent.css">
</head>
<body>
    <div class="container">
        <div class="icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
            </svg>
        </div>

        <h1>Check your email</h1>
        
        <p class="email-sent">
            Verification email has already been sent successfully to:
        </p>

        <div class="email-address">
            <?php echo $userEmail; ?>
        </div>

        <div class="instructions">
            <h3>📋 What to do next?</h3>
            <ol>
                <li>Open your email inbox</li>
                <li>Find the email from us (check spam too)</li>
                <li>Click the verification link</li>
                <li>Return and log in to your account</li>
            </ol>
        </div>

        <div class="resend-section">
            <p>Didn't get the email?</p>
        </div>

        <button class="btn-resend" 
                onclick="resendEmail()" 
                data-email="<?php echo htmlspecialchars($userEmail); ?>">
            Resend Verification Email
        </button>

        <div class="divider"></div>

        <a href="index.php" class="btn-home">Back to Home Page</a>

        <div class="divider"></div>

        <p class="spam-notice">
            💡 Tip: If you don't see the email, check your spam or promo folder.
        </p>
    </div>

    <script src="../js/resendEmail.js" defer></script>
</body>
</html>