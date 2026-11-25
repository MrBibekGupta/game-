# Banana Game

## Project Purpose

This project is a PHP-based interactive puzzle game application. It includes user registration and authentication, profile management, leaderboard tracking, and live puzzle generation via an external web service API.

## Project Structure

- `index.php` — Landing page and navigation entry point
- `auth/` — User authentication (`register.php`, `login.php`, `logout.php`)
- `game/` — Core gameplay (`play.php`, `new_game.php`, `leaderboard.php`, `profile.php`)
- `includes/` — Shared backend modules (`api.php`, `session_init.php`, `header.php`, `footer.php`)
- `config/` — Database connection configuration (`db.php`)
- `assets/` — Frontend stylesheets (`css/style.css`) and event-driven scripts (`js/script.js`)
- `cache/` — Local fallback cache for external API responses (`last_puzzle.json`)
- `docs/` — Technical documentation (`design-rationale.md`, `interoperability.md`, `security.md`)

## Git Workflow

This repository follows a structured branching and commit workflow:

1. **Main Development Branch (`development`)**:
   - `development` is the active integration branch where tested features and fixes are merged and pushed to `origin/development`.
   - Before pushing, always sync with remote changes using `git pull --rebase origin development`.
   - Never rewrite shared history or use `git push --force`.

2. **Feature & Backup Branches**:
   - Independent improvements are developed or isolated on dedicated branches (e.g., `feature-game`, `feature-new`) and recovery points (e.g., `backup-pre-phase1`) before integration.

3. **Conventional Commit Messages**:
   - `feat:` — New features or enhancements (e.g., `feat: add async answer submission and timer`)
   - `fix:` — Bug fixes (e.g., `fix: resolve CSRF token syntax error in login`)
   - `docs:` — Documentation updates (e.g., `docs: add Git workflow to Readme.md`)
   - `refactor:` — Code restructuring without changing behavior
   - `style:` — UI/CSS formatting improvements

4. **Pre-Commit Verification**:
   - Inspect changes with `git status` and `git diff`.
   - Run `php -l` syntax checks and functional tests before staging.
   - Never commit credentials, `.env` files, or broken code.
