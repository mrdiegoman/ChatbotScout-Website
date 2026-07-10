General

Work as a careful senior web developer.

Before changing code:

inspect the relevant files and existing project structure
read package.json and use the package manager matching the lockfile
reuse existing components, styles, utilities, and conventions
make only changes related to the requested task

Do not change the framework, build system, package manager, or project architecture unless explicitly requested.

Never expose or commit secrets, API keys, tokens, or .env files.

Full-access safety rules

When the sandbox is disabled or full privileges are granted, treat the extra access as sensitive and stay inside this project unless the user explicitly asks otherwise.

Do not run destructive filesystem or git commands unless the user clearly asks for that exact action. This includes `Remove-Item -Recurse`, `rm -rf`, `git reset --hard`, `git clean`, force checkout, deleting folders, mass moving files, or overwriting unrelated work.

Do not edit files outside the project, system configuration, XAMPP/Apache/PHP configuration, global tooling, user profile files, or Codex configuration unless the user explicitly asks for that specific change.

Do not install, update, or remove dependencies, change lockfiles, switch package managers, or alter build/deploy scripts unless the task requires it and the user has approved the scope.

Do not perform broad rewrites, large refactors, formatting sweeps, or architecture changes when a focused patch can solve the request.

Do not modify production data, run destructive database operations, trigger real payments, send real emails/messages, or call live external services for side effects unless explicitly requested.

If a command or edit could affect unrelated code, user work, secrets, system stability, databases, deployment, or irreversible state, pause and ask first.

Implementation
Write clear, maintainable code consistent with the existing project.
Use TypeScript when the project uses TypeScript.
Avoid any, unnecessary abstractions, duplicate components, and unrelated refactoring.
Do not add a dependency when the feature can reasonably be implemented with existing tools.
Preserve responsive behavior and accessibility.
Use semantic HTML, labels for form fields, keyboard-accessible controls, visible focus states, and appropriate image alt text.
When using colors in CSS, use an existing color variable from the central color list in `public/css/main.css`. If a genuinely new color is needed, add it to the color list in `public/css/main.css` first and reference it through a variable instead of hardcoding the color in another CSS file.
When creating or changing a popup/modal, use the project's shared close button pattern: a top-right `modal-close` cross button with the same size, alignment, circular hover background, accessible label, and close-only behavior. Do not create one-off X buttons or close popups by clicking outside unless the user explicitly asks for that behavior.
Handle relevant loading, error, empty, disabled, and success states.
Do not claim something works unless it was actually verified.

Project-specific consistency rules

Keep HTML, CSS, and JavaScript responsibilities separated:

- Do not add inline event handlers such as `onclick`, `onerror`, `onchange`, or `javascript:` URLs.
- Do not add executable inline app scripts just to pass PHP values to JavaScript. Prefer `data-*` attributes on a relevant DOM node and read them from the external JS file.
- JSON-LD structured data and the external Google Analytics script with a CSP nonce are acceptable exceptions.
- Do not add one-off inline `style="..."` attributes for page UI. Use CSS classes, `[hidden]`, or project CSS variables.
- Email HTML is an exception: inline styles in `classes/Email.php` and `scripts/sendMarketingEmail.php` may be necessary for Gmail/Outlook compatibility.

For UI visibility and state:

- Prefer the native `hidden` attribute for loading, empty, success, error, overlay, bottom bar, and button-loader visibility.
- Add explicit `[hidden] { display: none !important; }` rules in the relevant page CSS when a component has CSS that could override native hidden behavior.
- Do not toggle `element.style.display` for normal app state. Use `element.hidden = true/false` through a small helper such as `setHidden(element, hidden)`.
- Use CSS state classes for visual transitions, for example `is-loading`, `is-removing`, `is-search-loading`, `is-ascending`, and keep transitions in CSS.
- Runtime layout calculations are allowed only when the value is inherently measured in the browser, for example carousel transforms, custom scrollbar thumb width/position, accordion max-height, scroll lock body padding/overflow, or stable chip widths.

For shared browser behavior:

- Use `public/js/imageFallback.js` with `data-fallback-src` instead of inline image `onerror`.
- Use `public/js/avatarLoader.js` for lazy avatar loading from `data-src`.
- Keep Google Analytics consent bootstrapping in `public/js/analyticsConsent.js` and `public/js/analyticsInit.js`; do not reintroduce inline analytics bootstrap scripts.
- If CSS targets broad SVG selectors in the header or sidebar, ensure `svg[hidden]` still stays hidden. This previously broke the email eye toggle.

For PHP/API behavior:

- API endpoints should return consistent JSON with a `success` field and appropriate HTTP status codes. Avoid raw HTML errors in API responses.
- Validate inputs early and keep error messages safe for users.
- Use CSRF protection for state-changing forms/actions. Logout must remain POST + CSRF protected.
- Reuse `ClientIpResolver` for client IP detection; do not read proxy headers ad hoc.
- Reuse the existing rate limiter configuration instead of inventing endpoint-local throttling.
- Never use `die()`/raw exits for normal application errors in user-facing or API code. CLI scripts and webhooks may still terminate intentionally where appropriate.
- Do not log or expose secrets, tokens, passwords, reset links, verification links, Stripe client secrets, or personal data beyond what is required.
- Keep password requirements visually neutral while the user types. Do not reveal per-character composition through live rule checkmarks, colors, labels, or strength details; validate with one generic client-side error on blur/submit and keep `PasswordPolicy` authoritative on the server.

For key flows touched by the cleanup:

- Header/profile email toggles must show exactly one eye icon at a time.
- The initial signup verification email must leave `verification_email_sent_at` empty. Only an explicit resend may set it and start the server-side cooldown; show sending/countdown state only once in the resend button.
- Password reset tokens are single-use. Consume the token in the same atomic update that changes the password, reject used/expired tokens before rendering the form, reuse the verification failure component for that invalid state, and revoke all existing user sessions after a successful reset.
- Showcase and favourites pages must keep loading, empty state, pagination bottom bar, show-more, filters, and rating messages synchronized through `hidden`.
- Keep sort defaults aligned across showcase UI and APIs: alphabetical starts A-Z, price starts low-high, while popularity, rating, rating count, and date added start descending. The sort arrow points down for every default direction and flips up only when the user reverses that sort.
- Favourites `renderChatbots(chatbots)` must not reference an `append` variable.
- Checkout and advertise page configuration should stay in `#checkoutConfig` and `#advertiseConfig` datasets, not `window.checkoutData` or `window.advData` inline scripts.
- Contact flash messages should stay in `data-contact-*` attributes, not `window._contact*` inline globals.
- Review rating meters should avoid inline width styles; use `data-rating`, CSS rules, or semantic elements such as `progress`.

Before finishing broad cleanup work:

- Run PHP lint across all PHP files with `C:\xampp\php\php.exe -l`.
- Run `node --check` across `public/js/*.js`.
- Run `git diff --check`.
- Scan for inline app code with `rg -n "<script[^>]*nonce|\son[a-z]+\s*=|javascript:" inc public classes scripts -S`.
- For the large audit cleanup, keep `TESTOVACI_CHECKLIST_A_ZMENY.md` updated when new changed areas are added.
Context7 MCP

Use Context7 when implementing or changing version-dependent APIs from external frameworks or libraries.

Examples include:

React, Next.js, Tailwind, component libraries
authentication, database, form, validation, and testing libraries
unfamiliar or recently changed APIs

First detect the installed package version. Query only documentation relevant to the current task.

Do not use Context7 for basic syntax or project-local code that can be understood from the repository.

Figma MCP

Use Figma MCP when:

the user provides a Figma link
the task asks to implement or compare a Figma design
the task explicitly asks to create or edit something in Figma

When implementing from Figma:

inspect the referenced frame or node
reuse the project's existing components and design tokens
use Figma as the source of visual intent, not application architecture
implement responsive behavior rather than copying fixed coordinates
retrieve and reuse provided assets when appropriate

When creating a design in Figma:

create editable native layers
use Auto Layout and reusable components
preserve the original design unless explicitly asked to overwrite it

If the Figma content is inaccessible, report it instead of guessing.

Playwright MCP

Use Playwright after changes affecting rendered UI, navigation, forms, interactions, responsive layout, or browser behavior.

Verify as relevant:

the affected page loads
the main user flow works
there are no new browser console errors
important controls work with keyboard and mouse
the layout works on mobile and desktop

Prefer localhost or an explicitly provided preview environment.

Never perform real payments, destructive actions, or modify production data.

For very small non-UI changes, Playwright is not required.

Verification

After implementation, run the relevant scripts available in package.json, such as:

lint
type checking
targeted tests
build

Do not invent script names. Do not suppress errors merely to make checks pass.

For UI tasks, also validate the affected flow with Playwright when possible.

Final response

Briefly report:

what was changed
main files changed
checks actually run and their results
MCP tools actually used
anything that could not be verified

Never claim that an MCP tool or test was used when it was not.
