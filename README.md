#  DB-Backup-Utility
![Laravel](https://img.shields.io/badge/Laravel-10.x-red)
![License](https://img.shields.io/github/license/gofran04/db-backup-utility)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen)

## 📖 Table of Contents
- [Project Overview](#-project-overview)
- [Features](#features)
- [Installation](#️-installation)
- [Configuration](#-configuration)
- [Usage](#️-usage)
- [Testing](#-testing)
- [Troubleshooting](#-troubleshooting)
- [Contribution / Future Plans](#-contribution--future-plans)

## 🚀 Project Overview
A Laravel-based utility to securely back up and restore MySQL/PostgreSQL databases via CLI or API, with support for compression, scheduling, and storage management.

## Features

- ✅ Backup & restore MySQL/PostgreSQL databases
- ✅ API and CLI interfaces
- ✅ Gzip compression
- ✅ Backup retention policy (by count or age)
- ✅ Backup scheduling (via cron)
- ✅ Backup status monitoring
- ✅ Local storage reporting


## 🛠️ Installation
### Requirements
- PHP 8.2+
- Laravel 10+
- MySQL / PostgreSQL
- Composer


### Setup
```bash
# Clone the repo
git clone https://github.com/gofran04/DB-Backup-Utility/tree/dev.git

cd db-backup-utility

# Install dependencies
composer install

# Create .env and configure
cp .env.example .env
php artisan key:generate

# Set your database and backup storage path in .env
# For example:
# DB_CONNECTION=mysql
# BACKUP_STORAGE_PATH=/home/user/.db-backup
```

## 🔧 Configuration

Your DB Backup Utility requires minimal setup. Here's how to configure everything:

---

### 1. Set Database Connections in `.env`

For each connection you want to use, define the usual Laravel `.env` variables:

<details>
<summary>MySQL Example</summary>

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
</details>

<details>
<summary>PostgreSQL Example</summary>
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=my_database
DB_USERNAME=my_user
DB_PASSWORD=my_password

</details>


### 2.🔹 Installation Command (`program:install`)

Before running any backup or restore commands, you must initialize the backup utility environment:

```bash
php artisan program:install
```
This command will:
- 📂 Create the ~/.db-backup directory (if it doesn’t exist)
- 📝 Generate a config.json file with example database profiles
- 🔐 Set secure file permissions
- 📦 Prepare the default backup storage directory

### 3. Use `~/.db-backup/config.json` for Backup Profiles

You can define reusable backup profiles in a JSON file located at:

~/.db-backup/config.json


**Example:**

```json
{
  "production_mysql": {
    "type": "mysql",
    "host": "127.0.0.1",
    "port": 3306,
    "database": "prod_db",
    "username": "prod_user",
    "password": "secret"
  },
  "staging_pgsql": {
    "type": "pgsql",
    "host": "127.0.0.1",
    "port": 5432,
    "database": "staging_db",
    "username": "postgres",
    "password": "secret"
  }
}
```

### 4. Set Custom Storage Path in config/backup.php
By default, backups are stored in:
storage/app/backups

To change the backup storage location, update your config/backup.php:
```
return [
    'storage_path' => storage_path('custom_backups'),
];
```
---

## 🖥️ Usage
### CLI
1. **Backup a Database:  php artisan db:backup {id?} {--profile=}** 
- Example (by database ID):
```php artisan db:backup 4``` 
- Example (by profile name):
```php artisan db:backup --profile= prof1```
Use this command to create a database backup using either a database ID or a predefined profile from your config.json file.

2. **Restore a Backup: php artisan backup:restore {file} {--id=} {--profile=}**
- Example (by database ID):
```php artisan backup:restore backups/file.sql --id=4```
- Example (by profile name):
```php artisan backup:restore backups/file.sql --profile= prof1```
Restore a database from a backup file using either the database ID or a profile.

3. **Cleanup Old Backups: php artisan backup:cleanup {--keep-last=} {--older-than-days=}**
- Example (keep last 4 backups):
```php artisan backup:cleanup  --keep-last=4``` 
- Example (delete backups older than 8 days):
 ```php artisan backup:cleanup  --older-than-days=8```
Manage disk space efficiently by removing outdated backups.


4. **Run Scheduled Backups: php artisan backup:schedule**
This command checks all backup schedules and triggers any that are due.

5. **Check Backup Status: php artisan backup:status**
Displays the status of all scheduled backups, including last run time and next scheduled run.

6. **php artisan backup-storage-report**
Displays local storage usage, including: storage usage,including: Total number of backup files, Total size (formatted in MB/GB) and Largest and smallest backup files.

### API (📬 Postman Collection)
You can test all API endpoints easily using the official Postman collection:

🔗 **[Download Collection](https://github.com/gofran04/DB-Backup-Utility/blob/dev/documentation/postman/DB_Backup_Utility_postman_collection.json)**

> Includes:
> - All available API endpoints  
> - Pre-filled examples  
> - Ready-to-send request bodies  

#### 🛠️ How to Use:
1. Open [Postman](https://www.postman.com/)
2. Click **Import** → **Upload Files**
3. Select the downloaded JSON file
4. Start testing the API!

### ✅ Testing

This project includes the following test coverage:

#### 🔹 Feature Tests
- All main Artisan commands:
  - `db:backup`
  - `backup:restore`
  - `backup:cleanup`
  - `backup:schedule`
  - `backup:status`
  - `backup-storage-report`
- Tests ensure correct execution, output, and error handling.

#### 🔹 API Tests
- All API endpoints tested:
  - Backup creation via DB ID or profile
  - Restoration from file
  - Cleanup by count or date
  - Scheduling and status

#### 🔹 How to Run Tests

Make sure you’ve configured a `.env.testing` file and test database.

Then run:
``` php artisan test --process-isolation ```

### 🧯 Troubleshooting

#### ❌ Error: "mysqldump: command not found"
Ensure `mysqldump` is installed and available in the system path.
```sudo apt install mysql-client```

#### ❌ Error: "Permission denied when accessing config.json"
Fix file permissions:
``` chmod 600 ~/.db-backup/config.json```

#### ❌ Backup not showing in API or CLI
Make sure:
- The database connection exists and is valid.
- The storage/backups directory is writable.

#### ❌ Cron jobs not running
Check:
- You ran php artisan schedule:run every minute via crontab.

- Use php artisan backup:schedule manually to debug.

#### ✅ Recommended Fixes
Run php artisan config:clear and php artisan cache:clear after env/config changes.

Use --profile= carefully, and validate the profile format in ~/.db-backup/config.json.

Still stuck? Create a GitHub issue with logs and steps to reproduce.

### 📚 Contribution / Future Plans
- Support cloud storage.
- Improve web UI.