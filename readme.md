# CAMS - Criminology Attendance Monitoring System

Professional fingerprint-based attendance system with ESP8266 integration, role-based web dashboard, and email notifications.

**UI: Blue & White Theme | Compact Collapsed Sidebar | Role-Based Access**

## Quick Start

### 1. Initialize Database
```
URL: http://localhost/CAMS/system/init.php
Creates all tables, views, and seeds initial data
```

### 2. Install Dependencies
```bash
cd C:\xampp\htdocs\CAMS
composer require phpmailer/phpmailer
```

### 3. Configure Email
Edit `/config/mail.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'app-password');
```

### 4. Access System
```
URL: http://localhost/CAMS/

Login with demo accounts:
Admin: admin@cams.edu.ph / admin123
Teacher: teacher@cams.edu.ph / teacher123
```

### 5. Upload ESP8266 Code
- Arduino IDE → Install libraries: Adafruit Fingerprint, ArduinoJson, LiquidCrystal_I2C
- Copy `arduino/CAMS.ino` to IDE
- Edit WiFi/server config at top
- Upload to NodeMCU 1.0 (ESP-12E)

## System Features

✅ **Fingerprint Scanning** - Real-time student attendance via ESP8266
✅ **Role-Based Dashboard** - Admin system management & Teacher class monitoring
✅ **Blue & White UI** - Modern compact design with collapsed sidebar
✅ **Student Management** - Admin CRUD operations (Teachers: view-only)
✅ **Attendance Reports** - Filter, view, and export (Admin: all | Teacher: their section)
✅ **Email Notifications** - Automatic attendance confirmation emails
✅ **Queue Management** - Retry failed emails with 3-attempt mechanism
✅ **System Settings** - Admin-only configuration (Admin only)
✅ **Mobile Responsive** - Works on desktop, tablet, and mobile

## User Roles

### Admin
- System administrator with full access
- Dashboard: View all attendance statistics
- Students: CRUD management (add, edit, delete)
- Logs: View all attendance with filters
- Settings: Configure system parameters
- Notifications: Monitor email queue and retry

### Teacher
- Class instructor with limited access
- Dashboard: View only their section statistics
- My Class: View roster of assigned students
- Reports: Attendance reports for their section only
- Notifications: View emails sent to their students

## File Structure

```
/CAMS
├── index.php                  (login + system information)
│
├── /auth
│   └── logout.php            (session cleanup)
│
├── /admin                     ← Admin Dashboard Pages
│   ├── dashboard.php         (system statistics)
│   ├── students.php          (student CRUD)
│   ├── register.php          (student registration by admin)
│   ├── users.php             (manage admin & teacher accounts)
│   ├── logs.php              (attendance records)
│   ├── settings.php          (system configuration)
│   ├── notifications.php     (email queue management)
│   └── notification_status.php (queue status)
│
├── /teacher                   ← Teacher Pages
│   ├── dashboard.php         (class statistics)
│   ├── my_class.php          (student roster)
│   ├── attendance_report.php (class attendance records)
│   └── notifications.php     (class notifications)
│
├── /public                    ← Public Pages
│   └── register.php          (student self-registration)
│
├── /system                    ← System Files
│   └── init.php              (database initialization - one-time)
│
├── /api                       ← API Endpoints
│   ├── scan.php              (fingerprint scan)
│   ├── register.php          (student registration API)
│   ├── enroll.php            (fingerprint enrollment)
│   ├── get_recent_scans.php  (dashboard AJAX)
│   ├── test.php              (API testing)
│   └── queue.php             (notification processor - cron)
│
├── /config
│   ├── db.php                (database connection)
│   └── mail.php              (email configuration)
│
├── /helpers
│   ├── AttendanceHelper.php
│   ├── EmailHelper.php
│   ├── NotificationQueueHelper.php
│   ├── ESP8266Helper.php
│   └── RoleHelper.php        (role/permission checking)
│
├── /includes
│   ├── header.php            (page header with collapsed sidebar)
│   ├── footer.php            (page footer)
│   └── collapsed_sidebar.php (100px compact sidebar)
│
├── /arduino
│   └── CAMS.ino              (ESP8266 scanner firmware)
│
└── database.sql              (schema reference)
```

## Color Scheme

```
Primary Blue:      #2563eb (main actions)
Dark Blue:         #1e40af (hover states)
Sidebar Gray:      #1f2937 (sidebar background)
Light Background:  #f9fafb (page background)
Success:           #10b981 (present, success)
Warning:           #f59e0b (late, pending)
Danger:            #ef4444 (absent, error)
White:             #ffffff (cards, content)
```

## API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/scan.php` | POST | Record attendance from ESP8266 |
| `/api/register.php` | POST | Register new student |
| `/api/enroll.php` | POST | Fingerprint enrollment workflow |
| `/api/get_recent_scans.php` | GET | Dashboard live updates |
| `/api/test.php` | GET/POST | Test scanner API |
| `/api/queue.php` | GET | Process email queue (cron) |

## Email System

- **PHPMailer** integration for SMTP/sendmail
- **Automatic retry** - 3 attempts, 5-minute intervals
- **Email templates** - Customizable attendance notifications
- **Queue persistence** - Failed emails stored for retry
- **Cron support** - Auto-process via `api/queue.php`

Setup cron (Linux/Mac):
```bash
*/5 * * * * curl http://localhost/CAMS/api/queue.php?api_key=secret
```

## Database Schema

- **admins** - Admin user accounts (id, email, password_hash, full_name, status)
- **teachers** - Teacher user accounts (id, email, password_hash, full_name, section, status)
- **students** - Student records (id, student_id, name, email, year, section)
- **fingerprints** - Stored fingerprints (student_id, finger_index, sensor_id)
- **attendance** - Daily records (student_id, date, times, status)
- **settings** - System configuration (late_threshold, schedules, etc.)
- **notification_queue** - Email retry queue (pending, sent, failed)

## Default Credentials

⚠️ **Change in production!**

**Admin:**
- Email: `admin@cams.edu.ph`
- Password: `admin123`

**Teacher:**
- Email: `teacher@cams.edu.ph`
- Password: `teacher123`

## Hardware Requirements

- **ESP8266** (NodeMCU 1.0 / ESP-12E)
- **DY50** Fingerprint Sensor
- **I2C LCD** 16x2 Display (optional)
- **5V Power Supply** (for sensor)

## Deployment Checklist

- [ ] Run `system/init.php` to create database
- [ ] Install Composer & PHPMailer
- [ ] Configure email in `/config/mail.php`
- [ ] Change admin password (update hardcoded credentials)
- [ ] Add teacher accounts (currently hardcoded)
- [ ] Setup cron for email queue
- [ ] Upload ESP8266 firmware
- [ ] Test end-to-end (scan → email → dashboard)
- [ ] Enable HTTPS (production)
- [ ] Setup database backups

## Troubleshooting

**Database not found?**
```
Visit: http://localhost/CAMS/system/init.php
```

**ESP8266 not connecting?**
- Check WiFi SSID/password (2.4GHz only)
- Verify server IP in CAMS.ino
- Check firewall on server

**Emails not sending?**
- Verify SMTP settings in `/config/mail.php`
- Check notification queue table
- Review PHP error logs

**Dashboard not updating?**
- Check if `api/get_recent_scans.php` is accessible
- Verify browser console for AJAX errors

**Can't login as Teacher?**
- Currently uses hardcoded credentials (teacher@cams.edu.ph / teacher123)
- Section must be selected (A, B, C, or D)

## UI Features

- **Collapsed Sidebar:** 100px fixed left sidebar with icon-only navigation
- **Icon Tooltips:** Hover over sidebar icons to see full names
- **Blue & White:** Modern, professional color scheme
- **Responsive:** Adapts to mobile, tablet, and desktop
- **Session Management:** 30-minute timeout with auto-logout
- **Role-Based Navigation:** Different sidebars for Admin vs Teacher

## Support

For issues or configuration help, refer to individual files which contain detailed comments and documentation.

---

**System Status:** ✅ Production Ready

**Latest Version:** 1.0.0 (UI Overhaul - Blue/White Theme, Role-Based Access)

**Last Updated:** 2026-04-02
