<?php
/**
 * Submit Chatbot Page
 */

require '../inc/bootstrap.php';
require '../app/auth.php';

if (!$isLoggedIn) {
    header('Location: login.php?redirect=submitChatbot.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Your Chatbot – ChatbotHub</title>
    <?php require '../inc/header.php'; ?>
    <link rel="stylesheet" href="../css/submitChatbot.css">
</head>
<body>

<div class="sc-page">

    <!-- ── Left panel: info ─────────────────────────────── -->
    <aside class="sc-sidebar">
        <div class="sc-sidebar__inner">

            <div class="sc-sidebar__logo">
                <a href="index.php">
                    <img src="../img/chatbothub_logo.png" alt="ChatbotHub" height="36">
                </a>
            </div>

            <h2 class="sc-sidebar__title">Get your chatbot<br>in front of thousands</h2>
            <p class="sc-sidebar__sub">Submit your chatbot and we'll review it, test it, and add it to the directory.</p>

            <ul class="sc-steps">
                <li class="sc-steps__item sc-steps__item--active">
                    <div class="sc-steps__dot">1</div>
                    <div>
                        <strong>Submit your chatbot</strong>
                        <span>Fill in the form with basic info</span>
                    </div>
                </li>
                <li class="sc-steps__item">
                    <div class="sc-steps__dot">2</div>
                    <div>
                        <strong>We review & test it</strong>
                        <span>Our team checks your chatbot</span>
                    </div>
                </li>
                <li class="sc-steps__item">
                    <div class="sc-steps__dot">3</div>
                    <div>
                        <strong>Get approved</strong>
                        <span>You'll receive an email notification</span>
                    </div>
                </li>
                <li class="sc-steps__item">
                    <div class="sc-steps__dot">4</div>
                    <div>
                        <strong>Choose a listing plan</strong>
                        <span>Pick a plan and go live</span>
                    </div>
                </li>
            </ul>

            <div class="sc-sidebar__note">
                <div class="sc-sidebar__note-icon">🔍</div>
                <p>We personally test every chatbot before approving it. This ensures quality for our visitors and gives your chatbot accurate, trustworthy information on the directory.</p>
            </div>

        </div>
    </aside>

    <!-- ── Right panel: form ─────────────────────────────── -->
    <main class="sc-main">
        <div class="sc-main__inner">

            <div class="sc-form-header">
                <h1>Submit Your Chatbot</h1>
                <p>Fill in the details below and we'll take it from there.</p>
            </div>

            <!-- Success state (hidden by default) -->
            <div class="sc-success" id="scSuccess" style="display:none;">
                <div class="sc-success__icon">🎉</div>
                <h2>Submission received!</h2>
                <p>Thanks for submitting your chatbot. We've sent a confirmation email to <strong><?php echo htmlspecialchars($user['email']); ?></strong>.</p>
                <p>Our team will review and test your chatbot. Once approved, you'll get an email with a link to choose your listing plan.</p>
                <a href="index.php" class="sc-success__btn">Back to Home</a>
            </div>

            <!-- Form -->
            <form class="sc-form" id="scForm" novalidate>

                <!-- Chatbot name -->
                <div class="sc-field" id="field-name">
                    <label class="sc-label" for="chatbotName">
                        Chatbot Name <span class="sc-required">*</span>
                    </label>
                    <input
                        type="text"
                        id="chatbotName"
                        name="name"
                        class="sc-input"
                        placeholder="e.g. Intercom, Tidio, Drift"
                        maxlength="200"
                        autocomplete="off"
                    >
                    <span class="sc-error" id="error-name"></span>
                </div>

                <!-- Website URL -->
                <div class="sc-field" id="field-website">
                    <label class="sc-label" for="websiteUrl">
                        Website URL <span class="sc-required">*</span>
                    </label>
                    <div class="sc-input-wrap">
                        <span class="sc-input-icon">🌐</span>
                        <input
                            type="url"
                            id="websiteUrl"
                            name="website_url"
                            class="sc-input sc-input--icon"
                            placeholder="https://yourchatbot.com"
                            autocomplete="off"
                        >
                    </div>
                    <span class="sc-error" id="error-website"></span>
                </div>

                <!-- Logo upload -->
                <div class="sc-field" id="field-logo">
                    <label class="sc-label">
                        Chatbot Logo
                        <span class="sc-label-hint">JPG, PNG or WebP · Max 5MB · Recommended 400×400px</span>
                    </label>
                    <div class="sc-logo-upload" id="logoUploadArea">
                        <input
                            type="file"
                            id="logoFile"
                            name="logo"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            class="sc-logo-upload__input"
                        >
                        <div class="sc-logo-upload__placeholder" id="logoPlaceholder">
                            <div class="sc-logo-upload__icon">📷</div>
                            <p class="sc-logo-upload__text">
                                <strong>Click to upload</strong> or drag & drop
                            </p>
                            <p class="sc-logo-upload__hint">PNG, JPG, WebP up to 5MB</p>
                        </div>
                        <div class="sc-logo-upload__preview" id="logoPreview" style="display:none;">
                            <img id="logoPreviewImg" src="" alt="Logo preview">
                            <button type="button" class="sc-logo-upload__remove" id="logoRemoveBtn">✕</button>
                        </div>
                    </div>
                    <span class="sc-error" id="error-logo"></span>
                </div>

                <!-- Access note -->
                <div class="sc-field" id="field-access">
                    <label class="sc-label" for="accessNote">
                        How can we access your chatbot for testing? <span class="sc-required">*</span>
                        <span class="sc-label-hint">Provide a demo link, trial credentials, or describe how we can test your chatbot. We'll contact you via email if we need more info.</span>
                    </label>
                    <textarea
                        id="accessNote"
                        name="access_note"
                        class="sc-textarea"
                        placeholder="e.g. You can try our chatbot at https://yourchatbot.com/demo — no login required. Or: we can set up a trial account for you, just email us at..."
                        rows="4"
                        maxlength="1000"
                    ></textarea>
                    <div class="sc-char-count">
                        <span id="accessNoteCount">0</span>/1000
                    </div>
                    <span class="sc-error" id="error-access"></span>
                </div>

                <!-- Agreement checkbox -->
                <div class="sc-field" id="field-agreement">
                    <label class="sc-checkbox">
                        <input
                            type="checkbox"
                            id="accessAgreement"
                            name="access_agreement"
                            value="1"
                            class="sc-checkbox__input"
                        >
                        <span class="sc-checkbox__box"></span>
                        <span class="sc-checkbox__text">
                            I understand that ChatbotHub needs access to my chatbot in order to test it, gather information and add it to the directory. I agree to provide reasonable access during the review process.
                        </span>
                    </label>
                    <span class="sc-error" id="error-agreement"></span>
                </div>

                <!-- Submit -->
                <div class="sc-form__footer">
                    <p class="sc-form__note">
                        By submitting, you confirm that you have the rights to list this chatbot and agree to our <a href="affiliateDisclosure.php">Terms of Service</a>.
                    </p>
                    <button type="submit" class="sc-submit-btn" id="scSubmitBtn">
                        <span id="scSubmitText">Submit Chatbot</span>
                        <span id="scSubmitLoader" style="display:none;">Submitting...</span>
                    </button>
                </div>

                <p class="sc-global-error" id="scGlobalError"></p>

            </form>

        </div>
    </main>

</div>

<?php require '../inc/footer.php'; ?>
<script src="../js/submitChatbot.js"></script>
</body>
</html>