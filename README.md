# 81 Buell Utilities

## Overview
**81 Buell Utilities** is a web application designed for roommates to efficiently manage and track shared utility bills. It features a clean dashboard for viewing and updating bills, automated payment reminders, and comprehensive payment status management. Built with PHP, MySQL, and Python, it aims to simplify household bill coordination.

The application has recently undergone significant refactoring for improved security, maintainability, and a more robust database structure.

## Key Features
- 💡 **Bill Dashboard**: View all utility bills with details such as billing dates, cost per person, payment status, and links to view/download bill documents.
- 🔐 **CAS Authentication**: Secure login leveraging CAS, with role-based access distinguishing administrators from regular users.
- 🛠 **Admin Portal**: Administrators can easily add new bills, update payment statuses for individuals, manually send email reminders, and dispatch custom emails to users.
- 📅 **Calendar Integration**: Export bill due dates to an `.ics` calendar file for easy integration with personal calendars.
- 📧 **Automated Email Reminders**: A Python script, typically run via cron, sends daily email reminders for unpaid bills that are due soon.
-  📄 **Pagination**: Bill lists in both the user dashboard and admin portal are paginated for easier navigation.
- ⚙️ **Testing/Dry-Run Mode**: Admins can enable a dry-run mode via environment settings to test functionalities like adding bills or sending reminders without making actual database changes or sending emails.

---

## Technology Stack
- **Backend**: PHP (8.x recommended), Python 3.10+
- **Database**: MySQL (using PDO for PHP, SQLAlchemy & mysql-connector-python for Python)
- **Frontend**: HTML, CSS, JavaScript (minimal)
- **PHP Dependencies**: Composer (for `phpdotenv`)
- **Python Dependencies**: Listed in `requirements.txt` (`sqlalchemy`, `python-dotenv`, `mysql-connector-python`)
- **Automation**: Cron (or similar task scheduler) for running the Python reminder script.

---

## Setup Instructions

### 1. Clone the Repository
```bash
git clone https://github.com/aaronperkel/utility-manager.git
cd utility-manager
```

### 2. Install PHP Dependencies

Ensure Composer is installed. Then, from the project root, run:

```bash
composer install
```
This will install the phpdotenv package, used for managing environment variables.

### 3. Install Python Dependencies

Ensure you have Python 3.10+ and pip installed. Then, from the project root, run:

```bash
pip install -r requirements.txt
```
This will install SQLAlchemy, python-dotenv, and the MySQL connector. It's recommended to use a Python virtual environment.

### 4. Set Up Environment Variables

Create a .env file in the project root by copying and modifying .env.example. This file stores critical configuration details. A comprehensive list of variables can be found in .env.example, but here are the key ones:

- Database Configuration:
  - DB_HOST: Your database host (e.g., localhost, webdb.uvm.edu).
  - DB_NAME: Your database name (e.g., APERKEL_utilities).
  - DB_USER: Your database username.
  - DB_PASS: Your database password.
  - DB_USE_SSL: Set to true to enable SSL for database connections.
  - DB_SSL_CA_PATH: Absolute path to your CA certificate if DB_USE_SSL=true.
- Application Settings:
  - APP_BASE_URL: Absolute base URL for the application, used in email links (e.g., https://utilities.example.com).
  - APP_ADMIN_USERS: Comma-separated list of admin usernames (e.g., from CAS REMOTE_USER).
  - APP_BILLS_PER_PAGE: Number of bills to show per page.
- User & Email Mapping:
  - APP_UID_TO_NAME_MAPPING: JSON string mapping CAS REMOTE_USER uids to display names (e.g., '{"caslogin":"DisplayName"}').
  - APP_USER_EMAILS: JSON string mapping display names (matching tblPeople.personName) to email addresses (e.g., '{"DisplayName":"user@example.com"}').
- Email Sending Configuration:
  - APP_EMAIL_FROM_ADDRESS & APP_EMAIL_FROM_NAME: For emails sent by PHP.
  - APP_CONFIRMATION_EMAIL_TO: Recipient for PHP admin confirmations.
  - PYTHON_SENDER_EMAIL & EMAIL_PASS: Credentials for SMTP server used by the Python script (e.g., iCloud app-specific password).
  - PYTHON_CONFIRMATION_EMAIL_TO: Recipient for Python script's admin confirmations.
- Testing/Dry-Run Mode:
  - APP_DRY_RUN_ENABLED: Set to true to enable dry-run mode.
  - APP_DRY_RUN_ADMIN_ONLY: If true, dry-run is only active for admins.

Note: Ensure the .env file is secured and not publicly accessible. Refer to .env.example for the complete list and detailed comments for all variables.

### 5. Initialize the Database

Create your MySQL database (e.g., APERKEL_utilities). Then, use the table schemas provided in [src/sql.php](src/sql.php) to set up your tables:

- tblPeople: Stores user information (personID, personName).
- tblUtilities: Stores bill details (e.g., pmkBillID, fldDate, fldItem, fldTotal, fldCost, fldDue, fldStatus, fldView). The old fldOwe column has been removed.
- tblBillOwes: A linking table (billID, personID) that tracks which person owes for which bill, replacing fldOwe.
Refer to [src/sql.php](src/sql.php) for the exact CREATE TABLE statements, example INSERT commands, guidance on migrating from the older schema (if applicable), and example queries for the new structure.

### 6. Set Up Cron for Automation (Automated Reminders)

The Python script [src/scripts/db.py](src/scripts/db.py) sends automated email reminders. Set up a cron job (or equivalent scheduled task) to run this script daily.

## Example cron entry:

```cron
0 10 * * * /usr/bin/python3 /path/to/your/utility-manager/src/scripts/db.py
```
- Adjust the schedule (0 10 * * * means 10:00 AM daily) as needed.
- Important: Replace /usr/bin/python3 with the absolute path to the Python interpreter where you installed the dependencies (e.g., the path within your virtual environment).
- Important: Replace /path/to/your/utility-manager/src/scripts/db.py with the correct absolute path to the db.py script on your server.
- The script relies on the .env file being in the project root (two levels above the scripts directory by default) or on a custom path specified by the PYTHON_DOTENV_PATH environment variable.

## Usage

- Regular Users: Log in (typically via CAS, which sets $_SERVER['REMOTE_USER']) to view their dashboard on index.php. They can see current amounts owed (calculated based on tblBillOwes) and view/download bill documents.
- Administrators (as defined in APP_ADMIN_USERS in .env):
  - Access the Admin Portal (portal.php) to add new bills (which now assigns owings to all users in tblPeople by default via tblBillOwes), upload bill PDFs, and update payment statuses for individuals (which modifies tblBillOwes and tblUtilities.fldStatus).
  - Manually trigger reminder emails for specific bills via send_reminder.php.
  - Send custom emails to registered users via send_custom_email.php.
  - Can enable Dry-Run Mode via .env variables. When active, this mode allows testing of the above actions without making database changes, sending actual emails, or modifying files. Feedback for dry-run actions is provided through on-page messages or console logs (for db.py).
- Automated System: The cron job for db.py automatically sends email reminders for unpaid bills to individuals listed in tblBillOwes, due within a 7-day window.

## Development Notes

- Security: Several security enhancements like CSRF protection (on most forms) and improved input validation have been implemented. However, ongoing vigilance and adherence to security best practices are crucial. send_custom_email.php has a TODO note for CSRF protection.
- Error Handling: The application includes improved error display mechanisms. For production environments, consider implementing more robust server-side logging (e.g., using Monolog for PHP, Python's logging module).
- Database Schema: The database structure has been normalized (introducing tblPeople and tblBillOwes) for better data integrity and flexibility. See [src/sql.php](src/sql.php) for details.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
