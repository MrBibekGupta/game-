<?php

/**
 * Save a valid puzzle response to the local JSON cache file.
 */
function savePuzzleCache(string $cacheFile, string $question, int $solution): void
{
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    $payload = json_encode([
        "question" => $question,
        "solution" => $solution,
        "cached_at" => date("c")
    ], JSON_PRETTY_PRINT);

    if ($payload !== false) {
        @file_put_contents($cacheFile, $payload, LOCK_EX);
    }
}

/**
 * Load and validate a previously cached puzzle response.
 */
function loadPuzzleCache(string $cacheFile): ?array
{
    if (!is_file($cacheFile) || !is_readable($cacheFile)) {
        return null;
    }

    $raw = @file_get_contents($cacheFile);
    if ($raw === false || $raw === "") {
        return null;
    }

    $data = json_decode($raw, true);
    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($data) ||
        empty($data["question"]) ||
        !isset($data["solution"])
    ) {
        return null;
    }

    return [
        "success" => true,
        "question" => (string) $data["question"],
        "solution" => (int) $data["solution"],
        "cached" => true
    ];
}

/**
 * Fetch a puzzle from the external Banana API with local cache fallback.
 */
function getBananaQuestion(
    string $apiUrl = "http://marcconrad.com/uob/banana/api.php?out=json&base64=no",
    ?string $cacheFile = null
): array {
    if ($cacheFile === null) {
        $cacheFile = __DIR__ . "/../cache/last_puzzle.json";
    }

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => "Banana Game PHP",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);

        $cached = loadPuzzleCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        return [
            "success" => false,
            "message" => "API connection failed: " . $error
        ];
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode !== 200) {
        $cached = loadPuzzleCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        return [
            "success" => false,
            "message" => "API returned HTTP status: " . $httpCode
        ];
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $cached = loadPuzzleCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        return [
            "success" => false,
            "message" => "Invalid JSON response from Banana API."
        ];
    }

    if (
        !is_array($data) ||
        empty($data["question"]) ||
        !isset($data["solution"])
    ) {
        $cached = loadPuzzleCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        return [
            "success" => false,
            "message" => "Question or solution is missing from API response."
        ];
    }

    $question = (string) $data["question"];
    $solution = (int) $data["solution"];

    savePuzzleCache($cacheFile, $question, $solution);

    return [
        "success" => true,
        "question" => $question,
        "solution" => $solution,
        "cached" => false
    ];
}
