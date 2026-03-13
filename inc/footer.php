<footer class="footer-main">
    <div class="footer-main-wrapper">
        <div class="footer-main-content">
            <div class="footer-main-content-first">
                <div class="footer-main-content-first-logo">
                    <img src="../img/chatbothub_logo.png" alt="chatbothub logo">
                    <p>ChatbotHub</p>
                </div>

                <div class="footer-main-content-first-folow-section">
                    <p class="footer-main-content-first-folow-section-text">Follow Us</p>
                    <div class="footer-content-first-folow-section-icons">
                        <a href="#">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"
                                class="footer-content-first-folow-section-icons-facebook">
                                <path fill="#fafafa"
                                    d="M240 363.3L240 576L356 576L356 363.3L442.5 363.3L460.5 265.5L356 265.5L356 230.9C356 179.2 376.3 159.4 428.7 159.4C445 159.4 458.1 159.8 465.7 160.6L465.7 71.9C451.4 68 416.4 64 396.2 64C289.3 64 240 114.5 240 223.4L240 265.5L174 265.5L174 363.3L240 363.3z" />
                            </svg>
                        </a>
                        <a href="#">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"
                                class="footer-content-first-folow-section-icons-instagram">
                                <path fill="#fafafa"
                                    d="M320.3 205C256.8 204.8 205.2 256.2 205 319.7C204.8 383.2 256.2 434.8 319.7 435C383.2 435.2 434.8 383.8 435 320.3C435.2 256.8 383.8 205.2 320.3 205zM319.7 245.4C360.9 245.2 394.4 278.5 394.6 319.7C394.8 360.9 361.5 394.4 320.3 394.6C279.1 394.8 245.6 361.5 245.4 320.3C245.2 279.1 278.5 245.6 319.7 245.4z" />
                            </svg>
                        </a>
                        <a href="#">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
                                class="footer-content-first-folow-section-icons-linkedIn">
                                <path fill="#fafafa"
                                    d="M196.3 512L103.4 512L103.4 212.9L196.3 212.9L196.3 512zM149.8 172.1C120.1 172.1 96 147.5 96 117.8C96 103.5 101.7 89.9 111.8 79.8C121.9 69.7 135.6 64 149.8 64C164 64 177.7 69.7 187.8 79.8C197.9 89.9 203.6 103.6 203.6 117.8C203.6 147.5 179.5 172.1 149.8 172.1zM543.9 512L451.2 512L451.2 366.4C451.2 331.7 450.5 287.2 402.9 287.2C354.6 287.2 347.2 324.9 347.2 363.9L347.2 512L254.4 512L254.4 212.9L343.5 212.9L343.5 253.7L344.8 253.7C357.2 230.2 387.5 205.4 432.7 205.4C526.7 205.4 544 267.3 544 347.7L544 512L543.9 512z" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Company Section with Accordion -->
            <div class="footer-main-content-second">
                <p class="footer-main-content-second-title">Company</p>

                <button class="footer-accordion-btn" aria-expanded="false" aria-controls="company-content">
                    <span>Company</span>
                    <span class="footer-accordion-icon">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 9L12 15L18 9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </button>

                <div class="footer-accordion-content" id="company-content">
                    <a href="contact.php">Contact Us</a>
                    <a href="login.php">Login</a>
                    <a href="signUp.php">Create Account</a>
                    <a href="#">Get Featured</a>
                    <a href="advertise.php">Advertise</a>
                </div>
            </div>

            <!-- Information Section with Accordion -->
            <div class="footer-main-content-third">
                <p class="footer-main-content-third-title">Information</p>

                <button class="footer-accordion-btn" aria-expanded="false" aria-controls="information-content">
                    <span>Information</span>
                    <span class="footer-accordion-icon">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 9L12 15L18 9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </button>

                <div class="footer-accordion-content" id="information-content">
                    <a href="aboutUs.php">About Us</a>
                    <a href="affiliateDisclosure.php">Affiliate Disclosure</a>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-main-bottom">
        <p class="footer-main-bottom-Rights">© 2025 Chatbothub.com - All rights reserved.</p>
        <div class="footer-main-bottom-extra">
            <a href="#" class="footer-main-bottom-PrivacyPolicy-a">Privacy policy</a>
            <a href="#" class="footer-main-bottom-terms-a">Terms of Service</a>
        </div>
        <p class="footer-main-bottom-affiliate-disclosure">More information can be found in our <a href="affiliateDisclosure.php">
            Affiliate Disclosure.</a></p>
    </div>
</footer>

<script src="../js/footerVertical.js" defer></script>

<?php if (!$isLoggedIn): ?>
<script src="../js/visitor.js"></script>
<?php endif; ?>

</body>
</html>