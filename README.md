**Submitted by:** Shaikh Mahenazbanu Mustufamiya 
**Enrollment No.**: 2402104144
**Mode**: Individual (Solo) Project
**Programme:**· BCA (Hons.),Third Year 
              · School of Computer Application 
              · Shreyarth University

# DoneDay

A lightweight, zero-cost task manager built around one idea:
**you don't have to do everything — just focus on what matters today.**

Traditional todo apps encourage accumulation. DoneDay encourages a
small, honest daily list with a configurable limit, a gentle
Today/Later split, calm context-aware motivational messages, and a
proper light/dark theme. No frameworks, no Node.js, no paid APIs,
no external services, no tracking.

Built with **HTML5 + CSS3 + vanilla JavaScript + PHP (PDO) + MySQL**.

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (PHP 8+ and MySQL/MariaDB), or any Apache + PHP + MySQL host
- Any modern browser

---

## Local setup with XAMPP

1. **Install XAMPP** from apachefriends.org.
2. **Start Apache and MySQL** in the XAMPP Control Panel.
3. **Copy the project** into your web root:
   `C:\xampp\htdocs\doneday\` (Windows) or `/opt/lampp/htdocs/doneday/` (Linux).
4. **Create the database**: open `http://localhost/phpmyadmin` →
   **Import** → choose `database/doneday.sql` → **Go**.
   (This creates the `doneday` database, tables, indexes, and demo data.)
5. **Check credentials** in `config/database.php` if your MySQL isn't
   the XAMPP default (user `root`, empty password).
6. **Open** `http://localhost/doneday/`.

**Demo login:** `demo@doneday.test` / `demo1234`

### Upgrading an existing DoneDay database (adds the theme setting)

```sql
ALTER TABLE settings
    ADD COLUMN theme VARCHAR(10) NOT NULL DEFAULT 'system'
    AFTER motivation_enabled;
```
Existing rows get `system` automatically — no data is touched.

---

## Features

- **Today / Later / Completed** — a clear, gentle separation; moving a
  task to Later is framed as planning, never failure
- **Daily task limit** — 3 / 5 / 7 / custom (max 20); reaching it opens a
  friendly "want to move one to Later?" dialog (Move & add / Add anyway /
  Cancel), never a hard block
- **Daily reset** — unfinished yesterday-Today tasks quietly move to
  Later at the next login/dashboard load; nothing is deleted
- **Progress** — one quiet bar and an honest "n / m completed" line
- **Motivational messages** — ~100 local, context-aware messages (no API,
  no cost), anti-repeat, toggleable in Settings
- **Themes** — System (follows the device), Light, Dark; dark palette
  uses soft-black surfaces and AA+ contrast; instant switch, no reload
- **Accessible** — skip link, keyboard-complete flow, visible focus,
  ≥4.5:1 text contrast, reduced-motion support, no color-only meaning

---

## Project structure

```
doneday/
├── index.php            # entry point (redirects)
├── login.php            # login + brute-force throttle
├── register.php         # registration
├── dashboard.php        # Today + Later, quick-add, modals
├── history.php          # completed-task history (restore/delete)
├── settings.php         # daily limit, theme, motivation toggle, account
├── logout.php
├── .htaccess            # no directory listing
├── config/
│   ├── .htaccess        # block direct HTTP access
│   └── database.php     # PDO connection (gitignored)
├── includes/
│   ├── .htaccess        # block direct HTTP access
│   ├── auth.php         # hardened sessions + security headers
│   ├── functions.php    # escaping, CSRF, settings, rollover helpers
│   ├── header.php       # shared header + theme bootstrap
│   ├── footer.php
│   └── task_item.php    # reusable task row
├── api/
│   └── tasks.php        # one JSON endpoint (create/update/delete/
│                        # complete/restore/move), CSRF + ownership checks
├── assets/
│   ├── css/style.css    # design-token theme system (light + dark)
│   └── js/
│       ├── app.js       # all UI behaviour + fetch calls
│       └── quotes.js    # ~100 local motivational messages
├── database/
│   ├── .htaccess        # block direct HTTP access
│   └── doneday.sql      # schema + sample data (phpMyAdmin-ready)
├── SECURITY.md          # security model, tests, limitations
├── README.md
└── .gitignore
```

**Why one `api/tasks.php`?** All six actions share the same bootstrap
(DB, session, CSRF, ownership). One small router keeps that logic in one
place and is easier to follow than six near-identical scripts.

**Why quotes in `quotes.js` and not MySQL?** Static content needs no
queries, no joins, no admin UI — zero cost, zero latency. A table only
makes sense if quotes become user-editable later.

---

## Security

Full details, test evidence, and known limitations are in
**[SECURITY.md](SECURITY.md)**. Summary:

- PDO prepared statements everywhere; zero concatenated SQL
- `password_hash()` / `password_verify()`; bcrypt
- CSRF tokens verified on **every** state-changing route
- Per-user ownership enforced server-side on every task query (IDOR-safe)
- Full output escaping (`htmlspecialchars`) and safe DOM insertion
- HttpOnly + SameSite session cookies; `Secure` auto-enabled under HTTPS;
  session rotation on login; destruction on logout
- Security headers on every response (nosniff, frame denial, CSP, …)
- Session-based login throttling (5 attempts → 15-minute lock)
- `config/`, `includes/`, `database/` blocked from direct web access
- Friendly user-facing errors; technical details only in `error_log()`

---

## Themes

Settings → Appearance → System / Light / Dark.

- **System** follows `prefers-color-scheme` live, including when the OS
  switches while the app is open.
- Light/dark are stored per user (`settings.theme`) and apply instantly
  — no page reload.
- The palette is token-based (`--bg-primary`, `--surface`,
  `--text-primary`, `--accent`, …) so future palette tweaks are one-line
  changes. Dark mode uses soft black (`#121417`), a desaturated sage
  accent, and AA-or-better contrast per current accessibility guidance.

---

## Testing checklist

**Auth:** register (valid/duplicate/weak/mismatched) · login (correct,
wrong password) · 5 failed logins → 15-min lock · logout · protected
pages redirect when logged out.

**Tasks:** quick-add (type+Enter) · full add with details · edit ·
delete · complete · move Today⇄Later · restore from History · invalid or
foreign task ids rejected.

**Limit:** 3/5/7/custom limits · reaching the limit → friendly dialog ·
Move & add · Add anyway · Cancel.

**Rollover:** yesterday's unfinished Today tasks → Later (once, not
duplicated, never deleted) · completed history intact.

**Themes:** system/light/dark each render correctly · OS switch while
open updates a System-mode page live · preference persists across
sessions.

**Security spot-checks:** `<script>alert(1)</script>` title stored and
displayed inertly · POST to the API without a CSRF token → 403 · another
user's task id → "not found".

**Accessibility/responsive:** full flow by keyboard only · focus visible
everywhere · usable at 360px width · `prefers-reduced-motion` respected.

---

## Deployment

The app needs only **PHP 8+ with PDO-MySQL, one MySQL database, and
Apache** (for the folder `.htaccess` protections; without Apache they
are simply ignored and PHP-level protections still apply).

1. Upload all files (except `database/`) via FTP.
2. Create a MySQL database in your host's panel; import
   `database/doneday.sql` (or run the table statements only).
3. Fill your host's credentials into `config/database.php`.
4. Enable free SSL/HTTPS (Let's Encrypt) — the `Secure` cookie flag and
   HSTS-ready headers then apply automatically.
5. No Node, no build step, no cron, no workers, no paid services.

Suitable: shared cPanel hosting, or free tiers like InfinityFree.

---

## GitHub workflow

```bash
git init && git add . && git commit -m "DoneDay"
```

`config/database.php` is gitignored — credentials are never pushed.
A teammate imports `database/doneday.sql`, restores
`config/database.php` from the XAMPP defaults, and is running in minutes.

---

## Future expansion (deliberately NOT included)

Focus mode, task breakdown into steps, time/energy-based suggestions,
recurring tasks, streaks without shame, weekly reflection — the schema
and structure leave room for these without bloating the MVP.

## License

MIT.
