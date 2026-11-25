# Security Audit Report — Banana Game

**Scope:** Static code review + live HTTP/CLI security testing + real Chrome browser network/console verification of `C:\xampp\htdocs\Game`.  
**Sensitive Data Policy:** No passwords, session IDs, CSRF tokens, or database credentials are logged or included.

---

## 1. Security Controls Verified (PASS)

| Control | Implementation | Verification Result |
| :--- | :--- | :--- |
| **SQL Injection Protection** | Parameterized `mysqli` prepared statements (`prepare()` + `bind_param()`) in `auth/login.php`, `auth/register.php`, `game/play.php`, `game/profile.php`; static SQL in `game/leaderboard.php`. | **PASS** |
| **Password Hashing** | `password_hash($password, PASSWORD_DEFAULT)` and `password_verify()` in `auth/register.php` & `auth/login.php`. | **PASS** |
| **CSRF Protection** | 256-bit random token in `includes/session_init.php`, timing-safe `hash_equals()` via `verifyCsrfToken()` on `login.php`, `register.php`, and `play.php`. | **PASS** (CLI & HTTP POST) |
| **Session Fixation** | `session_regenerate_id(true)` executed immediately upon successful login in `auth/login.php`. | **PASS** |
| **Cookie Hardening** | `HttpOnly=true`, `SameSite=Strict`, and HTTPS-aware `Secure` flag configured before `session_start()` in `includes/session_init.php`. | **PASS** |
| **Login Rate Limiting** | 5 consecutive failed attempts trigger a 15-minute session lockout (`$_SESSION['lockout_until']`) in `auth/login.php`. | **PASS** (HTTP POST) |
| **Auth Guards & Logout** | `play.php`, `profile.php`, and `new_game.php` redirect guests (`302`) to `auth/login.php`; `logout.php` clears `$_SESSION`, expires cookie, and calls `session_destroy()`. | **PASS** |
| **Output Escaping** | Dynamic HTML output escaped with `htmlspecialchars()`. | **PASS** |

---

## 2. Security Findings & Mitigations

| Severity | Finding | Status | Details / Recommendation |
| :---: | :--- | :--- | :--- |
| **Medium** | **Direct HTTP exposure of `cache/last_puzzle.json` & `rate_limit.json`** | **FIXED** | Added `cache/.htaccess` (`Require all denied`); re-tested and confirmed `403 Forbidden` for both files. |
| **Low** | **Logout via `GET` (`auth/logout.php`)** | **FIXED** | Converted `auth/logout.php` to require `POST` + `verifyCsrfToken()` and updated logout controls in `index.php`, `play.php`, `profile.php`, and `leaderboard.php`. Verified `POST auth/logout.php -> 302` in Chrome. |
| **Low** | **Session-scoped Rate Limiting** | **FIXED** | Added hashed IP + account persistence in `cache/rate_limit.json` (protected `403`, ignored in `.gitignore`). Verified lockout persists across cleared cookies (`COOKIE_CLEAR_BYPASS_BLOCKED: True`). |
| **Info** | **HTTP Localhost Cookie `Secure` Flag** | **BY DESIGN** | `session_init.php` sets `Secure=false` on HTTP `localhost` and `Secure=true` automatically when HTTPS (`$_SERVER['HTTPS']` / port `443`) is active. |
