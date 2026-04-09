<?php
/**
 * SchoolYearHelper
 * Provides school year table management and active/default school year helpers.
 */

class SchoolYearHelper
{
    public static function ensureSettingsTable(mysqli $mysqli): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL,
                setting_value VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        return (bool) $mysqli->query($sql);
    }

    public static function ensureSchoolYearsTable(mysqli $mysqli): bool
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS school_years (
                id INT AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(20) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_school_year_label (label),
                INDEX idx_school_year_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        return (bool) $mysqli->query($sql);
    }

    public static function getDefaultLabel(?DateTime $date = null): string
    {
        $date = $date ?: new DateTime();
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        if ($month >= 6) {
            return $year . '-' . ($year + 1);
        }

        return ($year - 1) . '-' . $year;
    }

    public static function labelToRange(string $label): ?array
    {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $label, $matches)) {
            return null;
        }

        $startYear = (int) $matches[1];
        $endYear = (int) $matches[2];

        if ($endYear !== $startYear + 1) {
            return null;
        }

        return [
            'start_date' => sprintf('%04d-06-01', $startYear),
            'end_date' => sprintf('%04d-05-31', $endYear),
        ];
    }

    public static function upsertSetting(mysqli $mysqli, string $key, string $value): void
    {
        $stmt = $mysqli->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }

    public static function ensureSchoolYearSupport(mysqli $mysqli): bool
    {
        if (!self::ensureSettingsTable($mysqli)) {
            return false;
        }

        if (!self::ensureSchoolYearsTable($mysqli)) {
            return false;
        }

        $active = self::getActiveSchoolYear($mysqli);
        if ($active) {
            self::upsertSetting($mysqli, 'school_year', $active['label']);
            return true;
        }

        $defaultLabel = self::getDefaultLabel();
        $range = self::labelToRange($defaultLabel);
        if (!$range) {
            return false;
        }

        $stmt = $mysqli->prepare('INSERT INTO school_years (label, start_date, end_date, is_active) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('sss', $defaultLabel, $range['start_date'], $range['end_date']);
        $stmt->execute();
        $stmt->close();

        self::upsertSetting($mysqli, 'school_year', $defaultLabel);

        return true;
    }

    public static function getAllSchoolYears(mysqli $mysqli): array
    {
        $rows = [];
        $result = $mysqli->query('SELECT id, label, start_date, end_date, is_active FROM school_years ORDER BY start_date DESC');
        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function getActiveSchoolYear(mysqli $mysqli): ?array
    {
        $result = $mysqli->query('SELECT id, label, start_date, end_date, is_active FROM school_years WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1');
        if (!$result) {
            return null;
        }

        $row = $result->fetch_assoc();
        return $row ?: null;
    }

    public static function setActiveSchoolYear(mysqli $mysqli, int $schoolYearId): bool
    {
        if ($schoolYearId <= 0) {
            return false;
        }

        $mysqli->begin_transaction();
        try {
            $mysqli->query('UPDATE school_years SET is_active = 0');

            $stmt = $mysqli->prepare('UPDATE school_years SET is_active = 1 WHERE id = ?');
            $stmt->bind_param('i', $schoolYearId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('SELECT label FROM school_years WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $schoolYearId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$row) {
                throw new RuntimeException('School year not found');
            }

            self::upsertSetting($mysqli, 'school_year', $row['label']);
            $mysqli->commit();
            return true;
        } catch (Throwable $e) {
            $mysqli->rollback();
            return false;
        }
    }

    public static function createSchoolYear(mysqli $mysqli, string $label, string $startDate, string $endDate, bool $setActive): bool
    {
        if (!preg_match('/^\d{4}-\d{4}$/', $label)) {
            return false;
        }

        if ($startDate > $endDate) {
            return false;
        }

        $stmt = $mysqli->prepare('INSERT INTO school_years (label, start_date, end_date, is_active) VALUES (?, ?, ?, 0)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sss', $label, $startDate, $endDate);

        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $newId = (int) $mysqli->insert_id;
        $stmt->close();

        if ($setActive) {
            return self::setActiveSchoolYear($mysqli, $newId);
        }

        return true;
    }

    public static function getEffectiveSchoolYearRange(mysqli $mysqli): array
    {
        $active = self::getActiveSchoolYear($mysqli);
        if ($active) {
            return $active;
        }

        $label = self::getDefaultLabel();
        $range = self::labelToRange($label);

        return [
            'id' => 0,
            'label' => $label,
            'start_date' => $range ? $range['start_date'] : date('Y-01-01'),
            'end_date' => $range ? $range['end_date'] : date('Y-12-31'),
            'is_active' => 1,
        ];
    }
}
