<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/**
 * Send JSON response and stop script execution.
 */
function api_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/**
 * Enforce an HTTP method.
 */
function require_method(string $expectedMethod): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $expectedMethod) {
        api_response(405, [
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }
}

/**
 * Read JSON request body as an associative array.
 */
function read_json_body(): array
{
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        return [];
    }

    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        api_response(400, [
            'success' => false,
            'message' => 'Invalid JSON body'
        ]);
    }

    return $data;
}

/**
 * Parse a positive integer field from input.
 */
function require_positive_int(array $source, string $key): int
{
    if (!isset($source[$key])) {
        api_response(400, [
            'success' => false,
            'message' => "Missing required field: {$key}"
        ]);
    }

    $value = filter_var($source[$key], FILTER_VALIDATE_INT);
    if ($value === false || $value <= 0) {
        api_response(400, [
            'success' => false,
            'message' => "Invalid integer value for: {$key}"
        ]);
    }

    return (int)$value;
}

