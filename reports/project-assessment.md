# University Rubric Project Assessment — Banana Game

This assessment evaluates the current state of `C:\xampp\htdocs\Game` against the four supplied university marking criteria, separating verified runtime evidence from unexecuted browser checks.

---

## Criterion 1: Version Control & Organization
- **Evidence in Code:** Domain-separated folders (`auth/`, `game/`, `includes/`, `config/`, `assets/`, `cache/`, `docs/`), `development` integration branch synced with `origin/development`, `backup-pre-phase1` recovery branch, 5 structured Conventional Commits (`f58bbba`..`8511859`), `.gitignore`, `Readme.md`, and `docs/design-rationale.md`.
- **Strengths:** Clean Git history for recent phases; clear separation of shared helpers (`session_init.php`, `api.php`); zero syntax errors across all 13 PHP files.
- **Weaknesses:** Inline `<style>` blocks remain duplicated across individual PHP views while `assets/css/style.css` is largely unused; procedural PHP rather than OOP classes/MVC; no Git release tags (`v1.0`, etc.) yet.
- **Estimated Mark:** **74% – 80% (1st Class)**
- **Rationale:** Satisfies 2:1/1st Class criteria through structured branching, meaningful commits, documented Git workflow, and critical evaluation of architectural trade-offs in `docs/design-rationale.md`, held slightly below 85%+ by duplicated inline CSS and lack of OOP/tags.

## Criterion 2: Event-Driven Programming
- **Evidence in Code:** `assets/js/script.js` (`fetch()` async submission, `DOMParser` partial `#game-area` replacement, 30s `setInterval` countdown with auto-submit `timeout=1`, `keydown` `0`–`9` shortcuts, duplicate-submit guard, password toggle) + `game/play.php` server-side `POST`/timeout handler.
- **Strengths:** Verified live in real Chrome via Playwright: `0` console/page errors, `X-Requested-With: XMLHttpRequest` `200 OK` without full-page reload (`ALIVE_WITHOUT_RELOAD`), visible countdown tick (`29 -> 27`) and reset (`30`), automatic submission at `0` (`Time expired!`), and `0`–`9` keyboard shortcut focus/entry.
- **Weaknesses:** Partial DOM swap via `DOMParser` is slightly heavier than a dedicated JSON game endpoint (though it enables progressive enhancement).
- **Estimated Mark:** **84% – 90% (1st Class)**
- **Rationale:** All event-driven requirements are implemented, justified in `docs/design-rationale.md`, and verified end-to-end in a real Chrome browser.

## Criterion 3: Interoperability
- **Evidence in Code:** `includes/api.php` (`cURL` GET to Marc Conrad's Banana API, HTTP 200 check, `json_last_error()`, schema validation, `cache/last_puzzle.json` write/fallback), `cache/.htaccess` (`403` protection), and `docs/interoperability.md`.
- **Strengths:** All 4 live & simulated-failure API tests passed in runtime; critically justifies REST/JSON vs. SOAP/XML and server-side vs. client-side fetching.
- **Weaknesses:** Single-puzzle cache repeats the same puzzle if the remote API stays offline across multiple rounds; upstream API uses HTTP.
- **Estimated Mark:** **84% – 90% (1st Class)**
- **Rationale:** Demonstrates robust cross-system interoperability, verified fault-tolerance, and thorough technical justification.

## Criterion 4: Virtual Identity (Authentication & Security)
- **Evidence in Code:** `includes/session_init.php` (`HttpOnly`, `SameSite=Strict`, HTTPS-aware `Secure`, `hash_equals()` CSRF), `auth/register.php`, `auth/login.php` (`password_hash`/`password_verify`, `session_regenerate_id(true)`, persistent IP+account 5-attempt lockout in `cache/rate_limit.json`), `POST`+CSRF `auth/logout.php`, prepared statements, `cache/.htaccess`, and `docs/security.md`.
- **Strengths:** All 10 security & auth checks passed in live HTTP/CLI/Chrome tests, including cookie-clear rate-limit bypass protection and CSRF-protected logout; critical comparison of stateful sessions vs. JWT and defense-in-depth documented.
- **Weaknesses:** File-based rate limiting is single-node (Redis/DB would be needed for multi-server horizontal scaling).
- **Estimated Mark:** **88% – 93% (1st Class)**
- **Rationale:** Covers all 70%+ criteria with verified runtime enforcement, zero open security findings, and thorough discussion of limitations and alternatives.

---

## Overall Realistic Estimate
- **Projected Overall Range:** **84% – 89% (1st Class)**
- **Condition:** Assumes your final university submission includes the `docs/*.md` critical reflections and `reports/` evidence.
