# DOT SHIP

A premium web-based courier and parcel management system built with PHP 8+, MongoDB, HTML5, CSS3, and Bootstrap 5.

## Folder Structure

```text
DOT SHIP/
├─ admin/
├─ assets/
│  ├─ css/
│  ├─ img/
│  └─ js/
├─ config/
├─ includes/
├─ composer.json
├─ index.php
├─ login.php
├─ register.php
├─ dashboard.php
├─ book.php
├─ shipments.php
├─ track.php
└─ logout.php
```

## Requirements

- PHP 8.0 or later
- MongoDB server running locally or remotely
- Composer
- PHP MongoDB extension enabled

## Setup

1. Install dependencies.

```bash
composer install
```

2. Copy `.env.example` to `.env` if you want custom values and adjust the MongoDB settings.

3. Make sure MongoDB is running and the URI in `MONGODB_URI` is reachable.

4. Start the project from the workspace root.

```bash
php -S 127.0.0.1:8000
```

5. Open the app in your browser.

```text
http://127.0.0.1:8000
```

## Default Demo Accounts

- Admin: `admin@dotship.local` / `Admin@1234`
- Customer: `demo@dotship.local` / `Demo@1234`

## Notes

- The app seeds demo users and sample shipments on first run.
- Tracking is available publicly from the landing page.
- Admin routes live under `admin/`.