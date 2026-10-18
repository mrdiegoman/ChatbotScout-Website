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
$contactFirstError = is_array($contactErrors) && !empty($contactErrors)
    ? (string)$contactErrors[0]
    : '';

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

<div class="cs-page cs-contact-page">
    <section class="cs-hero" aria-labelledby="contact-title">
        <div class="cs-hero-inner">
            <div class="cs-hero-copy">
                <h1 id="contact-title">Contact Us</h1>
                <p class="cs-hero-lead">
                    Questions, partnerships, affiliate inquiries, and feedback all arrive in
                    one calm place. Share the useful context and we will review your message
                    carefully.
                </p>
                <div class="cs-hero-actions">
                    <a class="cs-button cs-button--green" href="chatbots">Browse chatbots</a>
                </div>
            </div>
        </div>
    </section>

    <main class="cs-main cs-contact-main"
          data-contact-flash
          data-contact-success="<?= htmlspecialchars((string)($contactSuccess ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          data-contact-error="<?= htmlspecialchars((string)($contactError ?? ''), ENT_QUOTES, 'UTF-8') ?>"
          data-contact-first-error="<?= htmlspecialchars($contactFirstError, ENT_QUOTES, 'UTF-8') ?>">
        <section class="cs-card cs-contact-form-card" aria-labelledby="contact-form-title">
            <div class="cs-text-stack">
                <h2 id="contact-form-title">Get in touch</h2>
                <p>
                    Use the form below for questions about our content, affiliate links,
                    featured websites, or potential collaborations.
                </p>
            </div>

            <form action="contact-submit" method="POST" id="contactForm" class="contact-form" novalidate>

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
                            autocomplete="given-name"
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
                        <label id="subjectLabel">Subject <span class="required">*</span></label>

                        <!-- Hidden input carries the actual value to PHP -->
                        <input type="hidden" id="subjectValue" name="subject" value="<?= old('subject', $oldInput) ?>">

                        <div
                            class="dropdown-wrapper"
                            id="subjectDropdown"
                            role="combobox"
                            tabindex="0"
                            aria-labelledby="subjectLabel subjectSelected"
                            aria-haspopup="listbox"
                            aria-expanded="false">
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
                                            : 'Please select a subject';
                                    ?>
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#232127"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="dropdown-droparrow-icon">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                            <ul class="dropdown-options" role="listbox">
                                <li class="dropdown-option <?= $oldSubject === 'general_question'    ? 'selected' : '' ?>" data-value="general_question" role="option" tabindex="-1" aria-selected="<?= $oldSubject === 'general_question' ? 'true' : 'false' ?>">General Question</li>
                                <li class="dropdown-option <?= $oldSubject === 'affiliate_inquiry'   ? 'selected' : '' ?>" data-value="affiliate_inquiry" role="option" tabindex="-1" aria-selected="<?= $oldSubject === 'affiliate_inquiry' ? 'true' : 'false' ?>">Affiliate Inquiry</li>
                                <li class="dropdown-option <?= $oldSubject === 'partnership_request' ? 'selected' : '' ?>" data-value="partnership_request" role="option" tabindex="-1" aria-selected="<?= $oldSubject === 'partnership_request' ? 'true' : 'false' ?>">Partnership Request</li>
                                <li class="dropdown-option <?= $oldSubject === 'report_issue'        ? 'selected' : '' ?>" data-value="report_issue" role="option" tabindex="-1" aria-selected="<?= $oldSubject === 'report_issue' ? 'true' : 'false' ?>">Report an Issue</li>
                                <li class="dropdown-option <?= $oldSubject === 'feedback'            ? 'selected' : '' ?>" data-value="feedback" role="option" tabindex="-1" aria-selected="<?= $oldSubject === 'feedback' ? 'true' : 'false' ?>">Feedback / Suggestion</li>
                                <li class="dropdown-option <?= $oldSubject === 'press_media'         ? 'selected' : '' ?>" data-value="press_media" role="option" tabindex="-1" aria-selected="<?= $oldSubject === 'press_media' ? 'true' : 'false' ?>">Press / Media</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="contact-page-main-section-2-form-row contact-page-main-section-2-form-row--single">
                    <div class="contact-page-main-section-2-form-row-group">
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

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="cs-button contact-page-main-section-2-submit-btn">Send Message</button>
                <?php if (Turnstile::isConfigured()): ?>
                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(Turnstile::siteKey(), ENT_QUOTES, 'UTF-8') ?>"></div>
                <?php endif; ?>
            </form>
        </section>
        <section class="cs-card cs-card--soft cs-contact-privacy-note" aria-label="Contact privacy note">
            <p>
                By contacting us, you agree that your personal data will be used solely
                to respond to your inquiry, in accordance with our
                <a class="cs-link" href="privacy-policy">Privacy Policy</a>.
            </p>
        </section>
    </main>
</div>

<script src="js/contact.js?v=<?= filemtime(__DIR__ . '/js/contact.js') ?>" defer></script>
<?php if (Turnstile::isConfigured()): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>

<?php require '../inc/footer.php'; ?>
