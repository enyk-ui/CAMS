<?php

declare(strict_types=1);

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function capitalizeEachWord(string $value): string
{
    $clean = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($clean === '') {
        return '';
    }

    return ucwords(strtolower($clean));
}

function normalizeStudentRegistrationInput(array $input): array
{
    return [
        'first_name' => capitalizeEachWord((string)($input['first_name'] ?? '')),
        'middle_initial' => strtoupper(substr(trim((string)($input['middle_initial'] ?? '')), 0, 1)),
        'last_name' => capitalizeEachWord((string)($input['last_name'] ?? '')),
        'extension' => capitalizeEachWord((string)($input['extension'] ?? '')),
        'year' => isset($input['year']) && $input['year'] !== '' ? (int)$input['year'] : null,
        'section' => capitalizeEachWord((string)($input['section'] ?? '')),
    ];
}

function validateStudentRegistrationRequired(array $data): ?string
{
    if ($data['first_name'] === '' || $data['last_name'] === '') {
        return 'Missing required fields: first_name, last_name';
    }

    return null;
}

function buildStudentWriteParts(mysqli $mysqli, array $data): array
{
    $parts = [
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'year' => $data['year'],
        'section' => $data['section'] !== '' ? $data['section'] : null,
    ];

    if (studentColumnExists($mysqli, 'middle_initial')) {
        $parts['middle_initial'] = $data['middle_initial'];
    }

    if (studentColumnExists($mysqli, 'extension')) {
        $parts['extension'] = $data['extension'];
    }

    return $parts;
}

function bindTypeForValue($value): string
{
    return is_int($value) ? 'i' : 's';
}
