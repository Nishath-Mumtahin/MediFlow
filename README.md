# MediFlow – Hospital Operations Management System

MediFlow is a DBMS Lab project for managing hospital operations: patients, doctors, departments, appointments, treatments, prescriptions, medications, rooms and admissions, with role-based access (Admin, Doctor, Patient).

## Tech Stack
- PHP 8+ (PDO) · MySQL / MariaDB · Tailwind CSS (CDN)
- Run with XAMPP (Apache + MySQL)

## Setup
1. Copy this folder to `xampp/htdocs/MediFlow`.
2. Start Apache and MySQL in XAMPP.
3. Import `schema.sql` in phpMyAdmin (creates the `mediflow` database with demo data).
4. Open `http://localhost/MediFlow/`.
5. DB settings are in `config.php` (default: root, no password).

## Demo Logins (password for all: `password`)
| Role    | Username |
|---------|----------|
| Admin   | admin    |
| Doctor  | m.karim  |
| Doctor  | r.uddin  |
| Patient | rahim    |
| Patient | fatema   |

New sign-ups are created as patients with a linked patient profile.

## Structure
```text
MediFlow/
├── auth/        login, signup, logout
├── dashboard/   admin/doctor/patient pages
├── docs/        ER diagram, UI design
├── config.php   settings, session, helpers
├── database.php PDO data-access functions
└── schema.sql   tables + demo data
```

*Developed for academic purposes as part of the DBMS Lab course.*
