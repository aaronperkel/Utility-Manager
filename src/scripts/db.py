from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
from email.mime.text import MIMEText
import datetime
from dotenv import load_dotenv
import smtplib
import time
import os
import json # For parsing JSON string from env
import socket # For catching socket errors during SMTP operations
import datetime # Imported for type hinting and date operations
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
from email.mime.text import MIMEText
from dotenv import load_dotenv

# db.py
# This script checks for unpaid utility bills and sends email reminders.
# It connects to a database, queries for bills due soon, and uses SMTP to send emails.

# Determine the base directory of the script for robust path construction.
script_dir = os.path.dirname(os.path.abspath(__file__))

# Attempt to load .env file from common locations relative to the script.
# This allows flexibility in where the .env file is placed (e.g., project root).
dotenv_paths = [
    os.path.join(script_dir, '..', '..', '.env'), # Root from src/scripts/
    os.path.join(script_dir, '.env'),             # Current directory
]
# Get custom path from environment variable if set
custom_dotenv_path = os.getenv('PYTHON_DOTENV_PATH')
if custom_dotenv_path:
    dotenv_paths.insert(0, custom_dotenv_path) # Prioritize custom path

loaded_path = None
for path in dotenv_paths:
    if os.path.exists(path):
        load_dotenv(dotenv_path=path)
        loaded_path = path
        break

if not loaded_path:
    print("Warning: .env file not found in any of the specified paths. Relying on pre-set environment variables.")

# --- Configuration Loading & Validation ---
# Load environment variables from .env file.
APP_BASE_URL = os.getenv('APP_BASE_URL', 'https://utilities.example.com').rstrip('/')
DB_HOST = os.getenv('DB_HOST', 'webdb.uvm.edu')
DB_NAME = os.getenv('DB_NAME')
DB_USER = os.getenv('DB_USER')
DB_PASS = os.getenv('DB_PASS')
EMAIL_PASS = os.getenv('EMAIL_PASS') # For the sender email

APP_EMAIL_FROM_NAME = os.getenv('APP_EMAIL_FROM_NAME', 'Utility Service') # Default if not set
PYTHON_SENDER_EMAIL = os.getenv('PYTHON_SENDER_EMAIL')
PYTHON_CONFIRMATION_EMAIL_TO = os.getenv('PYTHON_CONFIRMATION_EMAIL_TO')

# Default DB_SSL_CA_PATH construction
default_ca_path = os.path.join(script_dir, '..', '..', 'webdb-cacert.pem') # Default path relative to project root
DB_SSL_CA_PATH = os.getenv('DB_SSL_CA_PATH', default_ca_path)
# Read DB_USE_SSL, defaulting to 'false' if not set, then convert to boolean.
raw_db_use_ssl = os.getenv('DB_USE_SSL', 'false')
DB_USE_SSL = raw_db_use_ssl.lower() in ['true', '1']


# Load and parse APP_USER_EMAILS
email_map_json = os.getenv('APP_USER_EMAILS', '{}')
try:
    EMAIL_MAP = json.loads(email_map_json)
except json.JSONDecodeError:
    print(f"Warning: Could not parse APP_USER_EMAILS JSON: {email_map_json}. Using empty email map.")
    EMAIL_MAP = {}

# Validate that all critical environment variables are loaded.
# This helps in early detection of configuration issues.
critical_vars = {
    "DB_NAME": DB_NAME, "DB_USER": DB_USER, "DB_PASS": DB_PASS, # Database credentials
    "EMAIL_PASS": EMAIL_PASS, # Password for the sender's email account
    "PYTHON_SENDER_EMAIL": PYTHON_SENDER_EMAIL, # Email address used to send reminders
    "PYTHON_CONFIRMATION_EMAIL_TO": PYTHON_CONFIRMATION_EMAIL_TO # Admin email for confirmations
}
missing_vars = [name for name, var in critical_vars.items() if not var]
if missing_vars:
    print(f"Error: Missing critical environment variables: {', '.join(missing_vars)}. Please check your .env file. Exiting.")
    exit(1) # Exit if critical configuration is missing.


# --- DatabaseManager Class ---
class DatabaseManager:
    """
    Manages database connections and queries using SQLAlchemy.
    """
    def __init__(self, db_url: str, ssl_args: dict):
        """
        Initializes the DatabaseManager with database URL and SSL arguments.
        Creates a SQLAlchemy engine and sessionmaker.
        """
        try:
            # Create a SQLAlchemy engine. `connect_args` passes SSL options to the DB driver.
            self.engine = create_engine(db_url, connect_args=ssl_args)
            # Configure a sessionmaker for creating sessions.
            self.SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=self.engine)
            print("Database engine created successfully.")
        except Exception as e:
            print(f"Error creating database engine: {e}")
            raise # Re-raise exception to halt script if DB connection cannot be established.

    def get_unpaid_bills(self) -> list:
        """
        Fetches all unpaid bills (due date and list of people who owe).
        Returns a list of tuples (due_date, fldOwe_string) or an empty list on error.
        """
        try:
            # Use a context manager for session lifecycle.
            with self.SessionLocal() as session:
                # Execute raw SQL query using SQLAlchemy's text() for safety.
                result = session.execute(text("SELECT fldDue, fldOwe FROM tblUtilities WHERE fldStatus = 'Unpaid'"))
                return result.all() # Returns a list of Row objects.
        except Exception as e:
            print(f"Error fetching unpaid bills: {e}")
            return []

    def get_bill_details_for_reminder(self, due_date: datetime.date, person: str) -> tuple | None:
        """
        Fetches bill total and per-person cost for a specific bill and person.
        `due_date` should be a datetime.date object.
        Returns a tuple (total_cost, per_person_cost) or None if not found or on error.
        """
        try:
            with self.SessionLocal() as session:
                # Parameterized query to prevent SQL injection.
                # SQLAlchemy handles conversion of datetime.date to appropriate string for MySQL.
                row = session.execute(
                    text("""
                        SELECT fldTotal, fldCost
                        FROM tblUtilities
                        WHERE fldDue = :due
                        AND FIND_IN_SET(:person, REPLACE(fldOwe, ' ', ''))
                    """),
                    {"due": due_date, "person": person} # Pass parameters for binding.
                ).fetchone() # Expecting one row or None.
                return row if row else None
        except Exception as e:
            print(f"Error fetching bill details for reminder (date: {due_date}, person: {person}): {e}")
            return None


# --- Global Variables & Constants ---
DATE_FORMAT_STR = "%Y-%m-%d" # Standard date format string for display.
SMTP_SERVER = 'smtp.mail.me.com' # SMTP server for sending emails (e.g., iCloud).
SMTP_PORT = 587 # Standard SMTP port for TLS.
db_manager = None # Global instance of DatabaseManager, initialized in main().

# --- Email Functions ---
def get_email_body(due_date_str: str, total: float, cost: float, app_base_url: str, from_name: str, from_contact_email: str) -> str:
    """
    Generates the HTML body for reminder emails.
    Formats the due date and includes bill details and a link to the portal.
    """
    try:
        # Convert YYYY-MM-DD string to a more readable date format for the email body.
        date_obj = datetime.datetime.strptime(due_date_str, DATE_FORMAT_STR)
        readable_due_date = date_obj.strftime("%B %d, %Y") # e.g., "January 15, 2024"
    except ValueError:
        readable_due_date = due_date_str # Fallback to original string if parsing fails.

    portal_link = f"{app_base_url}/index.php" # Construct link to the main portal page.

    # HTML structure for the email body.
    return f"""
<p style="font: 14pt serif;">Hello,</p>
<p style="font: 14pt serif;">This is a reminder that your utility bill is due on {readable_due_date}.</p>
<ul>
    <li style="font: 14pt serif;">Bill total: ${total:.2f}</li>
    <li style="font: 14pt serif;">Cost per person: ${cost:.2f}</li>
</ul>
<p style="font: 14pt serif;">
    Please login to
    <a href="{portal_link}">{from_name} Portal</a>
    for more info.
</p>
<p style="font: 14pt serif;">
    <span style="color: green;">{from_name}</span><br>
    Contact: {from_contact_email}
</p>
"""

def send_email(bill_date_obj: datetime.date, person_name: str, bill_total: float, cost_per_person: float) -> bool:
    """
    Sends a reminder email for a specific bill to a specific person.
    Returns True if email sent successfully, False otherwise.
    """
    global DATE_FORMAT_STR, APP_BASE_URL, EMAIL_MAP, APP_EMAIL_FROM_NAME, PYTHON_SENDER_EMAIL, EMAIL_PASS
    
    recipient_email = EMAIL_MAP.get(person_name)
    if not recipient_email:
        print(f"[WARN] No email address found for {person_name} in EMAIL_MAP. Skipping reminder email.")
        return False

    # Determine email subject based on urgency.
    days_left = (bill_date_obj - datetime.date.today()).days
    subject = 'URGENT: Utility Bill Reminder' if days_left <= 3 else 'Utility Bill Reminder'
    
    # Format bill date to string for use in email body.
    bill_date_str_formatted = bill_date_obj.strftime(DATE_FORMAT_STR)

    # Generate email body content.
    body = get_email_body(
        due_date_str=bill_date_str_formatted,
        total=bill_total,
        cost=cost_per_person,
        app_base_url=APP_BASE_URL,
        from_name=APP_EMAIL_FROM_NAME,
        from_contact_email=PYTHON_SENDER_EMAIL
    )
    
    # Create MIMEText object for HTML email.
    msg = MIMEText(body, 'html')
    msg['Subject'] = subject
    msg['From']    = f"{APP_EMAIL_FROM_NAME} <{PYTHON_SENDER_EMAIL}>" # Use configured sender name and email.
    msg['To']      = recipient_email

    # Attempt to send the email using SMTP.
    try:
        with smtplib.SMTP(SMTP_SERVER, SMTP_PORT) as server: # Context manager for SMTP connection.
            server.ehlo() # Greet server.
            server.starttls() # Upgrade to TLS encryption.
            server.ehlo() # Re-greet after TLS.
            server.login(PYTHON_SENDER_EMAIL, EMAIL_PASS) # Login to SMTP server.
            server.sendmail(PYTHON_SENDER_EMAIL, [recipient_email], msg.as_string()) # Send the email.
        print(f"Reminder email successfully sent to {person_name} ({recipient_email}) for bill due on {bill_date_str_formatted}.")
        
        # If main email sent, send a confirmation to admin (best-effort).
        send_confirmation_email(
            original_recipient=recipient_email,
            original_subject=subject,
            original_email_body=body
        )
        return True # Indicate success.
    except (smtplib.SMTPException, socket.error) as e: # Catch specific SMTP and socket errors.
        print(f"[ERROR] SMTP error while sending reminder to {person_name} ({recipient_email}): {e}")
        return False
    except Exception as e: # Catch any other unexpected errors.
        print(f"[ERROR] Unexpected error sending reminder to {person_name} ({recipient_email}): {e}")
        return False


def send_confirmation_email(original_recipient: str, original_subject: str, original_email_body: str):
    """Sends a confirmation email to the admin about the reminder that was sent."""
    global APP_EMAIL_FROM_NAME, PYTHON_SENDER_EMAIL, PYTHON_CONFIRMATION_EMAIL_TO, EMAIL_PASS
    confirmation_subject = 'Notification Sent Confirmation (Utility Bills Script)'
    # HTML body for the confirmation email.
    confirmation_body = f"""<p style="font: 12pt monospace;">A reminder email was sent via the Utility Bills Script.</p>
<hr>
<p style="font: 12pt monospace;"><b>Original Recipient:</b> {original_recipient}</p>
<p style="font: 12pt monospace;"><b>Original Subject:</b> {original_subject}</p>
<hr>
<p style="font: 12pt monospace;">--- Original Email Body ---</p>
{original_email_body}
"""

    msg = MIMEText(confirmation_body, 'html')
    msg['Subject'] = confirmation_subject
    msg['From'] = f"{APP_EMAIL_FROM_NAME} Script Notifier <{PYTHON_SENDER_EMAIL}>" # Use configured sender.
    msg['To'] = PYTHON_CONFIRMATION_EMAIL_TO # Send to configured admin confirmation email.

    try:
        with smtplib.SMTP(SMTP_SERVER, SMTP_PORT) as server: # Use defined SMTP constants.
            server.ehlo()
            server.starttls()
            server.ehlo()
            server.login(PYTHON_SENDER_EMAIL, EMAIL_PASS) # Use configured credentials.
            server.sendmail(PYTHON_SENDER_EMAIL, [PYTHON_CONFIRMATION_EMAIL_TO], msg.as_string())
        print(f"Confirmation email successfully sent to {PYTHON_CONFIRMATION_EMAIL_TO}.")
    except (smtplib.SMTPException, socket.error) as e: # Catch specific SMTP and socket errors.
        print(f"[ERROR] SMTP error while sending confirmation email to {PYTHON_CONFIRMATION_EMAIL_TO}: {e}")
    except Exception as e: # Catch any other unexpected errors.
        print(f"[ERROR] Unexpected error sending confirmation to {PYTHON_CONFIRMATION_EMAIL_TO}: {e}")


# --- Main Execution Block ---
if __name__ == '__main__':
    print('============== Initializing Script ==============')

    # Construct the database URL for SQLAlchemy.
    # It's important that DB_USER, DB_PASS, DB_HOST, DB_NAME are loaded from .env and validated prior to this.
    db_url = f"mysql+mysqlconnector://{DB_USER}:{DB_PASS}@{DB_HOST}/{DB_NAME}"

    # Prepare SSL arguments for the database connection based on DB_USE_SSL.
    ssl_args_dict = {}
    if DB_USE_SSL:
        print("[INFO] DB_USE_SSL is true. Attempting SSL connection for database.")
        if DB_SSL_CA_PATH:
            # Resolve potential relative path for DB_SSL_CA_PATH (e.g. if it's like "webdb-cacert.pem")
            # Assumes if not absolute, it's relative to the project root (parent of script_dir's parent)
            ca_path_to_check = DB_SSL_CA_PATH
            if not os.path.isabs(ca_path_to_check):
                # script_dir is src/scripts, so ../.. is project root
                project_root = os.path.abspath(os.path.join(script_dir, '..', '..'))
                ca_path_to_check = os.path.join(project_root, DB_SSL_CA_PATH)

            if os.path.exists(ca_path_to_check) and os.access(ca_path_to_check, os.R_OK):
                ssl_args_dict['ssl_ca'] = ca_path_to_check
                print(f"[INFO] Using SSL CA certificate for database: {ca_path_to_check}")
            else:
                print(f"[WARN] DB_USE_SSL is true, but DB_SSL_CA_PATH ('{DB_SSL_CA_PATH}', resolved to '{ca_path_to_check}') is invalid or not readable. Attempting connection without client-side SSL CA verification.")
                # Proceeding with empty ssl_args_dict for SSL, relies on server/driver defaults or system CAs.
        else:
            print("[WARN] DB_USE_SSL is true, but DB_SSL_CA_PATH is not set. SSL connection will rely on server configuration and system CAs.")
            # Proceeding with empty ssl_args_dict for SSL.
    else:
        print("[INFO] DB_USE_SSL is false. Attempting connection without SSL for database.")
        # ssl_args_dict remains empty, so SQLAlchemy won't attempt to use SSL explicitly.

    # Initialize the DatabaseManager.
    try:
        db_manager = DatabaseManager(db_url, ssl_args_dict)
    except Exception as e:
        # Errors during engine creation are critical and usually printed by DatabaseManager's __init__.
        print(f"Critical: Failed to initialize DatabaseManager. Exiting script.")
        exit(1) # Terminate script if DatabaseManager cannot be initialized.

    print('============== Checking Bills ==============')
    script_start_time = datetime.datetime.now() # Record script start time for duration calculation.
    print(f"Script started at: {script_start_time.strftime('%Y-%m-%d %H:%M:%S')}")
    
    # Fetch all unpaid bills from the database.
    unpaid_bills_data = db_manager.get_unpaid_bills()

    if unpaid_bills_data: # Proceed if there are any unpaid bills.
        print('-------------- Email Scheduling --------------')
        print(f'Found {len(unpaid_bills_data)} unpaid bill entries.')

        email_sent_count = 0    # Counter for successfully sent emails.
        email_failed_count = 0  # Counter for failed email attempts.

        # Iterate through each unpaid bill entry.
        # bill_due_date_obj is a datetime.date object.
        # owes_str is a comma-separated string of names.
        for bill_due_date_obj, owes_str in unpaid_bills_data:
            # Split the 'owes_str' into a list of names, stripping whitespace and filtering out empty names.
            persons_owing = [p.strip() for p in owes_str.split(',') if p.strip()]

            for person_name_str in persons_owing: # Iterate through each person owing for the current bill.
                print(f'- Processing: Due {bill_due_date_obj.strftime(DATE_FORMAT_STR)}, For: {person_name_str}')

                # Fetch specific bill details (total amount, per-person cost) for this person and bill.
                bill_details_tuple = db_manager.get_bill_details_for_reminder(bill_due_date_obj, person_name_str)

                if not bill_details_tuple: # If no details found (e.g., data inconsistency).
                    print(f"  - [WARN] No specific bill details found for {person_name_str} on {bill_due_date_obj.strftime(DATE_FORMAT_STR)}. Skipping reminder.")
                    continue # Skip to the next person or bill.

                bill_total_amount, cost_per_person_amount = bill_details_tuple

                try:
                    today_date_obj = datetime.date.today() # Get current date.
                    days_until_due = (bill_due_date_obj - today_date_obj).days # Calculate days until due.
                    print(f'  - Days until bill due: {days_until_due}')

                    # Send reminder if the bill is due within 7 days (or is past due).
                    if days_until_due <= 7:
                        print('  - Attempting to send email...')
                        if send_email(bill_due_date_obj, person_name_str, bill_total_amount, cost_per_person_amount):
                            email_sent_count += 1 # Increment success counter.
                        else:
                            email_failed_count += 1 # Increment failure counter.
                        time.sleep(1) # Brief pause to rate-limit email sending.
                    else:
                        print('  - Not sending email (due date is more than 7 days away).')
                except ValueError as ve: # Catch errors related to date processing.
                    print(f"  - [ERROR] Date processing error for {person_name_str}, bill due {bill_due_date_obj.strftime(DATE_FORMAT_STR)}: {ve}")
                    email_failed_count += 1
                except Exception as e: # Catch any other unexpected errors for this specific bill/person.
                    print(f"  - [ERROR] Unexpected error processing for {person_name_str}, bill due {bill_due_date_obj.strftime(DATE_FORMAT_STR)}: {e}")
                    email_failed_count += 1
        
        # Print a summary of email sending activity.
        print('-------------- Summary --------------')
        print(f"Total reminder emails attempted: {email_sent_count + email_failed_count}")
        print(f"Successfully sent: {email_sent_count}")
        print(f"Failed to send: {email_failed_count}")

    elif unpaid_bills_data is None: # This case should ideally not be reached if get_unpaid_bills returns [].
        print("Critical error: Could not retrieve bill information. Email scheduling aborted.")
    else: # unpaid_bills_data is an empty list.
        print("No unpaid bills found that require reminders at this time.")

    script_end_time = datetime.datetime.now() # Record script end time.
    print(f"Script finished at: {script_end_time.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"Total execution time: {script_end_time - script_start_time}") # Print total duration.
    print('=================== Done ===================\n')
