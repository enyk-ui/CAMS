-- CAMS pre-change safety checklist
-- 1) Backup (run from shell): mysqldump -u <user> -p <database> > cams_backup.sql

-- 2) Record baseline counts
SELECT COUNT(*) AS students_count FROM students;
SELECT COUNT(*) AS attendance_count FROM attendance;
SELECT COUNT(*) AS school_years_count FROM school_years;

-- 3) Validate critical nullable fields
SELECT COUNT(*) AS null_student_name_rows
FROM students
WHERE first_name IS NULL OR last_name IS NULL;

SELECT COUNT(*) AS null_attendance_fk_rows
FROM attendance
WHERE student_id IS NULL OR attendance_date IS NULL;

SELECT COUNT(*) AS null_school_year_label_rows
FROM school_years
WHERE label IS NULL OR TRIM(label) = '';

-- 4) Validate relationship integrity
SELECT COUNT(*) AS orphan_attendance_rows
FROM attendance a
LEFT JOIN students s ON s.id = a.student_id
WHERE s.id IS NULL;

-- 5) Optional performance indexes
ALTER TABLE students ADD INDEX idx_students_last_name (last_name);
ALTER TABLE students ADD INDEX idx_students_section (section);
ALTER TABLE users ADD INDEX idx_users_school_year_label (school_year_label);
ALTER TABLE attendance ADD INDEX idx_attendance_date_student (attendance_date, student_id);
