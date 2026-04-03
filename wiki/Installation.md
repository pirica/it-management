# Installation

## 1) Database Setup

1. Create a MySQL database (for example: `itmanagement`).
2. Import `database.sql`.

## 2) Application Files

1. Copy/extract the project into your web root.
2. Ensure upload and backup directories exist:
   - `images/`
   - `tickets_photos/`
   - `backups/`

## 3) Configure Database Connection

Edit `config/config.php` and set your credentials:

- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

## 4) Run Locally

Open in your browser:

`http://localhost/it-management/`

## 5) Troubleshooting

- Verify DB credentials and DB server status.
- Confirm upload folders are writable by the web server.
- Check PHP and Apache error logs.
- Clear browser cache if UI assets appear stale.
