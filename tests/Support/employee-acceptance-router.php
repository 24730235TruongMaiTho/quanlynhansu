<?php

declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$prefix = '/_employee_acceptance_health/';
$runId = getenv('EMPLOYEE_ACCEPTANCE_RUN_ID');

if (str_starts_with($requestPath, $prefix)) {
    $requestedRunId = substr($requestPath, strlen($prefix));
    if (is_string($runId)
        && preg_match('/\A[a-f0-9]{12}\z/', $runId) === 1
        && hash_equals($runId, $requestedRunId)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['run_id' => $runId], JSON_THROW_ON_ERROR);
        return;
    }

    http_response_code(404);
    return;
}

$public = realpath(__DIR__.'/../../public');
$storage = realpath(__DIR__.'/../../storage/app/public');
$candidate = $public === false
    ? false
    : realpath($public.DIRECTORY_SEPARATOR.ltrim(rawurldecode($requestPath), '/\\'));

if ($public !== false && $candidate !== false && is_file($candidate)
    && ! is_link($candidate)
    && (str_starts_with(
        strtolower(rtrim(str_replace('\\', '/', $candidate), '/').'/' ),
        strtolower(rtrim(str_replace('\\', '/', $public), '/').'/' ),
    ) || ($storage !== false && str_starts_with(
        strtolower(rtrim(str_replace('\\', '/', $candidate), '/').'/' ),
        strtolower(rtrim(str_replace('\\', '/', $storage), '/').'/' ),
    )))) {
    return false;
}

require __DIR__.'/../../public/index.php';
