# 81 Buell Utilities

## Overview
**81 Buell Utilities** is a web app for roommates to manage and track shared utility bills. It provides a clean dashboard to view and update bills, automate reminders, and manage payment statuses — all built with PHP, SQL, and Python.

## Features
- 💡 Bill Dashboard: View all bills with dates, cost per person, status, and download/view links.
- 🔐 CAS Authentication: Secure login with role-based access.
- 🛠 Admin Portal: Easily add new bills, update payments, and send email reminders.
- 📅 Calendar Integration: Export bills to `.ics` calendar files.
- 📧 Automated Emails: Daily reminder emails for unpaid bills via `cron`.

---

## Stack
- PHP + MySQL (PDO)
- HTML/CSS + JS
- Python 3.10
- Cron (for automation)

---

## Demo
![Demo](utility.png)

---

## Setup

### 1. Clone the Repository
```bash
git clone https://github.com/aaronperkel/utility-manager.git
cd utility-manager
```

### 2. Install PHP Depenencies
Make sure you have Composer installed. Install the PHP dependencies by running:
```bash
composer install
```

### 3. Set Up Environment Variables
Create a `.env` file in the root with:
```dotenv
DBNAME=your_database_name
DBUSER=your_database_username
DBPASS=your_database_password
EMAIL_PASS="your_email_password"
```

### 4. Initialize the Database
Create a MySQL database, then use the schema:sql.php`. Here’s an example:
```sql
CREATE TABLE tblUtilities (
  pmkBillID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fldDate VARCHAR(50) DEFAULT NULL,
  fldItem VARCHAR(50) DEFAULT NULL,
  fldTotal VARCHAR(50) DEFAULT NULL,
  fldCost VARCHAR(50) DEFAULT NULL,
  fldDue VARCHAR(50) DEFAULT NULL,
  fldStatus VARCHAR(50) DEFAULT NULL,
  fldView VARCHAR(150) DEFAULT NULL,
  fldOwe VARCHAR(150) DEFAULT NULL
);
```

### 5. Python Dependencies
Install Python Packages
```bash
pip install sqlalchemy python-dotenv
```

### 6. Set Up Cron for Automation
Set up your `crontab` to run the Python script daily:
```cron
0 10 * * * /opt/mise/installs/python/3.10.15/bin/python /path/to/db.py
```

## Usage
- Regular users can view and download their bills.
- Admins can post new bills, edit status, and send reminders.
- Automatic email reminders are triggered daily for unpaid bills due within 7 days.


## License
[MIT License](LICENSE) © 2024 [Aaron Perkel](http://aaronperkel.com)