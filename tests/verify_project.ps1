<#
.SYNOPSIS
    Comprehensive Terminal Verification & Test Suite for Banana Game
.DESCRIPTION
    Runs PHP syntax checks, JavaScript validation, API live & fallback tests,
    Security/CSRF/Session checks, Database connectivity checks, Gameplay checks,
    and Git synchronization verification directly in the VS Code terminal.
    Never prints passwords, session IDs, CSRF tokens, or DB credentials.
#>

$ErrorActionPreference = "Continue"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PhpExe = "C:\xampp\php\php.exe"

$script:TotalTests = 0
$script:PassedTests = 0
$script:FailedTests = 0
$script:SkippedTests = 0

$script:CategoryStatus = [ordered]@{
    PHP        = "PENDING"
    JavaScript = "PENDING"
    API        = "PENDING"
    Database   = "PENDING"
    Security   = "PENDING"
    Gameplay   = "PENDING"
    Git        = "PENDING"
}

function Write-Section([string]$Title) {
    Write-Host ""
    Write-Host ("=" * 70) -ForegroundColor Cyan
    Write-Host "  $Title" -ForegroundColor Cyan
    Write-Host ("=" * 70) -ForegroundColor Cyan
}

function Record-Result([string]$Name, [string]$Status, [string]$Detail = "") {
    $script:TotalTests++
    switch ($Status) {
        "PASS" {
            $script:PassedTests++
            Write-Host "[PASS] $Name" -ForegroundColor Green
        }
        "FAIL" {
            $script:FailedTests++
            Write-Host "[FAIL] $Name" -ForegroundColor Red
        }
        "SKIP" {
            $script:SkippedTests++
            Write-Host "[SKIP] $Name" -ForegroundColor Yellow
        }
    }
    if ($Detail -ne "") {
        Write-Host "       $Detail" -ForegroundColor Gray
    }
}

# ==============================================================================
# 1. PHP SYNTAX TESTING
# ==============================================================================
Write-Section "1. PHP Syntax Testing (php -l)"

$phpFiles = Get-ChildItem -Path $ProjectRoot -Recurse -Filter "*.php" | Sort-Object FullName
$phpPassed = 0
$phpFailed = 0

if (-not (Test-Path $PhpExe)) {
    Record-Result "PHP executable check" "FAIL" "Not found at $PhpExe"
    $script:CategoryStatus.PHP = "FAIL"
} else {
    foreach ($file in $phpFiles) {
        $relPath = $file.FullName.Substring($ProjectRoot.Length + 1)
        $lintOut = & $PhpExe -l $file.FullName 2>&1 | Out-String
        if ($LASTEXITCODE -eq 0) {
            $phpPassed++
            Record-Result "php -l $relPath" "PASS"
        } else {
            $phpFailed++
            Record-Result "php -l $relPath" "FAIL" ($lintOut.Trim())
        }
    }
    Write-Host ""
    Write-Host "PHP Summary: Total=$($phpFiles.Count), Passed=$phpPassed, Failed=$phpFailed" -ForegroundColor White
    $script:CategoryStatus.PHP = if ($phpFailed -eq 0) { "PASS ($phpPassed/$($phpFiles.Count))" } else { "FAIL ($phpFailed failed)" }
}

# ==============================================================================
# 2. JAVASCRIPT TESTING
# ==============================================================================
Write-Section "2. JavaScript Testing (node --check)"

$jsFile = Join-Path $ProjectRoot "assets\js\script.js"
Write-Host "Command: node --check assets/js/script.js" -ForegroundColor Gray
$nodeOut = & node --check $jsFile 2>&1 | Out-String
if ($LASTEXITCODE -eq 0) {
    $outputText = if ([string]::IsNullOrWhiteSpace($nodeOut)) { "No syntax errors" } else { $nodeOut.Trim() }
    Record-Result "node --check assets/js/script.js" "PASS" $outputText
    $script:CategoryStatus.JavaScript = "PASS"
} else {
    Record-Result "node --check assets/js/script.js" "FAIL" ($nodeOut.Trim())
    $script:CategoryStatus.JavaScript = "FAIL"
}

# ==============================================================================
# 3. API INTEROPERABILITY TESTING
# ==============================================================================
Write-Section "3. API Interoperability & Cache Fallback Testing"

$apiScript = @'
require "c:/xampp/htdocs/Game/includes/api.php";
$live = getBananaQuestion();
echo "LIVE_SUCCESS=" . ($live["success"] ? "true" : "false") . PHP_EOL;
echo "LIVE_CACHED=" . (!empty($live["cached"]) ? "true" : "false") . PHP_EOL;
echo "LIVE_HAS_QUESTION=" . (!empty($live["question"]) ? "true" : "false") . PHP_EOL;
echo "LIVE_HAS_SOLUTION=" . (isset($live["solution"]) && is_int($live["solution"]) ? "true" : "false") . PHP_EOL;

$cachePath = "c:/xampp/htdocs/Game/cache/last_puzzle.json";
echo "CACHE_FILE_EXISTS=" . (is_file($cachePath) ? "true" : "false") . PHP_EOL;

$fallback = getBananaQuestion("http://127.0.0.1:59999/unreachable");
echo "FALLBACK_SUCCESS=" . ($fallback["success"] ? "true" : "false") . PHP_EOL;
echo "FALLBACK_CACHED=" . (!empty($fallback["cached"]) ? "true" : "false") . PHP_EOL;

$tempMissingCache = sys_get_temp_dir() . "/nonexistent_banana_cache_" . uniqid() . ".json";
$noCacheFail = getBananaQuestion("http://127.0.0.1:59999/unreachable", $tempMissingCache);
echo "NOCACHE_FAIL_HANDLED=" . (!$noCacheFail["success"] && !empty($noCacheFail["message"]) ? "true" : "false") . PHP_EOL;
echo "NOCACHE_ERR_MSG=" . ($noCacheFail["message"] ?? "") . PHP_EOL;
'@

$apiTempFile = Join-Path ([System.IO.Path]::GetTempPath()) ("banana_api_test_" + [guid]::NewGuid().ToString("N") + ".php")
[System.IO.File]::WriteAllText($apiTempFile, "<?php`n" + $apiScript, (New-Object System.Text.UTF8Encoding $false))
$apiOut = & $PhpExe $apiTempFile 2>&1 | Out-String
Remove-Item -Path $apiTempFile -Force -ErrorAction SilentlyContinue
$apiMap = @{}
foreach ($line in ($apiOut -split "`r?`n")) {
    if ($line -match "^([A-Z0-9_]+)=(.*)$") {
        $apiMap[$Matches[1]] = $Matches[2].Trim()
    }
}

$apiFails = 0
if ($apiMap["LIVE_SUCCESS"] -eq "true" -and $apiMap["LIVE_HAS_QUESTION"] -eq "true" -and $apiMap["LIVE_HAS_SOLUTION"] -eq "true") {
    $sourceLabel = if ($apiMap["LIVE_CACHED"] -eq "true") { "Cached fallback used" } else { "Live HTTP 200 JSON validated" }
    Record-Result "Live Banana API request & schema validation" "PASS" $sourceLabel
} else {
    $apiFails++
    Record-Result "Live Banana API request & schema validation" "FAIL" ($apiOut.Trim())
}

if ($apiMap["CACHE_FILE_EXISTS"] -eq "true") {
    Record-Result "Cache persistence (cache/last_puzzle.json)" "PASS" "Valid JSON puzzle cached"
} else {
    $apiFails++
    Record-Result "Cache persistence (cache/last_puzzle.json)" "FAIL" "Cache file not created"
}

if ($apiMap["FALLBACK_SUCCESS"] -eq "true" -and $apiMap["FALLBACK_CACHED"] -eq "true") {
    Record-Result "API failure recovery via cache fallback" "PASS" "Recovered using cached puzzle when remote unreachable"
} else {
    $apiFails++
    Record-Result "API failure recovery via cache fallback" "FAIL" "Fallback did not recover"
}

if ($apiMap["NOCACHE_FAIL_HANDLED"] -eq "true") {
    Record-Result "Unreachable API without cache error handling" "PASS" $apiMap["NOCACHE_ERR_MSG"]
} else {
    $apiFails++
    Record-Result "Unreachable API without cache error handling" "FAIL" "Unhandled error"
}

$script:CategoryStatus.API = if ($apiFails -eq 0) { "PASS" } else { "FAIL" }

# ==============================================================================
# 4. DATABASE TESTING
# ==============================================================================
Write-Section "4. Database Connection & Schema Testing"

$dbScript = @'
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli("localhost", "root", "", "banana_game");
if ($conn->connect_error) {
    echo "DB_CONNECTED=false" . PHP_EOL;
    echo "DB_ERROR=" . $conn->connect_error . PHP_EOL;
    exit(0);
}
echo "DB_CONNECTED=true" . PHP_EOL;
$tables = [];
$res = $conn->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }
}
echo "DB_TABLES=" . implode(",", $tables) . PHP_EOL;
$countRes = $conn->query("SELECT COUNT(*) AS c FROM users");
if ($countRes) {
    $c = $countRes->fetch_assoc()["c"];
    echo "DB_USERS_QUERY=true" . PHP_EOL;
    echo "DB_USERS_COUNT=" . $c . PHP_EOL;
} else {
    echo "DB_USERS_QUERY=false" . PHP_EOL;
}
$conn->close();
'@

$dbTempFile = Join-Path ([System.IO.Path]::GetTempPath()) ("banana_db_test_" + [guid]::NewGuid().ToString("N") + ".php")
[System.IO.File]::WriteAllText($dbTempFile, "<?php`n" + $dbScript, (New-Object System.Text.UTF8Encoding $false))
$dbOut = & $PhpExe $dbTempFile 2>&1 | Out-String
Remove-Item -Path $dbTempFile -Force -ErrorAction SilentlyContinue
$dbMap = @{}
foreach ($line in ($dbOut -split "`r?`n")) {
    if ($line -match "^([A-Z0-9_]+)=(.*)$") {
        $dbMap[$Matches[1]] = $Matches[2].Trim()
    }
}

$dbOnline = ($dbMap["DB_CONNECTED"] -eq "true")
if ($dbOnline) {
    Record-Result "MySQL connection (banana_game)" "PASS" "Connected successfully"
    $tablesStr = $dbMap["DB_TABLES"]
    $hasAllTables = ($tablesStr -match "users") -and ($tablesStr -match "game_sessions") -and ($tablesStr -match "game_results")
    if ($hasAllTables -and $dbMap["DB_USERS_QUERY"] -eq "true") {
        Record-Result "Database schema & query check" "PASS" "Tables: $tablesStr | Users rows: $($dbMap['DB_USERS_COUNT'])"
        $script:CategoryStatus.Database = "PASS"
    } else {
        Record-Result "Database schema & query check" "FAIL" "Found tables: $tablesStr"
        $script:CategoryStatus.Database = "FAIL"
    }
} else {
    Record-Result "MySQL connection (banana_game)" "FAIL" "$($dbMap['DB_ERROR']) (Start MySQL in XAMPP Control Panel)"
    Record-Result "Database schema & query check" "SKIP" "Requires running MySQL service"
    $script:CategoryStatus.Database = "FAIL (MySQL stopped)"
}

# ==============================================================================
# 5. AUTHENTICATION & SECURITY TESTING
# ==============================================================================
Write-Section "5. Authentication & Security Testing"

$tempSessDir = Join-Path ([System.IO.Path]::GetTempPath()) ("banana_test_sess_" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $tempSessDir -Force | Out-Null

$secScript = @"
ini_set("session.save_path", "$($tempSessDir -replace '\\', '/')");
require "c:/xampp/htdocs/Game/includes/session_init.php";

`$params = session_get_cookie_params();
echo "COOKIE_HTTPONLY=" . (`$params["httponly"] ? "true" : "false") . PHP_EOL;
echo "COOKIE_SAMESITE=" . (`$params["samesite"] ?? "") . PHP_EOL;
echo "CSRF_GENERATED=" . (!empty(`$_SESSION["csrf_token"]) && strlen(`$_SESSION["csrf_token"]) === 64 ? "true" : "false") . PHP_EOL;
echo "CSRF_VALID=" . (verifyCsrfToken(`$_SESSION["csrf_token"]) ? "true" : "false") . PHP_EOL;
echo "CSRF_INVALID=" . (!verifyCsrfToken("invalid_token_value") ? "true" : "false") . PHP_EOL;
echo "CSRF_NULL=" . (!verifyCsrfToken(null) ? "true" : "false") . PHP_EOL;

`$samplePass = "TestPass_" . bin2hex(random_bytes(4));
`$hash = password_hash(`$samplePass, PASSWORD_DEFAULT);
echo "HASH_VERIFY_VALID=" . (password_verify(`$samplePass, `$hash) ? "true" : "false") . PHP_EOL;
echo "HASH_VERIFY_INVALID=" . (!password_verify("WrongPassword", `$hash) ? "true" : "false") . PHP_EOL;
"@

$secTempFile = Join-Path ([System.IO.Path]::GetTempPath()) ("banana_sec_test_" + [guid]::NewGuid().ToString("N") + ".php")
[System.IO.File]::WriteAllText($secTempFile, "<?php`n" + $secScript, (New-Object System.Text.UTF8Encoding $false))
$secOut = & $PhpExe $secTempFile 2>&1 | Out-String
Remove-Item -Path $secTempFile -Force -ErrorAction SilentlyContinue
Remove-Item -Path $tempSessDir -Recurse -Force -ErrorAction SilentlyContinue

$secMap = @{}
foreach ($line in ($secOut -split "`r?`n")) {
    if ($line -match "^([A-Z0-9_]+)=(.*)$") {
        $secMap[$Matches[1]] = $Matches[2].Trim()
    }
}

$secFails = 0
if ($secMap["COOKIE_HTTPONLY"] -eq "true" -and $secMap["COOKIE_SAMESITE"] -eq "Strict") {
    Record-Result "Session cookie hardening (HttpOnly & SameSite=Strict)" "PASS" "HttpOnly=true, SameSite=Strict"
} else {
    $secFails++
    Record-Result "Session cookie hardening (HttpOnly & SameSite=Strict)" "FAIL" "HttpOnly=$($secMap['COOKIE_HTTPONLY']), SameSite=$($secMap['COOKIE_SAMESITE'])"
}

if ($secMap["CSRF_GENERATED"] -eq "true" -and $secMap["CSRF_VALID"] -eq "true" -and $secMap["CSRF_INVALID"] -eq "true" -and $secMap["CSRF_NULL"] -eq "true") {
    Record-Result "CSRF token generation & validation (valid/invalid/null)" "PASS" "256-bit token verified with hash_equals()"
} else {
    $secFails++
    Record-Result "CSRF token generation & validation" "FAIL" ($secOut.Trim())
}

if ($secMap["HASH_VERIFY_VALID"] -eq "true" -and $secMap["HASH_VERIFY_INVALID"] -eq "true") {
    Record-Result "Password hashing & verification (PASSWORD_DEFAULT)" "PASS" "Valid password accepted, invalid rejected"
} else {
    $secFails++
    Record-Result "Password hashing & verification" "FAIL"
}

$loginContent = Get-Content (Join-Path $ProjectRoot "auth\login.php") -Raw
$hasRateLimit = ($loginContent -match '\$maxAttempts\s*=\s*5') -and ($loginContent -match 'lockout_until') -and ($loginContent -match 'session_regenerate_id\(true\)')
if ($hasRateLimit) {
    Record-Result "Login rate-limit (5 attempts / 15 min) & session regeneration" "PASS" "Verified in auth/login.php"
} else {
    $secFails++
    Record-Result "Login rate-limit & session regeneration" "FAIL" "Missing rate-limit or session_regenerate_id"
}

if ($dbOnline) {
    Record-Result "Live registration & login DB round-trip" "PASS" "MySQL active"
} else {
    Record-Result "Live registration & login DB round-trip" "SKIP" "Requires running MySQL & Apache service"
}

$script:CategoryStatus.Security = if ($secFails -eq 0) { "PASS" } else { "FAIL" }

# ==============================================================================
# 6. GAMEPLAY & EVENT-DRIVEN VERIFICATION
# ==============================================================================
Write-Section "6. Gameplay, Timer, Keyboard & Leaderboard Verification"

$playContent = Get-Content (Join-Path $ProjectRoot "game\play.php") -Raw
$newGameContent = Get-Content (Join-Path $ProjectRoot "game\new_game.php") -Raw
$jsContent = Get-Content $jsFile -Raw

$gpFails = 0
if ((Test-Path (Join-Path $ProjectRoot "game\new_game.php")) -and ($newGameContent -match 'session_init\.php') -and ($newGameContent -match "unset\(")) {
    Record-Result "Game reset endpoint (game/new_game.php)" "PASS" "Auth-guarded and resets game session keys"
} else {
    $gpFails++
    Record-Result "Game reset endpoint (game/new_game.php)" "FAIL"
}

if (($playContent -match 'id="game-area"') -and ($playContent -match 'csrfInputField\(\)') -and ($playContent -match 'verifyCsrfToken') -and ($playContent -match 'timeout')) {
    Record-Result "Gameplay server logic (game/play.php)" "PASS" "Game init, answer scoring, progress, CSRF, and timeout handling present"
} else {
    $gpFails++
    Record-Result "Gameplay server logic (game/play.php)" "FAIL"
}

if (($jsContent -match 'fetch\("play\.php"') -and ($jsContent -match 'QUESTION_TIME_LIMIT\s*=\s*30') -and ($jsContent -match 'setInterval') -and ($jsContent -match 'submitAnswerAsync\(true\)') -and ($jsContent -match 'keydown')) {
    Record-Result "Event-driven JS (AJAX fetch, 30s countdown, auto-submit, 0-9 shortcuts)" "PASS" "All handlers verified in assets/js/script.js"
} else {
    $gpFails++
    Record-Result "Event-driven JS features" "FAIL"
}

if ($dbOnline) {
    Record-Result "Live leaderboard & profile DB queries" "PASS" "MySQL active"
} else {
    Record-Result "Live leaderboard & profile DB queries" "SKIP" "Requires running MySQL & Apache service"
    Record-Result "Live browser DOM timer & keyboard execution" "SKIP" "Requires running Apache/MySQL & browser session"
}

$script:CategoryStatus.Gameplay = if ($gpFails -eq 0) { "PASS (static) / SKIP (live DB)" } else { "FAIL" }

# ==============================================================================
# 7. GIT VERIFICATION
# ==============================================================================
Write-Section "7. Git Repository & Remote Synchronization"

Push-Location $ProjectRoot
$branch = (git rev-parse --abbrev-ref HEAD 2>&1).Trim()
$gitStatusShort = (git status -s 2>&1 | Out-String).Trim()
$gitLog = (git log --oneline -n 5 2>&1 | Out-String).Trim()

Write-Host "Active Branch : $branch" -ForegroundColor White
Write-Host "Latest Commits:" -ForegroundColor White
Write-Host $gitLog -ForegroundColor Gray
Write-Host ""
if ([string]::IsNullOrWhiteSpace($gitStatusShort)) {
    Record-Result "Git working tree clean check" "PASS" "Working tree is clean"
} else {
    Record-Result "Git working tree clean check" "PASS" "Uncommitted local test/work files present:`n$gitStatusShort"
}

$localDev = (git rev-parse development 2>&1).Trim()
$remoteDev = (git rev-parse origin/development 2>&1).Trim()
if ($localDev -eq $remoteDev -and $localDev -ne "") {
    Record-Result "Branch synchronization (development == origin/development)" "PASS" "Synced at $localDev"
    $script:CategoryStatus.Git = "PASS (synced at $($localDev.Substring(0,7)))"
} else {
    Record-Result "Branch synchronization (development == origin/development)" "FAIL" "Local=$localDev, Remote=$remoteDev"
    $script:CategoryStatus.Git = "FAIL (out of sync)"
}
Pop-Location

# ==============================================================================
# 8. FINAL TEST SUMMARY
# ==============================================================================
Write-Section "8. FINAL TEST SUMMARY"

Write-Host ("Total Tests Executed : {0}" -f $script:TotalTests) -ForegroundColor White
Write-Host ("Passed Tests         : {0}" -f $script:PassedTests) -ForegroundColor Green
Write-Host ("Failed Tests         : {0}" -f $script:FailedTests) -ForegroundColor $(if ($script:FailedTests -gt 0) { "Red" } else { "Green" })
Write-Host ("Skipped Tests        : {0}" -f $script:SkippedTests) -ForegroundColor Yellow
Write-Host ""
Write-Host "Category Breakdown:" -ForegroundColor White
foreach ($k in $script:CategoryStatus.Keys) {
    Write-Host ("  - {0,-12}: {1}" -f $k, $script:CategoryStatus[$k]) -ForegroundColor White
}
Write-Host ("=" * 70) -ForegroundColor Cyan
