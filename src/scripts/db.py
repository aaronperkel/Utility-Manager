# src/scripts/db.py

from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
from email.mime.text import MIMEText
import datetime
from dotenv import load_dotenv
import smtplib
import time
import os

# load environment
load_dotenv('/users/a/p/aperkel/.env')

DATE_FMT = "%Y-%m-%d"

# Create engine once
ssl_args = {'ssl': {'ca': '../../webdb-cacert.pem'}}
DB_URL = (
    f"mysql://{os.getenv('DBUSER')}:{os.getenv('DBPASS')}"
    f"@webdb.uvm.edu/{os.getenv('DBNAMEUTIL')}"
)
engine = create_engine(DB_URL, connect_args=ssl_args)
Session = sessionmaker(bind=engine)

# map names to emails
EMAIL_MAP = {
    'Aaron': 'aperkel@uvm.edu',
    'Owen':  'oacook@uvm.edu',
    'Ben':   'bquacken@uvm.edu',
}

def get_email_body(item, total, cost, due_date, user_balance, is_new):
    """
    If is_new=False: reminder template including item, total, cost, due date,
    and this user's outstanding balance. Otherwise a simple new-bill notice.
    """
    if not is_new:
        return f"""
        <p style="font:14pt serif;">Hello,</p>
        <p style="font:14pt serif;">
          This is a reminder that your <strong>{item}</strong> bill—total <strong>{total}</strong>,
          due <strong>{due_date}</strong>—is coming up.
        </p>
        <ul style="font:14pt serif; list-style: disc inside;">
          <li>Total cost: {total}</li>
          <li>Cost per person: {cost}</li>
          <li>Your current outstanding balance: {user_balance}</li>
        </ul>
        <p style="font:14pt serif;">
          Please log in to
          <a href="https://utilities.aperkel.w3.uvm.edu">81 Buell Utilities</a>
          for details.
        </p>
        <p style="font:14pt serif;">
          <span style="color:green;">81 Buell Utilities</span><br>
          P: (478) 262‑8935 | E: me@aaronperkel.com
        </p>
        """
    else:
        return """
        <p style="font:14pt serif;">Hello,</p>
        <p style="font:14pt serif;">A new utility bill has been posted.</p>
        <p style="font:14pt serif;">
          Please log in to
          <a href="https://utilities.aperkel.w3.uvm.edu">81 Buell Utilities</a>
          to view it.
        </p>
        <p style="font:14pt serif;">
          <span style="color:green;">81 Buell Utilities</span><br>
          P: (478) 262‑8935 | E: me@aaronperkel.com
        </p>
        """

def check_bills():
    """
    Returns a list of dicts for all unpaid bills, each with:
    due, owe_list, item, total, cost.
    """
    sql = """
      SELECT fldDue   AS due,
             fldOwe   AS owe,
             fldItem  AS item,
             fldTotal AS total,
             fldCost  AS cost
      FROM tblUtilities
      WHERE fldStatus = 'Unpaid'
    """
    with engine.connect() as conn:
        rows = conn.execute(text(sql)).mappings().all()

    bills = []
    for r in rows:
        bills.append({
            'due':      r['due'],
            'owe_list': [x.strip() for x in r['owe'].split(',')],
            'item':     r['item'],
            'total':    r['total'],
            'cost':     r['cost'],
        })
    return bills

def send_email(bill, people):
    """
    Sends a reminder to each person in `people` about `bill`.
    """
    due    = bill['due']
    item   = bill['item']
    total  = bill['total']
    cost   = bill['cost']

    # days until due
    due_dt    = datetime.datetime.strptime(due, DATE_FMT)
    days_left = (due_dt - datetime.datetime.today()).days
    subject   = 'URGENT: Utility Bill Reminder' if days_left <= 3 else 'Utility Bill Reminder'

    # for each person, compute outstanding balance and email them
    for who in people:
        email = EMAIL_MAP.get(who)
        if not email:
            continue

        # compute this user's total outstanding
        bal_sql = """
          SELECT SUM(fldCost)
          FROM tblUtilities
          WHERE fldStatus <> 'Paid'
            AND FIND_IN_SET(:who, fldOwe)
        """
        with engine.connect() as conn:
            bal = conn.execute(text(bal_sql), {'who': who}).scalar() or 0.0
        user_balance = f"${bal:.2f}"

        body = get_email_body(item, total, cost, due, user_balance, is_new=False)

        msg = MIMEText(body, 'html')
        msg['Subject'] = subject
        msg['From']    = '81 Buell Utilities <me@aaronperkel.com>'
        msg['To']      = email

        with smtplib.SMTP('smtp.gmail.com', 587) as s:
            s.ehlo()
            s.starttls()
            s.login('aaron.perkel@icloud.com', os.getenv('EMAIL_PASS'))
            s.send_message(msg)

        time.sleep(1)

def new_bill_notification(bill):
    """
    Sends a new-bill notice to everyone on that bill.
    """
    recipients = [EMAIL_MAP[p] for p in bill['owe_list'] if p in EMAIL_MAP]
    body = get_email_body(None, None, None, None, None, is_new=True)

    msg = MIMEText(body, 'html')
    msg['Subject'] = 'New Bill Posted'
    msg['From']    = '81 Buell Utilities <me@aaronperkel.com>'
    msg['To']      = ', '.join(recipients)

    with smtplib.SMTP('smtp.gmail.com', 587) as s:
        s.ehlo()
        s.starttls()
        s.login('aaron.perkel@icloud.com', os.getenv('EMAIL_PASS'))
        s.send_message(msg)

def run_schedule():
    bills = check_bills()
    for bill in bills:
        send_email(bill, bill['owe_list'])

if __name__ == '__main__':
    run_schedule()