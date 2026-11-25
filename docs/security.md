# Virtual Identity & Security Documentation

## 1. Establishing Virtual Identity

User virtual identity in the Banana Game is established through server-side PHP sessions backed by a relational MySQL `users` table (`auth/register.php`, `auth/login.php`, `auth/logout.php`, and `includes/session_init.php`).

- **Registration (`auth/register.php`):** Validates username length (`3–50` chars), email syntax (`FILTER_VALIDATE_EMAIL`), and password length (`>= 6` chars), checks uniqueness via prepared statements, and hashes credentials with `password_hash($password, PASSWORD_DEFAULT)` (Bcrypt/Argon2).
- **Authentication (`auth/login.php`):** Verifies credentials using `password_verify()`, regenerates the session identifier via `session_regenerate_id(true)` to prevent session fixation, and binds `user_id`, `username`, and `email` to `$_SESSION`.
- **Termination (`auth/logout.php`):** Clears `$_SESSION`, expires the browser session cookie, and calls `session_destroy()`.

## 2. Defense-in-Depth Mechanisms

1. **Centralized Session Hardening (`includes/session_init.php`):**
   - Configures cookie parameters *before* `session_start()`:
     - `httponly => true` (mitigates XSS cookie theft via `document.cookie`).
     - `samesite => 'Strict'` (mitigates cross-site request forgery on cookie transmission).
     - `secure => $isHttps` (automatically enabled when served over HTTPS / port 443, while remaining functional on local HTTP `localhost` during development).
2. **CSRF Protection (`csrfInputField()` & `verifyCsrfToken()`):**
   - Generates a 256-bit cryptographically random token (`bin2hex(random_bytes(32))`) per session.
   - Embedded in all state-changing `POST` forms (`auth/login.php`, `auth/register.php`, `game/play.php`) and verified with timing-safe `hash_equals()`.
3. **Brute-Force Rate Limiting (`auth/login.php`):**
   - Tracks failed login attempts in `$_SESSION['login_attempts']`.
   - Enforces a 15-minute lockout (`$_SESSION['lockout_until']`) after 5 consecutive failed attempts, resetting the counter upon successful login.
4. **SQL Injection & XSS Prevention:**
   - All database queries accepting user input use `mysqli` prepared statements (`prepare()` / `bind_param()`).
   - Output rendered in HTML is escaped with `htmlspecialchars()`.

## 3. Alternative Approaches & Limitations

- **Stateful Sessions vs. Stateless JWT:** Stateful PHP sessions allow immediate server-side revocation on logout (`session_destroy()`), whereas stateless JWTs require token blacklists or short expiry + refresh tokens. For a monolithic PHP web app, server-side sessions are simpler and safer.
- **Session-based vs. IP/Account-based Rate Limiting:** Session-based rate limiting stops automated browser/cookie replay brute-force attacks without altering the database schema, though a distributed attacker clearing cookies could bypass it (a production deployment would pair this with database/Redis IP+username throttling).
- **Future Enhancements:** Multi-Factor Authentication (TOTP) or OAuth 2.0 / OpenID Connect (e.g., Google Sign-In) for federated virtual identity.
