<?php
/**
 * Submit Chatbot Page — Affiliate Partnership
 */

require '../inc/bootstrap.php';
require 'app/auth.php';

if (!$isLoggedIn) {
    header('Location: login?redirect=submit-chatbot');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Listed on ChatbotScout – Partner With Us</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="css/submitChatbot.css?v=<?= filemtime(__DIR__ . '/css/submitChatbot.css') ?>">
</head>
<body>

<div class="sc-page">

    <!-- ── Left panel ───────────────────────────────────── -->
    <aside class="sc-sidebar">
        <div class="sc-sidebar__inner">

            <div class="sc-sidebar__logo">
                <a href="./">
                    <img src="img/ChatbotScout_logo.png" alt="ChatbotScout" height="36">
                </a>
            </div>

            <h2 class="sc-sidebar__title">Grow your chatbot<br>with affiliate revenue</h2>
            <p class="sc-sidebar__sub">We list your chatbot, drive high-intent traffic to it, and earn a share of revenue — no upfront cost.</p>

            <ul class="sc-steps">
                <li class="sc-steps__item sc-steps__item--active">
                    <div class="sc-steps__dot">1</div>
                    <div>
                        <strong>Tell us about your chatbot</strong>
                        <span>Fill in the short form</span>
                    </div>
                </li>
                <li class="sc-steps__item">
                    <div class="sc-steps__dot">2</div>
                    <div>
                        <strong>We review & reach out</strong>
                        <span>Our team contacts you to discuss a deal</span>
                    </div>
                </li>
                <li class="sc-steps__item">
                    <div class="sc-steps__dot">3</div>
                    <div>
                        <strong>Agree on affiliate terms</strong>
                        <span>Revenue share tailored to your product</span>
                    </div>
                </li>
                <li class="sc-steps__item">
                    <div class="sc-steps__dot">4</div>
                    <div>
                        <strong>Go live on ChatbotScout</strong>
                        <span>Your chatbot reaches thousands of buyers</span>
                    </div>
                </li>
            </ul>

            <div class="sc-sidebar__note">
                <span class="sc-sidebar__note-kicker">Partnership model</span>
                <ul class="sc-sidebar__note-list">
                    <li>No listing fee</li>
                    <li>Direct affiliate terms</li>
                    <li>We earn when you earn</li>
                </ul>
            </div>

        </div>
    </aside>

    <!-- ── Right panel: form ────────────────────────────── -->
    <main class="sc-main">
        <div class="sc-main__inner">

            <div class="sc-form-header">
                <h1>Get Your Chatbot Listed</h1>
                <p>Tell us about your chatbot and we'll get back to you about an affiliate partnership.</p>
            </div>

            <!-- Success state -->
            <div class="sc-success" id="scSuccess" style="display:none;">
                <div class="sc-success__icon">🎉</div>
                <h2>We got your submission!</h2>
                <p>Thanks for reaching out. We've sent a confirmation to <strong><?php echo htmlspecialchars($user['email']); ?></strong>.</p>
                <p>Our team will review your chatbot and contact you to discuss the affiliate partnership details.</p>
                <a href="./" class="sc-success__btn">Back to Home</a>
            </div>

            <!-- Form -->
            <form class="sc-form" id="scForm" novalidate>

                <!-- Affiliate info banner -->
                <div class="sc-affiliate-banner">
                    <div>
                        <span class="sc-affiliate-banner__eyebrow">Affiliate listing request</span>
                        <strong>Start with a free review.</strong>
                        <span>If your chatbot is a fit, we'll contact you to agree clear affiliate terms before it goes live.</span>
                    </div>
                </div>

                <!-- Contact name -->
                <div class="sc-field" id="field-contact-name">
                    <label class="sc-label" for="contactName">Your Name<span class="sc-required">*</span></label>
                    <input
                        type="text"
                        id="contactName"
                        name="contact_name"
                        class="sc-input"
                        placeholder="e.g. John Smith"
                        value="<?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>"
                        maxlength="200"
                    >
                    <span class="sc-error" id="error-contact-name"></span>
                </div>

                <!-- Company name -->
                <div class="sc-field" id="field-company">
                    <label class="sc-label" for="companyName">Company / Product Name<span class="sc-required">*</span></label>
                    <input
                        type="text"
                        id="companyName"
                        name="company_name"
                        class="sc-input"
                        placeholder="e.g. Acme AI Inc."
                        maxlength="200"
                    >
                    <span class="sc-error" id="error-company"></span>
                </div>

                <!-- Email -->
                <div class="sc-field" id="field-email">
                    <label class="sc-label" for="contactEmail">
                        Contact Email<span class="sc-required">*</span>
                        <span class="sc-label-hint">We'll use this to get in touch about the partnership</span>
                    </label>
                    <input
                        type="email"
                        id="contactEmail"
                        name="contact_email"
                        class="sc-input"
                        placeholder="e.g. hello@yourcompany.com"
                        value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                        maxlength="255"
                    >
                    <span class="sc-error" id="error-email"></span>
                </div>

                <!-- Two-column row: chatbot name + URL -->
                <div class="sc-row">
                    <div class="sc-field" id="field-name">
                        <label class="sc-label" for="chatbotName">Chatbot Name<span class="sc-required">*</span></label>
                        <input
                            type="text"
                            id="chatbotName"
                            name="name"
                            class="sc-input"
                            placeholder="e.g. HelpBot Pro"
                            maxlength="200"
                        >
                        <span class="sc-error" id="error-name"></span>
                    </div>
                    <div class="sc-field" id="field-url">
                        <label class="sc-label" for="websiteUrl">Chatbot Website URL<span class="sc-required">*</span></label>
                        <div class="sc-input-wrap">
                            <span class="sc-input-icon">🌐</span>
                            <input
                                type="url"
                                id="websiteUrl"
                                name="website_url"
                                class="sc-input sc-input--icon"
                                placeholder="https://yourchatbot.com"
                                maxlength="500"
                            >
                        </div>
                        <span class="sc-error" id="error-url"></span>
                    </div>
                </div>

                <!-- Message -->
                <div class="sc-field" id="field-message">
                    <label class="sc-label" for="message">
                        Tell us about your chatbot<span class="sc-required">*</span>
                        <span class="sc-label-hint">Briefly describe what it does, who it's for, and your current pricing model or affiliate structure if you have one</span>
                    </label>
                    <textarea
                        id="message"
                        name="message"
                        class="sc-textarea"
                        placeholder="e.g. HelpBot Pro is an AI customer support chatbot for SaaS companies. We offer monthly plans starting at $29. We're open to a 20–30% affiliate commission on referred signups..."
                        rows="5"
                        maxlength="2000"
                    ></textarea>
                    <div class="sc-char-count">
                        <span id="messageCount">0</span>/2000
                    </div>
                    <span class="sc-error" id="error-message"></span>
                </div>

                <!-- Submit -->
                <div class="sc-form__footer">
                    <!-- <p class="sc-form__note">
                        By submitting, you confirm that you have the rights to list this chatbot and agree to our <a href="affiliate-disclosure">Affiliate Terms</a>.
                    </p> -->
                    <button type="submit" class="sc-submit-btn" id="scSubmitBtn">
                        <span id="scSubmitText">Send Partnership Request</span>
                        <span id="scSubmitLoader" style="display:none;">Sending...</span>
                    </button>
                </div>

                <p class="sc-global-error" id="scGlobalError"></p>

            </form>

        </div>
    </main>

</div>

<?php require '../inc/footer.php'; ?>
<script src="js/submitChatbot.js?v=<?= filemtime(__DIR__ . '/js/submitChatbot.js') ?>"></script>
</body>
</html>
