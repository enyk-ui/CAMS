<?php

declare(strict_types=1);

function studentColumnExists(mysqli $mysqli, string $columnName): bool
{
    $safeColumn = $mysqli->real_escape_string($columnName);
    $result = $mysqli->query("SHOW COLUMNS FROM students LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function normalizeStudentRegistrationInput(array $input): array
{
    return [
        'student_no' => trim((string)($input['student_no'] ?? $input['student_id'] ?? $input['student_id_text'] ?? '')),
        'first_name' => trim((string)($input['first_name'] ?? '')),
        'middle_initial' => trim((string)($input['middle_initial'] ?? '')),
        'last_name' => trim((string)($input['last_name'] ?? '')),
        'extension' => trim((string)($input['extension'] ?? '')),
        'email' => trim((string)($input['email'] ?? '')),
        'year' => isset($input['year']) && $input['year'] !== '' ? (int)$input['year'] : null,
        'section' => trim((string)($input['section'] ?? '')),
    ];
}

function validateStudentRegistrationRequired(array $data): ?string
{
    if ($data['student_no'] === '' || $data['first_name'] === '' || $data['last_name'] === '' || $data['email'] === '') {
        return 'Missing required fields: student_id, first_name, last_name, email';
    }

    return null;
}

function buildStudentWriteParts(mysqli $mysqli, array $data): array
{
    $parts = [
        'student_id' => $data['student_no'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'email' => $data['email'],
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
