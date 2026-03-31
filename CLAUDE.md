# CLAUDE.md

## Project Overview
ChatbotScout — a chatbot directory/listing platform. Users can browse, submit, and review AI chatbots. Admins manage submissions, reviews, and promotions.

Domain: chatbotscout.com

Built with PHP + Node.js on XAMPP (Windows, local dev).

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP (OOP, no framework) |
| Frontend | HTML, CSS, Vanilla JS |
| Database | MySQL via PDO (`d67598_websitedb`) |
| Payments | Stripe (`stripe/stripe-php` + webhooks) |
| Email | PHPMailer — Gmail API |
| Rate Limiting | Redis (primary) with MySQL fallback |
| MCP | `mcp/supabase-server.js` (CommonJS) |

---

## Project Structure

```
api/          — JSON API endpoints (called by frontend JS fetch)
app/          — Server-side action handlers (form submissions, auth flows)
classes/      — PHP classes (Database, User, Session, Email, RateLimiter, AuditLog, Visitor)
config/       — Config files (app, database, stripe, gmail, redis)
inc/          — Shared PHP includes (bootstrap, header, footer, errorHandler)
css/js/img/   — Frontend assets
mcp/          — MCP servers (supabase-server.js)
cron/         — Cron jobs
logs/         — Log files (error.log)
public/       — Public-facing pages
scripts/      — Utility scripts
tools/        — Dev tools (seedTestData.php)
WebsiteDB/    — Database schema backup (Base.sql)
```

### Key API Endpoints (`api/`)
- `getChatbots.php` — main chatbot listing with filtering/pagination
- `submitChatbot.php` / `submitReview.php` — user submissions
- `stripeWebhook.php` — Stripe payment events
- `getAdminData.php` / `getAdminSubmissions.php` — admin panel data
- `toggleFavorite.php`, `blockUser.php`, `identify.php`, `recordVisit.php`

### Key App Handlers (`app/`)
- `auth.php` — login/register/logout helpers
- `guard.php` — route protection
- `stripeActions.php` — payment flows
- `adminSubmissionActions.php` / `adminReviewActions.php` / `adminSettingsActions.php`
- `profileActions.php`, `advertiseActions.php`, `contactActions.php`

### PHP Classes (`classes/`)
- `Database.php` — Singleton PDO connection
- `User.php` — user management and registration
- `Session.php` — session creation, validation, cleanup
- `Email.php` — email sending via PHPMailer
- `RateLimiter.php` — Redis/DB rate limiting
- `AuditLog.php` — admin audit trail
- `Visitor.php` — visitor tracking

---

## Development Environment
- OS: Windows 11
- Local server: XAMPP
- Web root: `C:\xampp\htdocs\MainWebsite`
- MySQL DB name: `d67598_websitedb` (password: blank, user: root)

## Local Dev URL
`http://localhost/MainWebsite/`

## Common Commands
```bash
# Install Node dependencies
npm install

# Install PHP dependencies
composer install

# Stripe webhook testing (requires Stripe CLI)
stripe listen --forward-to localhost/MainWebsite/api/stripeWebhook.php

# Seed test data
php tools/seedTestData.php
```

---

## Bootstrap Globals

Every page/endpoint includes `inc/bootstrap.php`, which sets these globals:

| Variable | Type | Description |
|---|---|---|
| `$isLoggedIn` | bool | Whether user has a valid session |
| `$user` | array\|null | Full user data including `is_admin` flag |
| `$userRole` | string | `'admin'` or `'user'` |
| `$visitorId` | string\|null | Visitor tracking ID (if GDPR consent given) |
| `$page` | string | Current page basename from `$_SERVER['PHP_SELF']` |

Bootstrap also: starts session, registers class autoloader, validates session token from `session_token` cookie, checks GDPR consent, runs visitor tracking, and may clean expired sessions (1% chance per request).

---

## Coding Conventions

### PHP Naming
- Classes: `PascalCase` — in `classes/`
- Action files: `camelCaseActions.php` — in `app/`
- API endpoints: `camelCase.php` — in `api/`
- Config files: `lowercase.php` — in `config/`
- Variables & functions: `$camelCase` / `camelCase()`
- DB tables & columns: `snake_case`

### PHP Rules
- Always use `mb_strlen()` not `strlen()` (multi-byte string support, UTF-8)
- UUIDs generated in PHP with `random_bytes()` via `generateUUID()` in bootstrap
- Config files return associative arrays, loaded fresh per request (no caching layer)
- `BOOTSTRAP_LOADED` constant is set by bootstrap — do not include bootstrap files without it being set

### Database Naming
- IDs: UUID v4 (36-char string), stored as `VARCHAR(36)`
- Status column values: `active`, `pending`, `deleted`, `inactive`, `blocked`, `pending_verification`
- Soft deletes: `deleted_at TIMESTAMP NULL` — always include `deleted_at IS NULL` in queries
- Timestamps: `created_at`, `updated_at`, `deleted_at`

---

## Standard API Endpoint Pattern

Every file in `api/` follows this structure:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require '../inc/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Auth check (if needed)
if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in.', 'redirect' => '/login.php']);
    exit;
}

// Input
$data = json_decode(file_get_contents('php://input'), true) ?? [];

// Validate inputs...

try {
    $db = Database::getInstance()->getConnection();
    // ... operations
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    error_log('Error in ' . basename(__FILE__) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
```

**Response shape:** `{ success: bool, message?: string, data?: any, redirect?: string }`

### Input Validation Patterns
- Email: `filter_var($email, FILTER_VALIDATE_EMAIL)`
- URL: `filter_var($url, FILTER_VALIDATE_URL)`
- UUID: `/^[0-9a-f\-]{36}$/i` regex
- Enum: `in_array($value, ['opt1', 'opt2'], true)` (strict)
- Strings: `trim()` + `mb_strlen()` for length checks

---

## Database Patterns

```php
$db = Database::getInstance()->getConnection(); // always singleton

// Soft deletes — always include in queries
WHERE deleted_at IS NULL

// Dynamic IN() placeholders
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("SELECT * FROM table WHERE id IN ({$placeholders})");
$stmt->execute($ids);
```

PDO config: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES = false`.

---

## Authentication & Guards

```php
// app/auth.php helpers
login($userData, $rememberMe = false)   // Creates DB session + sets cookie
logout()                                 // Destroys session
isLoggedIn()                             // Returns bool from $_SESSION
getCurrentUser()                         // Returns user array from $_SESSION

// app/guard.php
requireLogin()          // Redirects to login if not authenticated
requireRole('admin')    // Checks is_admin flag, redirects if unauthorized
```

Session token: 32 random bytes (hex), stored in `user_sessions` table and `session_token` cookie (httpOnly). Session validation checks account status — suspended/blocked users will have `$isLoggedIn = false` even with a valid cookie.

---

## JavaScript Patterns

All JS files use IIFE pattern:
```javascript
(function () {
    'use strict';

    const state = { currentPage: 1, filters: {} };

    // DOM refs cached once
    const listEl = document.getElementById('list');

    // Fetch pattern
    async function loadData() {
        const response = await fetch('/api/endpoint.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: value })
        });
        const result = await response.json();
        if (!result.success) {
            if (result.redirect) window.location.href = result.redirect;
            return;
        }
        // use result.data
    }

    // XSS prevention — always use these when inserting user content into DOM
    function escapeHtml(str) { ... }
    function escapeAttr(str) { ... }

    document.addEventListener('DOMContentLoaded', () => { ... });
})();
```

- Use event delegation on parent elements rather than binding to every child
- Show/hide loading states while fetching
- Validate forms on blur (real-time) and on submit

---

## MCP Servers

**Local MySQL MCP** (`mcp__d67598_websitedb__*`) — primárny MCP, queries `d67598_websitedb` priamo
- `mcp__d67598_websitedb__query` — vykonáva SQL dotazy
- `mcp__d67598_websitedb__get_tables` — zobrazuje tabuľky

**Supabase MCP** (`mcp__claude_ai_Supabase__*`) — via claude.ai integration (projekt prázdny, nepoužíva sa)

**Context7 MCP** (`mcp__context7__*`) — dokumentácia knižníc, vždy použiť pred písaním kódu s externe knižnicou

**File:** `mcp/supabase-server.js` (CommonJS) — lokálny MCP server pre d67598_websitedb

---

## Rate Limiting

Class: `RateLimiter` — Redis primary, MySQL DB fallback (auto-detected).

```php
$limiter = new RateLimiter();
$limiter->check($action, $ipAddress, $email);          // Returns bool
$limiter->recordFailedAttempt($action, $ip, $email);   // Returns remaining lockout secs
$limiter->reset($action, $ip, $email);
```

Configured in `config/app.php` under `rate_limit` key. Tracks both IP and email independently. Actions: `login`, `registration`, `password_reset`, `contact`.

---

## Email

- **Production:** Gmail API with OAuth2 — credentials in `config/gmail.php`
- **Development:** Mailtrap SMTP — credentials in `config/mailtrap.php`
- Methods in `Email` class follow pattern: `send<Action>()` e.g. `sendVerification()`, `sendPasswordReset()`

---

## Architecture Notes
- No PHP framework — custom OOP with manual routing via `app/guard.php`
- API endpoints return JSON, consumed by frontend JS fetch calls
- MySQL (`d67598_websitedb`) is the primary app database; Supabase integration currently unused
- Stripe webhooks handled in `api/stripeWebhook.php`
- MCP server (`mcp/supabase-server.js`) connects Claude to local MySQL (`d67598_websitedb`) for DB queries during development
- No `.env` file — all credentials hardcoded in `config/` files (local dev only)
- `WebsiteDB/Base.sql` contains the full database schema dump
- Cron: `cron/updatePopularTags.php` runs daily to update tag rankings


## Git Workflow
- Active branch: `dev`
- Main branch: `main`
- Merge `dev` → `main` before production deployment

## Collaboration
- Discuss architecture and design decisions before implementing
- For simple bug fixes and small CSS/JS changes: implement directly
- For new features, multi-file changes, or DB schema changes: discuss approach first

## Agent Plugins
Always use the available agent plugins (skills) for relevant tasks — even if it takes longer or uses more context. Quality and correctness are more important than speed.

| Plugin | When to use |
|---|---|
| `code-review:code-review` | After every non-trivial code change (multiple files, logic changes) |
| `everything-claude-code:plan` | Before implementing any feature touching multiple files |
| `everything-claude-code:security-review` | After changes to auth, input handling, API endpoints, or session logic |
| `feature-dev:feature-dev` | When building a new page, feature, or system from scratch |
| `claude-md-management:claude-md-improver` | When CLAUDE.md feels outdated or after major project changes |

## CSRF
`js/csrf.js` je načítavaný na každej stránke cez `inc/header.php`. Automaticky pridáva CSRF token do každého fetch POST requestu. Token je generovaný v PHP a vložený do `<meta name="csrf-token">`.

Pri písaní nových fetch volaní **nie je potrebné manuálne pridávať CSRF** — `csrf.js` to robí automaticky cez fetch interceptor.

## Context7
Always use the Context7 MCP (`mcp__context7__resolve-library-id` + `mcp__context7__query-docs`) when working with any external library or framework (Stripe, PHPMailer, Supabase, mysql2, MCP SDK, etc.) to get up-to-date documentation before writing code.

## Cache Busting
All JS and CSS assets must include a `filemtime` query parameter to prevent browser caching issues after deploys:

```php
<!-- CSS in inc/header.php (path relative to inc/) -->
<link rel="stylesheet" href="css/main.css?v=<?= filemtime(__DIR__ . '/../public/css/main.css') ?>">

<!-- JS in public/*.php (path relative to public/) -->
<script src="js/homePage.js?v=<?= filemtime(__DIR__ . '/js/homePage.js') ?>"></script>
```

Note: Some pages load their own CSS directly (e.g. `profile.php` loads `profileReviews.css`) instead of via `header.php` — these also need `filemtime`.

## Deploy Workflow
Production is on chatbotscout.com hosted via HostCreators. Files are uploaded manually via the hosting file manager after merging `dev` → `main`. Always push to `main` before uploading.

## Styling in CSS
When working with bold text always choose between `font-family: outfit-bold`, `outfit-medium`, `outfit-semibold`. Never use `font-weight`.

## Localhost & Production Compatibility
All code must work on **both** localhost (XAMPP) and production (chatbotscout.com). Never hardcode environment-specific values. Use config files or environment detection where behaviour differs (e.g. DB credentials, email driver, cookie `secure` flag). Always test logic paths that apply to both environments.

## Business Logic
The main purpose is to get users to click affiliate links in `chatbotShowcase.php` or `favouriteShowcase.php` — this sets cookies so the site knows the user came from ChatbotScout, generating affiliate revenue if they buy a chatbot subscription. Future: paid advertising system with monthly subscriptions for promoted chatbot listings.