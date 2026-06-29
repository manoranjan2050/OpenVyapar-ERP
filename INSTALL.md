# OpenVyapar ERP — Complete Installation Guide

> **Indian GST-ready ERP for small businesses**
> Works on Windows, macOS, and Linux.

---

## What You Need (Prerequisites)

Install these once. If already installed, skip.

| Software | Version | Download |
|---|---|---|
| **PHP** | 8.2 or higher | https://www.php.net/downloads |
| **Composer** | Latest | https://getcomposer.org/download |
| **Node.js** | 18 or higher | https://nodejs.org |
| **MySQL / MariaDB** | 8.0+ / 10.6+ | https://dev.mysql.com/downloads or https://mariadb.org |
| **Git** | Latest | https://git-scm.com |

### Quick check — paste this in your terminal:
```bash
php -v        # Should show PHP 8.2+
composer -V   # Should show Composer 2.x
node -v       # Should show v18+
npm -v        # Should show 9+
mysql -V      # Should show MySQL 8+ or MariaDB 10.6+
git --version # Any version is fine
```

---

## Step 1 — Download the Project

```bash
git clone https://github.com/manoranjan2050/openvyapar-erp.git
cd openvyapar-erp
```

> **No internet?** Download the ZIP from GitHub and unzip it.

---

## Step 2 — Set Up the Database

Open MySQL/MariaDB and run:

```sql
CREATE DATABASE openvyapar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'openvyapar'@'localhost' IDENTIFIED BY 'secret123';
GRANT ALL PRIVILEGES ON openvyapar.* TO 'openvyapar'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 3 — Set Up the Backend (Laravel)

```bash
cd backend
```

### 3a — Install PHP packages
```bash
composer install
```

### 3b — Create the config file
```bash
cp .env.example .env
```

### 3c — Open `.env` and edit these lines:

```env
APP_NAME="OpenVyapar ERP"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=openvyapar
DB_USERNAME=openvyapar
DB_PASSWORD=secret123
```

### 3d — Generate the app key and migrate
```bash
php artisan key:generate
php artisan migrate --seed
```

> This creates all tables and a **demo admin account**:
> - Email: `admin@openvyapar.in`
> - Password: `password`

### 3e — Start the backend server
```bash
php artisan serve
```

Backend is running at: **http://localhost:8000**

---

## Step 4 — Set Up the Frontend (Vue 3)

Open a **new terminal window** (keep the backend running).

```bash
cd frontend
```

### 4a — Install packages
```bash
npm install
```

### 4b — Start the frontend
```bash
npm run dev
```

Frontend is running at: **http://localhost:5173**

---

## Step 5 — Open in Browser

Go to: **http://localhost:5173**

Login with:
- **Email:** `admin@openvyapar.in`
- **Password:** `password`

---

## Step 6 — Configure Your Company

After login:

1. Click **Settings** in the sidebar
2. Fill in your company details:
   - Company Name
   - GSTIN (15-digit GST number)
   - Address, Phone, Email
3. Click **Save Settings**

**That's it — OpenVyapar ERP is ready to use!**

---

## Everyday Use (Start/Stop)

Every time you want to use OpenVyapar, open **two terminals** and run:

**Terminal 1 — Backend:**
```bash
cd openvyapar-erp/backend
php artisan serve
```

**Terminal 2 — Frontend:**
```bash
cd openvyapar-erp/frontend
npm run dev
```

Then open **http://localhost:5173** in your browser.

---

## How to Use — Quick Tutorial

### Create Your First Product
1. Click **Products** → **New Product**
2. Fill: Name, SKU, GST Rate, Selling Price
3. Click **Save**

### Create Your First Sales Invoice
1. Click **Sales Invoices** → **New Invoice**
2. Select Customer (or create one)
3. Add products and quantities — GST is calculated automatically
4. Click **Save Invoice**

### Record a Payment
1. Open any invoice (click on it in the list)
2. Click **Record Payment** (green button)
3. Enter amount, select Cash/UPI/Bank, enter date
4. Click **Confirm Payment**

### Print a GST Invoice
1. Open any invoice
2. Click **Print** button
3. Use your browser's print dialog to print or save as PDF

### Export to Excel
1. Go to **Products** or **Sales Invoices**
2. Click **Export** button
3. An Excel file downloads automatically

### View GST Reports (GSTR-1)
1. Click **GST Reports** in the sidebar
2. Select period (or click Q1/Q2/Q3/Q4 buttons)
3. Click **Generate Report**
4. View B2B, B2C, HSN Summary, and ITC tabs
5. Click **Export Excel** to download for filing

---

## Common Problems & Fixes

### "php: command not found"
- Windows: Add PHP to your PATH. See https://www.php.net/manual/en/install.windows.php
- macOS: Run `brew install php`
- Ubuntu/Debian: Run `sudo apt install php8.2 php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-mysql`

### "Could not connect to database"
- Make sure MySQL is running: `sudo systemctl start mysql` (Linux) or start it from Services (Windows)
- Double-check DB_USERNAME and DB_PASSWORD in `backend/.env`

### "npm run dev" fails
- Delete `node_modules` folder and run `npm install` again
- Make sure Node.js version is 18 or higher

### Browser shows "Cannot GET /"
- Make sure BOTH backend (port 8000) AND frontend (port 5173) are running
- Open http://localhost:5173 (not 8000)

### Port already in use
```bash
# Change backend port:
php artisan serve --port=8001

# Change frontend port — edit frontend/vite.config.ts:
# server: { port: 5174 }
```

---

## Production Deployment

For hosting on a VPS / shared hosting:

### Backend
```bash
cd backend
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Point your web server (Nginx/Apache) document root to `backend/public`.

### Frontend (build for production)
```bash
cd frontend
npm run build
```

This creates a `dist/` folder — upload to your web server or CDN.

Update `VITE_API_BASE_URL` in `frontend/.env.production` to point to your live backend URL.

---

## Reset / Fresh Install

To wipe all data and start fresh:
```bash
cd backend
php artisan migrate:fresh --seed
```

> **Warning:** This deletes all your invoices, products, and customers.

---

## License

OpenVyapar ERP is open-source software licensed under the **AGPL v3**.
Free to use, modify, and self-host. If you distribute it, you must share your changes.

---

## Support

- GitHub Issues: https://github.com/manoranjan2050/openvyapar-erp/issues
- Email: electroiot.in@gmail.com

---

*Made with ❤️ in India for Indian businesses*
