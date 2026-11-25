<?php
/**
 * Application environment & quiz debug logging configuration.
 *
 * Local development (localhost / 127.0.0.1 / CLI) enables QUIZ_DEBUG_ANSWER
 * by default unless overridden via environment variables:
 *   APP_ENV=production
 *   QUIZ_DEBUG_ANSWER=false
 */

if (!function_exists('getAppEnvironment')) {
    function getAppEnvironment(): string
    {
        $env = getenv('APP_ENV');
        if ($env !== false && $env !== '') {
            return strtolower(trim($env));
        }

        $sapi = php_sapi_name();
        if ($sapi === 'cli' || $sapi === 'cli-server') {
            return 'development';
        }

        $host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        $hostOnly = explode(':', $host)[0];
        if (in_array($hostOnly, ['localhost', '127.0.0.1', '::1'], true)) {
            return 'development';
        }

        return 'production';
    }
}

if (!function_exists('isQuizDebugEnabled')) {
    function isQuizDebugEnabled(): bool
    {
        $appEnv = getAppEnvironment();
        if ($appEnv !== 'development') {
            return false;
        }

        $flag = getenv('QUIZ_DEBUG_ANSWER');
        if ($flag !== false && $flag !== '') {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }

        return true;
    }
}

if (!function_exists('logQuizDebugAnswer')) {
    function logQuizDebugAnswer(
        int $questionId,
        string $question,
        int $correctAnswer,
        int $timerSeconds = 40
    ): void {
        if (!isQuizDebugEnabled()) {
            return;
        }

        $displayQuestion = $question;
        $parsedPath = parse_url($question, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $base = basename($parsedPath);
            if ($base !== '') {
                $displayQuestion = $base;
            }
        }

        $entry = "========================================" . PHP_EOL
            . "          QUIZ DEBUG" . PHP_EOL
            . "========================================" . PHP_EOL
            . "Question ID : " . $questionId . PHP_EOL
            . "Question    : " . $displayQuestion . PHP_EOL
            . "Answer      : " . $correctAnswer . PHP_EOL
            . "Timer       : " . $timerSeconds . " seconds" . PHP_EOL
            . "Time        : " . date('H:i:s') . PHP_EOL
            . "========================================" . PHP_EOL . PHP_EOL;

        $logFile = __DIR__ . '/../logs/quiz-debug.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (is_dir($logDir) && is_writable($logDir)) {
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        }
    }
}
