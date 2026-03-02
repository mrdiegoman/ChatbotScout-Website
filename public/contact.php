<?php
require '../inc/bootstrap.php';
require '../inc/header.php';
?>

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
            <form>
                <div class="contact-page-main-section-2-form-row">
                    <div class="contact-page-main-section-2-form-row-group">
                        <label for="firstName">First Name <span class="required">*</span></label>
                        <input type="text" id="firstName" placeholder="Please enter first name" required>
                    </div>
                    <div class="contact-page-main-section-2-form-row-group">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                        <input type="text" id="lastName" placeholder="Please enter last name" required>
                    </div>
                </div>

                <div class="contact-page-main-section-2-form-row">
                    <div class="contact-page-main-section-2-form-row-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" id="email" placeholder="Please enter your email" required>
                    </div>
                    <div class="contact-page-main-section-2-form-row-group">
                        <label for="subject">Subject <span class="required">*</span></label>
                        <div class="dropdown-wrapper">
                            <div class="dropdown-select">
                                <div class="dropdown-selected">
                                    Please select a subject topic
                                </div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#232127"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="dropdown-droparrow-icon">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                            <ul class="dropdown-options">
                                <li class="dropdown-option"
                                    data-value="popularity">General Question</li>
                                <li class="dropdown-option"
                                    data-value="price">Affiliate Inquiry</li>
                                <li class="dropdown-option"
                                    data-value="alphabetical">Partnership Request</li>
                                <li class="dropdown-option"
                                    data-value="rating">Report an Issue</li>
                                <li class="dropdown-option"
                                    data-value="number-of-ratings">Feedback / Suggestion</li>
                                    <li class="dropdown-option"
                                    data-value="number-of-ratings">Press / Media</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="contact-page-main-section-2-form-row">
                    <div class="contact-page-main-section-2-form-row-group" style="flex:1;">
                        <label for="message">Message <span class="required">*</span></label>
                        <textarea maxlength="400" wrap="soft" id="message" placeholder="Here write your message..." required></textarea>
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

<script src="../js/dropdownOptions.js" defer></script>

<?php
require '../inc/footer.php';
?>