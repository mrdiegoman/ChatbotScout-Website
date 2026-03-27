<?php
require '../inc/bootstrap.php';
require '../inc/header.php';

// ============================================
// PRG - Read flash messages from session
// ============================================

$contactSuccess  = $_SESSION['contact_success']   ?? null;
$contactError    = $_SESSION['contact_error']      ?? null;
$contactErrors   = $_SESSION['contact_errors']     ?? [];
$oldInput        = $_SESSION['contact_old_input']  ?? [];

unset(
    $_SESSION['contact_success'],
    $_SESSION['contact_error'],
    $_SESSION['contact_errors'],
    $_SESSION['contact_old_input']
);

// Helper to repopulate old input safely
function old(string $key, array $old): string {
    return htmlspecialchars($old[$key] ?? '', ENT_QUOTES);
}
?>

<link rel="stylesheet" href="css/contact.css">

<div class="contact-page-wrapper">
    <main class="contact-page-main">
        <section class="contact-page-main-section-1">
            <h1>Contact Us</h1>
            <p>
                We are happy to hear from you. If you have any questions about our
                content, affiliate links, or featured websites, feel free to get
                in touch using the form below.
            </p>
            <p>
                If you represent a brand or would like to discuss a potential
                collaboration, please include a short description of your project
                so we can review it properly.
            </p>
            <p>
                We do our best to respond to all messages within a few business days.
                Thank you for reaching out and for visiting our website.
            </p>
        </section>

        <section class="contact-page-main-section-2">
            <h2>Get in Touch</h2>

            <form action="../app/contactActions.php" method="POST" id="contactForm" novalidate>

                <div class="contact-page-main-section-2-form-row">
                    <div class="contact-page-main-section-2-form-row-group">
                        <label for="firstName">First Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="firstName"
                            name="first_name"
                            placeholder="Please enter first name"
                            value="<?= old('firstName', $oldInput) ?>"
                            maxlength="50"
                            autocomplete="name"
                            required>
                    </div>
                    <div class="contact-page-main-section-2-form-row-group">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                        <input
                            type="text"
                            id="lastName"
                            name="last_name"
                            placeholder="Please enter last name"
                            value="<?= old('lastName', $oldInput) ?>"
                            maxlength="50"
                            autocomplete="family-name"
                            required>
                    </div>
                </div>

                <div class="contact-page-main-section-2-form-row">
                    <div class="contact-page-main-section-2-form-row-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Please enter your email"
                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                            maxlength="255"
                            autocomplete="email"
                            required>
                    </div>
                    <div class="contact-page-main-section-2-form-row-group">
                        <label>Subject <span class="required">*</span></label>

                        <!-- Hidden input carries the actual value to PHP -->
                        <input type="hidden" id="subjectValue" name="subject" value="<?= old('subject', $oldInput) ?>">

                        <div class="dropdown-wrapper" id="subjectDropdown">
                            <div class="dropdown-select">
                                <div class="dropdown-selected" id="subjectSelected">
                                    <?php
                                        $subjectLabels = [
                                            'general_question'    => 'General Question',
                                            'affiliate_inquiry'   => 'Affiliate Inquiry',
                                            'partnership_request' => 'Partnership Request',
                                            'report_issue'        => 'Report an Issue',
                                            'feedback'            => 'Feedback / Suggestion',
                                            'press_media'         => 'Press / Media',
                                        ];
                                        $oldSubject = $oldInput['subject'] ?? '';
                                        echo $oldSubject && isset($subjectLabels[$oldSubject])
                                            ? htmlspecialchars($subjectLabels[$oldSubject])
                                            : 'Please select a subject topic';
                                    ?>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#232127"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="dropdown-droparrow-icon">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                            <ul class="dropdown-options">
                                <li class="dropdown-option <?= $oldSubject === 'general_question'    ? 'selected' : '' ?>" data-value="general_question">General Question</li>
                                <li class="dropdown-option <?= $oldSubject === 'affiliate_inquiry'   ? 'selected' : '' ?>" data-value="affiliate_inquiry">Affiliate Inquiry</li>
                                <li class="dropdown-option <?= $oldSubject === 'partnership_request' ? 'selected' : '' ?>" data-value="partnership_request">Partnership Request</li>
                                <li class="dropdown-option <?= $oldSubject === 'report_issue'        ? 'selected' : '' ?>" data-value="report_issue">Report an Issue</li>
                                <li class="dropdown-option <?= $oldSubject === 'feedback'            ? 'selected' : '' ?>" data-value="feedback">Feedback / Suggestion</li>
                                <li class="dropdown-option <?= $oldSubject === 'press_media'         ? 'selected' : '' ?>" data-value="press_media">Press / Media</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="contact-page-main-section-2-form-row">
                    <div class="contact-page-main-section-2-form-row-group" style="flex:1;">
                        <label for="message">Message <span class="required">*</span></label>
                        <textarea
                            id="message"
                            name="message"
                            maxlength="400"
                            wrap="soft"
                            placeholder="Here write your message..."
                            required><?= old('message', $oldInput) ?></textarea>
                    </div>
                </div>

                <button type="submit" class="contact-page-main-section-2-submit-btn">Send Message</button>
            </form>
        </section>

        <aside class="contact-page-main-aside">
            <h3>Note</h3>
            <p>
                By contacting us, you agree that your personal data will be used
                solely to respond to your inquiry, in accordance with our
                <a href="privacyPolicy.php">Privacy Policy</a>.
            </p>
        </aside>
    </main>
</div>

<!-- Pass PHP flash messages to JS -->
<script nonce="<?= $cspNonce ?>">
    window._contactSuccess = <?= json_encode($contactSuccess) ?>;
    window._contactError   = <?= json_encode($contactError) ?>;
    window._contactErrors  = <?= json_encode($contactErrors) ?>;
</script>

<script src="js/contact.js" defer></script>

<?php require '../inc/footer.php'; ?>