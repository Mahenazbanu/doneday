# DoneDay — Security Documentation

This document describes how DoneDay protects users and data, and its
known limitations. It reflects the **actual implementation** — verify
claims by reading the referenced files.

---

## 1. Authentication

- Passwords are hashed with `password_hash()` (bcrypt, automatic salt and
  cost factor) and verified with `password_verify()` — never stored or
  logged as plain text. (register.php, login.php)
- Registration enforces: name length, valid email format, password ≥ 8
  characters, password confirmation match.
- Login uses **one generic error message** ("Incorrect email or
  password.") so the endpoint cannot be used to enumerate which emails
  are registered.
- On successful login the session id is rotated with
  `session_regenerate_id(true)` — blocks session fixation.
- Logout clears `$_SESSION`, expires the cookie, and destroys the
  session (logout.php).

### Brute-force resistance (login.php)
- Session-based throttle: after **5 failed attempts** the session is
  locked for **15 minutes**.
- **Known limitation:** because DoneDay must run on plain shared hosting
  (no cron, no Redis), the counter lives in the session. An attacker who
  rotates cookies gets a fresh counter. This stops casual password
  guessing and scripted spray against one account from a single client,
  but is not a substitute for network-level rate limiting. On a VPS or
  host that supports it, add fail2ban or mod_evasive — see §9.

## 2. Authorization / IDOR

Every query that touches user-owned data includes `user_id`, taken **only**
from the server-side session — never from a request parameter:

- `api/tasks.php → owned_task()` loads a task with
  `WHERE id = ? AND user_id = ?`; a missing row returns a 404-style JSON
  error. Tested: requesting another user's task id returns
  `{"success":false,"message":"That task could not be found."}` and
  changes nothing.
- Settings updates run `WHERE user_id = ?` and only ever affect the
  logged-in user's row.
- Dashboard/history queries always filter by session user.
- There is no endpoint that accepts a `user_id` parameter at all.

**Test evidence:** changing `data-task-id` to another user's task id in
the browser and clicking delete/move/complete → server responds
"not found"; the victim's task is untouched (ownership check is
server-side).

## 3. SQL injection

- 100% of queries use PDO prepared statements with bound parameters
  (`PDO::ATTR_EMULATE_PREPARES => false`, real server-side prepares).
- No user input is ever concatenated into SQL. Dynamic fragments are
  constants (status values) or validated against allowlists (priority).
- Task ids are cast to int before binding; dates must match
  `YYYY-MM-DD` or are rejected/null.

## 4. XSS

- All server-rendered user content passes through `e()`
  (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`): task titles,
  descriptions, categories, user name/email.
- JavaScript inserts user data via `textContent` or an `escapeHtml`
  helper before `innerHTML` — nothing user-supplied is written raw.
- The `quotes.js` strings are developer-authored constants, not user data.
- **Test evidence:** creating a task titled `<script>alert(1)</script>`
  stores and re-renders it as inert text; no script executes on any
  view (dashboard, later list, history, edit modal).

## 5. CSRF

- Every state-changing route verifies `csrf_verify()`: token stored in
  the PHP session, sent as a hidden form field or `X-CSRF-Token`
  header, compared with `hash_equals` (timing safe).
- Covered: register, login, settings, and all 6 task API actions.
  (The original MVP shipped tokens on the task API and settings but
  **not** on login/register — this was found in the audit and fixed.)
- **Test evidence:** POST to `/api/tasks.php` with a valid session but a
  missing/altered `csrf` parameter → 403
  `{"success":false,"message":"Session expired. Refresh and try again."}`.

## 6. Session security

- Cookie: `HttpOnly`, `SameSite=Lax`, and `Secure` **only when HTTPS is
  actually in use** — so local XAMPP (plain HTTP) keeps working
  unchanged while production gets the hardened cookie.
- Session id rotated at login; session destroyed at logout; 30-minute
  default lifetime via PHP defaults.
- `current_user()` re-fetches the user from the database on each request
  (no stale cached identity).
- Auth guard (`require_login`) on dashboard, history, settings; the API
  additionally returns 401 JSON for unauthenticated AJAX.

## 7. Configuration security

- Credentials live only in `config/database.php` (gitignored).
- `config/`, `includes/`, and `database/` each contain an `.htaccess`
  denying all direct HTTP access, so SQL dumps and source files cannot
  be downloaded even if PHP were misconfigured.
- Root `.htaccess` disables directory listing.
- Database connect failures are logged to `error_log()`; the browser
  sees only "Unable to connect to the database."

## 8. HTTP security headers (set in includes/auth.php)

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN` (clickjacking)
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- Content-Security-Policy: `default-src 'self'` with `'unsafe-inline'`
  for script/style (DoneDay ships small inline scripts: theme bootstrap,
  DONEDAY config). No external origins are allowed at all — the app
  makes zero third-party requests, which is also its privacy posture.

## 9. Production recommendations

1. Enforce HTTPS (free Let's Encrypt) — the `Secure` cookie flag then
   activates automatically.
2. Add `fail2ban` or `mod_evasive` for network-level login throttling.
3. Consider moving DB credentials to environment variables on hosts that
   support them (the current single config file is the beginner-friendly
   default and is already gitignored).
4. Keep PHP updated; DoneDay requires nothing beyond PHP 8 + PDO-MySQL.
5. Set `display_errors = Off` in production php.ini (DoneDay never prints
   internals regardless).

## 10. Privacy

- No analytics, no ads, no third-party APIs, no external requests of any
  kind. The motivational system is a local file (quotes.js).
- User data: name, email (login identifier), hashed password, tasks.
  Nothing else is collected or stored.
