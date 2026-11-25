# API Interoperability Documentation

## 1. Overview

The Banana Game integrates with an external third-party web service — **Marc Conrad's Banana API** (`http://marcconrad.com/uob/banana/api.php?out=json&base64=no`) — implemented in `includes/api.php`. This demonstrates cross-system interoperability between a local PHP/MySQL server and an external HTTP service running on a separate host and architecture.

## 2. Protocol & Data Interchange Format

- **Transport Protocol:** HTTP/1.1 `GET` request executed server-side via PHP `cURL`.
- **Headers:** Sends `Accept: application/json` and an explicit `User-Agent: Banana Game PHP`.
- **Timeouts:** Enforces `CURLOPT_CONNECTTIMEOUT = 5` seconds and `CURLOPT_TIMEOUT = 10` seconds to prevent worker thread exhaustion if the remote server stalls.
- **Data Format:** JSON (`out=json&base64=no`), returning an image URL (`question`) and an integer answer (`solution`).

### Why JSON over REST instead of SOAP/XML or Base64?
- **JSON vs. XML/SOAP:** JSON has a smaller payload size, lower serialization overhead, and native support in PHP (`json_decode`), whereas SOAP requires heavy XML envelopes and WSDL parsing.
- **URL vs. Base64 (`base64=no`):** Returning the image URL keeps API responses lightweight and avoids storing large Base64 strings in PHP session files, while allowing the browser to load and cache the puzzle image directly.

## 3. Fault Tolerance & Cache Fallback

External services can experience downtime, DNS failures, or malformed responses. `includes/api.php` implements a resilient **cache-aside fallback**:

1. **Live Fetch:** `getBananaQuestion()` requests a fresh puzzle from the Banana API.
2. **Validation:** Verifies that `curl_exec()` succeeded, HTTP status is `200`, `json_last_error() === JSON_ERROR_NONE`, and both `question` and `solution` are present.
3. **Cache Write:** On success, `savePuzzleCache()` writes the puzzle to `cache/last_puzzle.json` using `LOCK_EX` for atomic file locking.
4. **Fallback Read:** On any network error, non-200 HTTP status, or invalid JSON payload, `loadPuzzleCache()` loads and validates `cache/last_puzzle.json` so gameplay can continue gracefully without crashing.

## 4. Limitations & Trade-offs

- **HTTP vs. HTTPS:** The upstream Banana API endpoint is served over `http://`; server-side `cURL` avoids browser mixed-content warnings for the JSON call, though the returned image URL is also HTTP.
- **Single-Item Cache:** `cache/last_puzzle.json` stores the most recent valid puzzle rather than a multi-puzzle pool, which prevents total outage failure but repeats the last puzzle if the remote API remains offline across multiple rounds.
