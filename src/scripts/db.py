from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
from email.mime.text import MIMEText
import datetime
from dotenv import load_dotenv
import smtplib
import time 
import os

load_dotenv('/users/a/p/aperkel/.env')

dates = []
people = []
date_format = "%Y-%m-%d"

def get_email_body(due_date, total, cost):
    return f"""
<p style="font: 14pt serif;">Hello,</p>
<p style="font: 14pt serif;">This is a reminder that your utility bill is due on {due_date}.</p>
<ul>
    <li style="font: 14pt serif;">Bill total: ${total}</li>
    <li style="font: 14pt serif;">Cost per person: ${cost}</li>
</ul>
<p style="font: 14pt serif;">
    Please login to
    <a href="https://utilities.aperkel.w3.uvm.edu">81 Buell Utilities</a>
    for more info.
</p>
<p style="font: 14pt serif;">
    <span style="color: green;">81 Buell Utilities</span><br>
    P: (478)262-8935 | E: me@aaronperkel.com
</p>
"""

def check_bills():
    global dates
    global people

    print('============== Checking Bills ==============')
    now = datetime.datetime.now()
    print(now.strftime("%m/%d/%Y %H:%M:%S"))

    ssl_args = {'ssl': {'ca': '../../webdb-cacert.pem'}}
    db_engine = create_engine(
            'mysql://' + os.getenv('DBUSER') + ':' + os.getenv('DBPASS') + '@webdb.uvm.edu/' + os.getenv('DBNAMEUTIL'),
            connect_args=ssl_args)
    Session = sessionmaker(bind=db_engine)
    db = Session()

    print('connected')

    with db_engine.connect() as conn:
        result = conn.execute(text("SELECT fldDue, fldOwe FROM tblUtilities WHERE fldStatus = 'Unpaid'"))
    db.close()

    print('got SQL result')

    for row in result.all():
        dates.append(row[0])
        people.append(row[1])
        
    print(f'Dates: {dates}')
    print(f'People: {people}')
    
    return dates, people

def send_email(date, person):
    global date_format
    emailMap = {
        'Aaron': 'aperkel@uvm.edu',
        'Owen':   'oacook@uvm.edu',
        'Ben':    'bquacken@uvm.edu',
    }
    recipients = [ emailMap[person] ] if person in emailMap else []
    
    ssl_args = {'ssl': {'ca': '../../webdb-cacert.pem'}}
    db_engine = create_engine(
            'mysql://' + os.getenv('DBUSER') + ':' + os.getenv('DBPASS') + '@webdb.uvm.edu/' + os.getenv('DBNAMEUTIL'),
            connect_args=ssl_args)
    
    # grab the total & cost for this exact person’s share of this bill
    with db_engine.connect() as conn:
        row = conn.execute(
            text("""
                SELECT fldTotal, fldCost
                FROM tblUtilities
                WHERE fldDue = :due
                AND FIND_IN_SET(:person, REPLACE(fldOwe, ' ', ''))
            """),
            {"due": date, "person": person}
        ).fetchone()

        if not row:
            print(f"[WARN] no entry for {person} on {date}, skipping")
            return

        total, cost = row
        
        new_date = datetime.datetime.strptime(date, date_format)
        days_left = (new_date - datetime.datetime.today()).days
        subject = 'URGENT: Utility Bill Reminder' if days_left <= 3 else 'Utility Bill Reminder'

        body = get_email_body(date, total, cost)
        
        sender_email = 'aaron.perkel@icloud.com'
        sender_password = os.getenv('EMAIL_PASS')

        msg = MIMEText(body, 'html')
        msg['Subject'] = subject
        msg['From']    = '81 Buell Utilities <me@aaronperkel.com>'
        msg['To']      = ', '.join(recipients)

        with smtplib.SMTP('smtp.mail.me.com', 587) as server:
            server.ehlo()
            server.starttls()
            server.login(sender_email, sender_password)
            server.sendmail(sender_email, recipients, msg.as_string())

        confirm(msg['To'], msg['Subject'], body)
        
        return True

def confirm(recip, sub, msg):
    sender_email = 'aaron.perkel@icloud.com'
    sender = 'me@aaronperkel.com'
    sender_password = os.getenv('EMAIL_PASS')
    subject = 'Mail Sent'

    body = '<p style="font: 12pt monospace;">An email was just sent via utilities.aperkel.w3.uvm.edu.</p>'

    body += '<hr>'
    
    body += '<p style="font: 12pt monospace;">To: ' + recip + '<br>'
    body += '<p style="font: 12pt monospace;">Subject: ' + sub + '</p>'

    body += msg

    msg = MIMEText(body, 'html')
    msg['Subject'] = subject
    msg['From'] = '81 Buell Utilities <' + sender + '>'
    msg['To'] = 'aperkel@uvm.edu'

    with smtplib.SMTP('smtp.mail.me.com', 587) as server:
        server.ehlo()
        server.starttls()
        server.ehlo()
        server.login(sender_email, sender_password)
        server.sendmail(sender_email, msg['To'], msg.as_string())
        
if __name__ == '__main__':
    dates, people = check_bills()
    if dates:
        print('-------------- Email Scheduling --------------')    
        print('Unpaid Bills:')
        for date, owes in zip(dates, people):
            for person in [p.strip() for p in owes.split(',')]:
                print(f'- {date}: {person}')
                new_date = datetime.datetime.strptime(date, date_format)
                today = datetime.datetime.strptime(time.strftime("%Y-%m-%d", time.localtime()), date_format)
                delta = new_date - today
                days_left = delta.days
                print(f'  - Days until bill due: {days_left}')

                if days_left <= 7:
                    print('  - Sending Email')
                    if send_email(date, person):
                        print('  - Email Sent')
                    time.sleep(1)
                else:
                    print('  - Not Sending Email')
        print('=================== Done ===================\n')
