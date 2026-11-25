# Final Project Test Report — Banana Game

**Date:** 2026-11-25  
**Project Directory:** `C:\xampp\htdocs\Game`  
**Repository:** `https://github.com/MrBibekGupta/game-.git` (Branch: `development`, HEAD: `8511859`)

---

## 1. Project Overview & Testing Environment

The **Banana Game** is a PHP 8 / MySQL web application using Apache (XAMPP) and vanilla JavaScript. Users register, authenticate, solve 10-question math puzzles fetched from Marc Conrad's external Banana API (`http://marcconrad.com/uob/banana/api.php`), track scores in `banana_game`, and view profiles and leaderboards.

- **OS / Runtime:** Windows, XAMPP Apache (`httpd`), MySQL (`mysqld` on port `3306`), PHP `8.0.30` CLI, Node.js (`node --check`).
- **Browser Testing Environment:** Real Google Chrome (`C:\Program Files\Google\Chrome\Application\chrome.exe`) driven via Python Playwright at desktop (`1280x800`) and mobile (`375x812`) viewports against `http://localhost/Game/`. Note: the IDE's built-in DevTools hook could not attach to the user's already-open Chrome profile (`DevToolsActivePort` missing), so Playwright launched an isolated real Chrome instance instead.

---

## 2. Test Execution Summary

All automated checks (`tests/verify_project.ps1`) and live HTTP/MySQL integration checks were executed against `http://localhost/Game/`.

| Category | Executed | Passed | Failed | Skipped / Not Tested | Notes |
| :--- | :---: | :---: | :---: | :---: | :--- |
| PHP Syntax (`php -l`) | 13 | 13 | 0 | 0 | All 13 PHP files clean |
| JavaScript Syntax (`node --check`) | 1 | 1 | 0 | 0 | `assets/js/script.js` clean |
| API Interoperability & Cache | 4 | 4 | 0 | 0 | Live API, cache write, fallback, no-cache error |
| Database (`banana_game`) | 2 | 2 | 0 | 0 | `users`, `game_sessions`, `game_results` + FKs |
| Auth & Security | 10 | 10 | 0 | 0 | Cookies, CSRF, hashing, persistent IP+account rate-limit, POST CSRF logout, HTTP 403 cache |
| Live HTTP End-to-End Flow | 9 | 9 | 0 | 0 | Register, login, Q1→Q2, timeout→Q3, profile, leaderboard, reset, logout |
| Browser Visual / Live DOM JS | 7 | 7 | 0 | 0 | Real Chrome: desktop/mobile layout, 0 console errors, AJAX, 30s timeout, `0-9` keys |
| Git Synchronization | 2 | 2 | 0 | 0 | `development == origin/development` (`8511859`) |

---

## 3. Detailed Test Cases

| ID | Test Case | Steps / Verification Method | Expected Result | Actual Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| TC-01 | PHP Syntax Lint | Run `php -l` on all 13 `.php` files | 0 syntax errors | 13/13 passed | PASS |
| TC-02 | JS Syntax Check | Run `node --check assets/js/script.js` | 0 syntax errors | Passed with 0 errors | PASS |
| TC-03 | Live Banana API | Call `getBananaQuestion()` via PHP CLI | HTTP 200 JSON with `question` & `solution` | `success: true`, `cached: false` | PASS |
| TC-04 | API Cache Persistence | Inspect `cache/last_puzzle.json` after live call | Valid JSON file created | File created & readable | PASS |
| TC-05 | API Fallback Recovery | Call `getBananaQuestion('http://127.0.0.1:59999/unreachable')` | Returns cached puzzle | `success: true`, `cached: true` | PASS |
| TC-06 | Unreachable API (No Cache) | Call unreachable URL with nonexistent cache path | Clean error message, no crash | `success: false`, error returned | PASS |
| TC-07 | Cache HTTP Exposure | `GET http://localhost/Game/cache/last_puzzle.json` | HTTP 403 Forbidden | Initially 200 (fixed with `cache/.htaccess`) → **403** | PASS |
| TC-08 | MySQL Schema & FKs | Connect to `banana_game` and run `SHOW CREATE TABLE` | `users`, `game_sessions`, `game_results` with FKs | All 3 tables & cascade FKs verified | PASS |
| TC-09 | Session Cookie Flags | Inspect `session_get_cookie_params()` after `session_init.php` | `HttpOnly=true`, `SameSite=Strict` | Confirmed `HttpOnly=true`, `SameSite=Strict` | PASS |
| TC-10 | CSRF Validation | `verifyCsrfToken()` with valid, invalid, and `null` tokens; HTTP POST with bad token | Valid=`true`, invalid/null=`false`, POST rejected | Confirmed both in CLI and HTTP POST | PASS |
| TC-11 | Password Hashing | `password_hash()` + `password_verify()` valid/wrong | Valid=`true`, wrong=`false` | Confirmed | PASS |
| TC-12 | Login Rate Limiting | 6 consecutive failed `POST` logins on `auth/login.php` | Lockout triggered after 5 attempts | `"Too many failed login attempts"` returned | PASS |
| TC-13 | Guest Route Guards | Unauthenticated `GET` to `play.php`, `profile.php`, `new_game.php` | HTTP 302 redirect to `auth/login.php` | All 3 returned `302` to `login.php` | PASS |
| TC-14 | Registration & Login Flow | `POST` register unique user → `POST` login | Account created → lands on `play.php` | `REGISTER_OK: True`, `LOGIN_LANDED_ON_PLAY: True` | PASS |
| TC-15 | Gameplay & Timeout Flow | `POST` answer on Q1 → `POST timeout=1` on Q2 → `GET new_game.php` | Advances Q1→Q2→Q3, resets to Q1 | Confirmed all transitions | PASS |
| TC-16 | Profile & Leaderboard | Authenticated `GET` to `profile.php` and `leaderboard.php` | HTTP 200 displaying user stats | Both returned 200 with test user | PASS |
| TC-17 | Logout Invalidation (POST+CSRF) | Click `POST auth/logout.php` form button in Chrome → `GET game/play.php` | `POST logout.php -> 302`, session cleared & `play.php` returns `302` | `POST auth/logout.php -> 302` to `index.php`, `post_logout_play_http=302` | PASS |
| TC-20 | Persistent Rate-Limit Bypass Check | Trigger 5 failures in session 1, clear cookies (new session 2), attempt login | Lockout persists across cleared cookies via `cache/rate_limit.json` | `COOKIE_CLEAR_BYPASS_BLOCKED: True`, `cache/rate_limit.json -> 403` | PASS |
| TC-18 | Browser Visual, Responsive & Console | Load `index.php` (1280x800 & 375x812), `login.php`, `profile.php`, `leaderboard.php` in Chrome | 0 console/page errors, 0 broken images, no horizontal overflow | `overflow=False`, `broken_imgs=0`, `CONSOLE_ERRORS_COUNT: 0`, `PAGE_ERRORS_COUNT: 0` | PASS |
| TC-19 | Browser Live `fetch()`, 30s Timer & Keys | Blur `#answer`, press `7`, submit via `fetch()`, then wait 30s for auto-timeout | `no_reload_guard` stays alive, `X-Requested-With: XMLHttpRequest`, timer ticks & resets, auto-submits at `0` | `AJAX_SUBMIT_Q1_TO_Q2` (`200`, `ALIVE_WITHOUT_RELOAD`, `timer_reset=30`), `AUTO_TIMEOUT_Q2_TO_Q3` (`200`, `Time expired!`, `ALIVE_WITHOUT_RELOAD`) | PASS |

### Browser Screenshots (Evidence)
- `reports/screenshots/01_index_desktop.png`
- `reports/screenshots/02_index_mobile.png`
- `reports/screenshots/03_login.png`
- `reports/screenshots/04_play_ajax_q2.png`
- `reports/screenshots/05_play_timeout_q3.png`
- `reports/screenshots/06_profile.png`
- `reports/screenshots/07_leaderboard.png`

---

## 4. Bugs Discovered, Fixes Implemented & Remaining Issues

- **Bugs & Security Issues Fixed:**
  1. `cache/last_puzzle.json` HTTP exposure fixed via `cache/.htaccess` (`Require all denied`, verified `403 Forbidden`).
  2. `GET`-based logout CSRF fixed by converting `auth/logout.php` to require `POST` + `verifyCsrfToken()` and updating logout controls in `index.php`, `play.php`, `profile.php`, and `leaderboard.php`.
  3. Session-based rate-limit bypass fixed by persisting hashed IP + account failure counters in `cache/rate_limit.json` (protected `403`), verified across cleared cookies.
- **Remaining Issues / Limitations:**
  1. **Duplicated Inline CSS:** Individual PHP pages still contain inline `<style>` blocks rather than sharing `assets/css/style.css`.
  2. **Uncommitted Local Files:** Modified auth/nav files, `cache/.htaccess`, `tests/verify_project.ps1`, and `reports/*` (including screenshots) are uncommitted locally pending approval.
