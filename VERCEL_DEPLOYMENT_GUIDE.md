# CU SmartPark - Vercel Deployment Guide

This guide explains how to deploy **CU SmartPark – Vehicle Parking Management System (Chandigarh University)** to [Vercel](https://vercel.com) seamlessly.

---

## 🚀 Quick Deployment Options

### Option A: Deploy via GitHub (Recommended)
1. Initialize git (if not already done) and push your project to a GitHub repository:
   ```bash
   git init
   git add .
   git commit -m "Initial commit for CU SmartPark"
   git branch -M main
   git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git
   git push -u origin main
   ```
2. Log in to your [Vercel Dashboard](https://vercel.com/dashboard).
3. Click **"Add New..."** > **"Project"**.
4. Import your GitHub repository.
5. In **Framework Preset**, select **Other**.
6. Click **Deploy**. Vercel will build the serverless runtime and provide a live URL (e.g., `https://cu-smartpark.vercel.app`).

---

### Option B: Deploy via Vercel CLI
1. Open PowerShell / Terminal in this project directory:
   ```powershell
   cd "c:\Users\KANISHKA GIRI\Desktop\DBMS KAUSTUBH"
   ```
2. Install the Vercel CLI (if not installed):
   ```bash
   npm i -g vercel
   ```
3. Run the deployment command:
   ```bash
   vercel
   ```
   Follow the prompts to link to your Vercel account.
4. For production deployment:
   ```bash
   vercel --prod
   ```

---

## 🗄️ Database Configuration on Vercel

CU SmartPark is built with an **intelligent dual-mode database engine**:

### 1. Instant Zero-Config Mode (Runs automatically!)
If you deploy without specifying external database credentials, CU SmartPark automatically initializes and runs SQLite inside `/tmp/smartpark_db.sqlite` using pre-seeded Chandigarh University data, active tickets, and parking slots. You can test immediately after deployment with no database setup required.

### 2. Production Cloud MySQL (Recommended for Persistent Data)
Because serverless functions have ephemeral temporary storage, connect a free cloud MySQL instance for permanent data retention across all visitors:

**Free Cloud MySQL Providers:**
- [Aiven for MySQL](https://aiven.io/mysql) (Free Tier)
- [Clever Cloud](https://www.clever-cloud.com/) (Free MySQL Addon)
- [Railway](https://railway.app/) (Free MySQL Service)
- [TiDB Serverless](https://tidb.cloud/) (Free Serverless MySQL compatible)

**Configure in Vercel Dashboard:**
Go to **Project Settings** > **Environment Variables** in Vercel and add:

| Variable Name | Example Value | Description |
|---|---|---|
| `DB_HOST` | `gateway01.us-east-1.prod.aws.tidbcloud.com` | Cloud MySQL Host |
| `DB_PORT` | `3306` | Cloud MySQL Port |
| `DB_NAME` | `smartpark_db` | Database Name |
| `DB_USER` | `xxxxxx.root` | Database Username |
| `DB_PASS` | `YourSecretPassword` | Database Password |

*Or provide a single connection string:*
| Variable Name | Example Value |
|---|---|
| `DATABASE_URL` | `mysql://user:pass@host:3306/dbname` |

When configured, CU SmartPark will automatically run the schema migrations, create all tables (`parking_slots`, `parking_records`, `bookings`, `payments`, `vehicles`, `system_settings`, `admins`), and seed the initial campus slots.

---

## 🔐 Default Admin Credentials

- **Admin Login URL:** `https://your-domain.vercel.app/login.php`
- **Username:** `admin`
- **Password:** `admin123`

---

## 🌐 Public Reservation Portal

- **Online Booking:** `https://your-domain.vercel.app/booking.php`
- **Check Booking Status:** `https://your-domain.vercel.app/booking-lookup.php`
