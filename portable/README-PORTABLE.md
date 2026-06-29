# OpenVyapar ERP — Portable Edition

**Free & Open-Source GST-Ready ERP for Indian Small Businesses**

> Version: 1.0.0 | Developer: MANORANJAN | https://manoranjan.dev

---

## What's Included

```
OpenVyapar-ERP-Portable\
├── php\            → PHP 8.2 (portable, no install needed)
├── app\            → Laravel 12 backend (API server)
├── www\            → Vue 3 frontend (pre-built static files)
├── launcher\       → Setup and reset utilities
├── logs\           → Server & error logs
├── start.bat       → ← DOUBLE-CLICK THIS TO START
├── stop.bat        → Stop all servers
└── README.txt      → This file
```

---

## Quick Start

1. **Extract** the ZIP to any folder (e.g., `C:\OpenVyapar` or your Desktop)
2. **Double-click** `start.bat`
3. On first launch, setup runs automatically (~30 seconds)
4. Browser opens at **http://localhost:8080**

**Default Login:**
```
Email:    admin@demo.com
Password: password
```

---

## System Requirements

| Requirement | Minimum |
|---|---|
| Windows | 10 or 11 (64-bit) |
| RAM | 512 MB free |
| Disk | 500 MB free |
| Internet | Not required (offline-first) |

> **No installation needed.** PHP and all dependencies are included.
> Your data is stored in a local SQLite file inside the `app\database\` folder.

---

## How It Works

```
start.bat
   │
   ├── [First run only] setup.bat
   │       ├── Creates SQLite database
   │       ├── Runs migrations (creates tables)
   │       └── Seeds demo company + admin user
   │
   ├── Starts API server   → http://localhost:8000  (PHP built-in server)
   ├── Starts Frontend     → http://localhost:8080  (PHP static file server)
   └── Opens browser       → http://localhost:8080
```

---

## Data & Backup

Your data lives in:
```
app\database\openvyapar.sqlite
```

**To back up your data:** copy this file somewhere safe.

**To restore:** stop the server, replace the file, start again.

You can also use the built-in **Backup & Restore** feature inside the app
(Settings → Backup & Restore) which creates ZIP backups you can download.

---

## Moving to a Different Computer

1. Stop the server (`stop.bat` or close the window)
2. Copy the entire folder to the new machine
3. Run `start.bat` on the new machine

That's it — no re-setup needed. All data moves with it.

---

## Resetting to Factory Defaults

Run `launcher\reset.bat` — this will **delete all data** and let you start fresh.

---

## Ports Used

| Port | Purpose |
|---|---|
| 8080 | Frontend (Vue 3 app) |
| 8000 | API server (Laravel) |

If you have a port conflict, edit `start.bat` and change the `PORT` and `PORT_FRONT` values.

---

## Telegram & Email Alerts

To enable Telegram or email alerts:
1. Open the app → **Alerts & Notifications**
2. Configure your Telegram Bot Token + Chat ID, or SMTP settings
3. Click **Save** then **Send Test**

All credentials are stored locally in your SQLite database.

---

## Firewall / Antivirus Note

Windows Defender or your antivirus may ask to allow `php.exe` on first run.
Click **Allow** — this is the portable PHP server that runs the app locally.
The app does **not** connect to the internet (except for Telegram alerts if you configure them).

---

## Upgrading

1. Back up `app\database\openvyapar.sqlite`
2. Download the new version ZIP
3. Extract to a new folder
4. Copy your database file into `app\database\`
5. Run `start.bat` — migrations will apply automatically

---

## License

OpenVyapar ERP is released under the **GNU Affero General Public License v3 (AGPL-3.0)**.

- Free to use, modify, and distribute
- Source code: https://github.com/manoranjan2050/OpenVyapar-ERP
- Must keep open source if deployed as a network service

---

## Support & Contact

- GitHub Issues: https://github.com/manoranjan2050/OpenVyapar-ERP/issues
- Email: manoranjan2050@live.com
- Website: https://manoranjan.dev

**Donations welcome:** PayPal → manoranjan2050@live.com
(Email before sending. Crypto accepted too — ask for wallet address.)

---

*Made with love in Odisha, India 🇮🇳*
*"Vyapar" (व्यापार) means "Business" in Hindi*
