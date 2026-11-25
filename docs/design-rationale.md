# Architectural Design Rationale

This document critically evaluates the engineering decisions, alternative approaches, advantages, disadvantages, and limitations across the four core assessment criteria of the Banana Game project.

---

## 1. Code Organization & Version Control

### Architectural Decision
The application is organized into domain-focused directories:
- `auth/` (registration, login, logout)
- `game/` (gameplay loop, session reset, profile, leaderboard)
- `includes/` (shared session initialization, external API client, layout partials)
- `config/` (database connection)
- `assets/` (client-side event-driven JavaScript and stylesheets)
- `cache/` & `docs/` (resilience cache and technical documentation)

### Alternative Approaches Considered
- **Full Object-Oriented MVC Framework (e.g., Laravel/Symfony):** Would provide built-in routing, ORM, and dependency injection, but introduces Composer/framework overhead and steep boilerplate for a compact 10-question puzzle game.
- **Modular Procedural + Shared Helpers (Chosen):** Keeps deployment zero-dependency on standard XAMPP/PHP 8 while isolating concerns (`session_init.php`, `api.php`, `db.php`) so security and API logic are not duplicated across endpoints.

### Version Control Strategy
- **Branching & Recovery:** `development` serves as the main integration branch; feature branches (`feature-game`, `feature-new`) and recovery snapshots (`backup-pre-phase1`) isolate changes before integration.
- **Commit Granularity:** Each meaningful task (Git docs, event-driven JS, API caching, security hardening, design docs) is committed separately using Conventional Commits (`feat:`, `fix:`, `docs:`) and synced via `git pull --rebase` without rewriting shared history.
- **Limitation:** Page-specific `<style>` blocks remain inline in individual views to preserve the existing UI without risking visual regressions; a future refactor could consolidate all rules into `assets/css/style.css`.

---

## 2. Event-Driven Programming

### Architectural Decision
Gameplay interactions in `game/play.php` and `assets/js/script.js` combine **client-side DOM/Timer/Keyboard events** with **server-side HTTP POST request handling**:
- **Asynchronous Submission (`fetch()`):** Intercepts the form `submit` event, transmits `FormData` (including `csrf_token`) via `fetch()`, parses the returned HTML with `DOMParser`, and swaps `#game-area` in-place without a full page reload.
- **Timer Event (`setInterval`):** Runs a 30-second countdown per question, updates `#timer-seconds`, applies warning styling at `<= 10s`, and dispatches an automatic timeout submission (`timeout=1`) when reaching `0`.
- **Keyboard Shortcuts (`keydown`):** Allows rapid numeric entry (`0`–`9`) by automatically focusing and populating `#answer`.

### Advantages & Trade-offs
- **Advantage (Progressive Enhancement):** Because `play.php` still handles standard `POST` requests, the game remains 100% functional even if JavaScript is disabled or `fetch()` encounters a transient error (falling back to `form.submit()`).
- **Disadvantage / Limitation:** Swapping `#game-area` via `DOMParser` is slightly heavier than a dedicated JSON API endpoint for `play.php`, though it avoids duplicating HTML rendering templates between PHP and JS.

---

## 3. Interoperability

### Architectural Decision
`includes/api.php` communicates with Marc Conrad's external Banana API (`http://marcconrad.com/uob/banana/api.php?out=json&base64=no`) using PHP `cURL` over HTTP/REST with JSON payloads and a local file cache (`cache/last_puzzle.json`).

### Alternatives & Justification
- **Server-side cURL vs. Client-side Browser `fetch()`:** Fetching the puzzle server-side hides the `solution` integer from the browser's network tab/DevTools, preventing cheating, and avoids CORS restrictions.
- **Cache-Aside Fallback:** Storing the last valid response in `cache/last_puzzle.json` prevents total application failure during external API outages. See [interoperability.md](interoperability.md) for full protocol analysis.

---

## 4. Virtual Identity & Security

### Architectural Decision
Authentication relies on `password_hash()`/`password_verify()`, `mysqli` prepared statements, centralized `session_init.php` (`HttpOnly`, `SameSite=Strict`, HTTPS-aware `Secure`), timing-safe CSRF tokens (`hash_equals()`), and session-based login rate limiting (5 attempts / 15-minute lockout).

### Alternatives & Limitations
- **Server-Side Sessions vs. JWT:** Server-side sessions support instant invalidation on `auth/logout.php` without token revocation lists.
- **Session Rate Limiting vs. Database/Redis Throttling:** Protects against automated credential-stuffing without modifying the database schema, though distributed attackers clearing cookies would require IP/account-level persistence. See [security.md](security.md) for full details.
