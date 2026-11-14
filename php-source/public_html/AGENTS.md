# Agents Guide (Woodex)
This repository is a procedural PHP + MySQL (`mysqli`) site with a jQuery frontend and custom admin panel.

## Purpose
- Help agentic coding tools work safely and quickly in this codebase.
- Keep changes small, consistent, and production-safe.
- Provide practical run/lint/test and coding conventions.

## Stack Snapshot
- Backend: PHP (no framework), server-rendered templates.
- DB: MySQL via `mysqli` (`lbpanel/config/connection.php`).
- Frontend: Bootstrap, jQuery, plain JS (`assets/script/`).
- Web server: Apache with rewrite rules in `.htaccess`.

## Repo Map
- Public pages: `index.php`, `shop.php`, `singleproduct.php`, `servicedetail.php`, `checkout.php`.
- API endpoints: `api/` (cart/search/etc, JSON-style responses).
- Admin area: `lbpanel/`.
- Shared templates: `header/`, `footer/`.
- Static assets: `assets/`.
- Third-party (avoid edits unless asked): `vendor/`, `phpmailer/`, `lbpanel/includes/ckeditor/`.

## Cursor / Copilot Rules
- Cursor rules: none found (`.cursor/rules/` and `.cursorrules` missing).
- Copilot instructions: none found (`.github/copilot-instructions.md` missing).

## Build / Run
No project build pipeline is configured at root (`composer.json` and `package.json` absent).

### Recommended local mode (XAMPP)
1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/woodex/`.
3. Prefer Apache mode when validating friendly URLs and redirects.

### Alternative quick mode (no Apache rewrites)
```bash
php -S localhost:8000
```
Then open explicit routes like `http://localhost:8000/index.php`.

## Lint Commands
Use syntax lint for every changed PHP file.

### Lint one file
```bash
php -l path\to\file.php
```

### Lint one file (XAMPP PHP explicit)
```bash
C:\xampp\php\php.exe -l path\to\file.php
```

### Lint all PHP files (exclude third-party dirs)
```powershell
powershell -NoProfile -Command "Get-ChildItem -Recurse -File -Filter *.php | ? { $_.FullName -notmatch '\\vendor\\|\\phpmailer\\|\\lbpanel\\includes\\ckeditor\\' } | % { C:\xampp\php\php.exe -l $_.FullName }"
```

## Test Commands
No first-party automated test suite is currently configured.

If PHPUnit is introduced:
```bash
vendor\bin\phpunit
vendor\bin\phpunit tests\SomeTest.php
vendor\bin\phpunit --filter testSpecificBehavior
```

Single-test guidance:
- Prefer `--filter <testMethodName>` for one method.
- Or run one test file directly.
- Keep test scope minimal before broad test runs.

## Coding Conventions
Follow nearby file style first (mixed procedural style codebase).

### Includes, bootstrap, globals
- Start session pages with `session_start();` before output.
- Use `require_once`/`include_once` for shared files.
- For new includes, prefer `__DIR__`-based paths.
- `$conn` from `lbpanel/config/connection.php` is expected globally.

### Imports / dependencies
- Do not assume Composer autoload or PSR-4.
- Avoid introducing frameworks or major architecture changes.
- Keep dependencies minimal; prefer existing jQuery/PHP patterns.

### Input validation
- Treat `$_GET`, `$_POST`, `$_FILES`, headers as untrusted.
- Validate request method early in handlers.
- Guard missing keys with `isset()` / null-coalescing.
- Cast external numeric inputs early (`(int)`, `(float)`).

### Database (`mysqli`)
- Use prepared statements for user-influenced values.
- Bind correct parameter types (`i`, `d`, `s`, `b`).
- Check statement/query success explicitly.
- Close statements (`$stmt->close()`).
- Keep charset `utf8mb4`.

### Output encoding / XSS
- Escape untrusted output: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- For multiline text: `nl2br(htmlspecialchars(...))`.
- Avoid raw HTML rendering for untrusted values.
- In jQuery, prefer `.text()` over `.html()` for untrusted text.

### Error handling / responses
- JSON APIs should return consistent shape (e.g. `status`, `message`).
- Set `header('Content-Type: application/json');` for JSON responses.
- Use meaningful status codes (`400`, `404`, `405`, `500`).
- After `header('Location: ...')`, always call `exit;`.
- Admin UX usually uses `$_SESSION['success']` / `$_SESSION['error']` flash messages.

### Naming conventions
- Keep file-local naming style; avoid mass renames.
- Variables may be mixed snake_case/camelCase; preserve local style.
- Functions are generally verb/helper style.
- Keep route filenames stable (`.htaccess` depends on them).

### Types / comparisons
- Prefer strict comparisons (`===`, `!==`).
- Normalize decoded JSON values (`?: []`, null checks).
- Avoid notices by guarding array/object access.

### Formatting
- Match surrounding indentation (2-4 spaces).
- Use braces on multi-line blocks.
- Prefer early returns to reduce nesting.
- Add comments only for non-obvious logic.

### Frontend JS
- Maintain jQuery compatibility and existing event patterns.
- Prefer delegated events: `$(document).on('click', '.selector', handler)`.
- Preserve response contracts expected by existing AJAX code.

### HTML/CSS
- Keep template edits minimal and localized.
- Reusable CSS should go to `assets/style.css` when appropriate.
- Keep Bootstrap usage consistent with nearby markup.

## Routing and Redirect Notes
- Friendly routes and canonical behavior live in root `.htaccess`.
- Verify rewrites when changing route files or URL patterns.
- Project includes dynamic SEO redirects (`redirect.php` + `url_redirects` table); preserve 301 behavior.

## Security / Secrets
- Never expose DB credentials from `lbpanel/config/connection.php`.
- Do not commit secrets (`.env`, SMTP/API keys, tokens).
- Avoid logging sensitive values in responses/errors.
- Do not edit third-party libraries unless explicitly requested.

## Agent Delivery Checklist
Before finishing:
1. Lint all touched PHP files.
2. Smoke-test impacted flows (shop, cart, checkout, admin CRUD).
3. Re-check URL rewrite behavior for route-related edits.
4. Keep diffs scoped; avoid unrelated refactors.

## Frequently Touched Paths
- `lbpanel/config/connection.php`
- `lbpanel/php/*.php`
- `lbpanel/category-management/*.php`
- `api/cart/*.php`
- `api/search/suggest.php`
- `assets/script/cart.js`
- `assets/style.css`

## Non-Goals
- No framework migration in routine tasks.
- No casual renaming of public route files.
- No app-level behavior changes inside vendor/third-party code.
- No large architectural rewrites unless requested.
