<?php
require '../inc/bootstrap.php';
require '../inc/header.php';
?>

<div class="cs-page cs-affiliate-page">
    <section class="cs-hero cs-affiliate-hero" aria-labelledby="affiliate-title">
        <div class="cs-hero-inner">
            <div class="cs-hero-copy">
                <h1 id="affiliate-title">Affiliate Disclosure</h1>
                <p class="cs-hero-lead">
                    We want visitors to clearly understand how affiliate links work on
                    ChatbotScout and how they help support the website.
                </p>
                <div class="cs-hero-actions">
                    <a class="cs-button cs-button--green" href="chatbotShowcase.php">Compare chatbots</a>
                </div>
            </div>
        </div>
    </section>

    <main class="cs-main cs-affiliate-main">
        <article class="cs-affiliate-sheet" aria-labelledby="affiliate-summary-title">
            <header class="cs-affiliate-intro">
                <p class="cs-affiliate-label">Disclosure summary</p>
                <h2 id="affiliate-summary-title">Some links on ChatbotScout are affiliate links.</h2>
                <p>
                    Transparency is important to us, so we keep this page simple. Some
                    links may lead to chatbot providers, tools, or services that are
                    relevant to our visitors.
                </p>
            </header>

            <div class="cs-affiliate-highlight" role="note">
                <span class="cs-affiliate-highlight-dot" aria-hidden="true"></span>
                <p>
                    <strong>No extra cost for you.</strong> If you click one of these
                    links and make a purchase or sign up for a service, we may receive a
                    small commission from the provider.
                </p>
            </div>

            <section class="cs-affiliate-section" aria-labelledby="affiliate-how-title">
                <h3 id="affiliate-how-title">How it works</h3>
                <ol class="cs-affiliate-flow">
                    <li>
                        <span class="cs-affiliate-flow-number">1</span>
                        <p>You click a relevant link from one of our pages.</p>
                    </li>
                    <li>
                        <span class="cs-affiliate-flow-number">2</span>
                        <p>A purchase or sign-up may generate a small commission.</p>
                    </li>
                    <li>
                        <span class="cs-affiliate-flow-number">3</span>
                        <p>The commission helps cover hosting, research, and content work.</p>
                    </li>
                </ol>
            </section>

            <section class="cs-affiliate-section" aria-labelledby="affiliate-principles-title">
                <h3 id="affiliate-principles-title">Our recommendation promise</h3>
                <p>
                    Recommendations are never based on commission rates. We only recommend
                    products and services that are relevant to our audience, with the goal
                    of helping visitors find useful chatbot solutions for their websites.
                </p>
            </section>

            <footer class="cs-affiliate-footer" aria-labelledby="affiliate-contact-title">
                <div>
                    <h3 id="affiliate-contact-title">Questions about an affiliate relationship?</h3>
                    <p>
                        If you want more detail about how a specific link works, feel free
                        to contact us.
                    </p>
                </div>
                <a class="cs-button" href="contact.php">Contact us</a>
            </footer>
        </article>
    </main>
</div>

<?php
require '../inc/footer.php';
?>
