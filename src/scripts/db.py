from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
from email.mime.text import MIMEText
import datetime
from dotenv import load_dotenv
import smtplib
import schedule
import time 
import os

load_dotenv('/users/a/p/aperkel/.env')

dates = []
people = []
date_format = "%Y-%m-%d"

def get_email_body(num, date):
    if num == 0:
        body = """
        <p style="font: 14pt serif;">Hello,</p>
        <p style="font: 14pt serif;">This is a reminder that you have
        an upcoming utility bill due on """ + date + """.</p>
        """
    else:
        body = """
        <p style="font: 14pt serif;">Hello,</p>
        <p style="font: 14pt serif;">You have a new bill ready to view.</p>
        """

    body += """
    <p style="font: 14pt serif;">
        Please login to
        <a href="https://utilities.aperkel.w3.uvm.edu">81 Buell Utilities</a>
        for more info.
    </p>
    <p style="font: 14pt serif;">
    <span style="color: green;">
    81 Buell Utilities</span><br>
    P: (478)262-8935 | E: me@aaronperkel.com</p>"""

    reminder = """"""

    body += reminder

    return body

def check_bills():
    global dates
    global people

    print('============== Checking Bills ==============')
    now = datetime.datetime.now()
    print(now.strftime("%m/%d/%Y %H:%M:%S"))

    ssl_args = {'ssl': {'ca': '../../webdb-cacert.pem'}}
    db_engine = create_engine(
            'mysql://' + os.getenv('DBUSER') + ':' + os.getenv('DBPASS') + '@webdb.uvm.edu/' + os.getenv('DBNAME'),
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

def send_email(date):
    global date_format
    
    sender_email = 'aaron.perkel@icloud.com'
    sender = 'me@aaronperkel.com'
    sender_password = os.getenv('EMAIL_PASS')
    recipients = ['aperkel@uvm.edu', 'oacook@uvm.edu', 'bquacken@uvm.edu']

    new_date = datetime.datetime.strptime(date, date_format)
    today = time.strftime(date_format, time.localtime())
    today = datetime.datetime.strptime(today, date_format)
    delta = new_date - today
    days_left = delta.days

    if days_left <= 3:
        subject = 'URGENT: Utility Bill Reminder'
    else:
        subject = 'Utility Bill Reminder'

    body = get_email_body(0, date)

    msg = MIMEText(body, 'html')
    msg['Subject'] = subject
    msg['From'] = '81 Buell Utilities <' + sender + '>'
    msg['To'] = ', '.join(recipients)

    with smtplib.SMTP('smtp.gmail.com', 587) as server:
        server.ehlo()
        server.starttls()
        server.ehlo()
        server.login(sender_email, sender_password)
        server.sendmail(sender_email, recipients, msg.as_string())

    confirm(msg['To'], msg['Subject'], body)

def send_email(date, people):
    recipients = []
    global date_format

    if ('Aaron' in people):
        recipients.append('aperkel@uvm.edu')
    if ('Owen' in people):
        recipients.append('oacook@uvm.edu')
    if ('Ben' in people):
        recipients.append('bquacken@uvm.edu')

    sender_email = 'aaron.perkel@icloud.com'
    sender = 'me@aaronperkel.com'
    sender_password = os.getenv('EMAIL_PASS')
    
    new_date = datetime.datetime.strptime(date, date_format)
    today = time.strftime(date_format, time.localtime())
    today = datetime.datetime.strptime(today, date_format)
    delta = new_date - today
    days_left = delta.days

    if days_left <= 3:
        subject = 'URGENT: Utility Bill Reminder'
    else:
        subject = 'Utility Bill Reminder'

    body = get_email_body(0, date)

    msg = MIMEText(body, 'html')
    msg['Subject'] = subject
    msg['From'] = '81 Buell Utilities <' + sender + '>'
    msg['To'] = ', '.join(recipients)

    with smtplib.SMTP('smtp.mail.me.com', 587) as server:
        server.ehlo()
        server.starttls()
        server.ehlo()
        server.login(sender_email, sender_password)
        server.sendmail(sender_email, recipients, msg.as_string())

    confirm(msg['To'], msg['Subject'], body)

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

def new_bill():
    sender_email = 'aaron.perkel@icloud.com'
    sender = 'me@aaronperkel.com'
    sender_password = os.getenv('EMAIL_PASS')
    recipients = ['aperkel@uvm.edu', 'oacook@uvm.edu', 'bquacken@uvm.edu']
    subject = 'New Bill Posted'

    body = get_email_body(1, sender_email)

    msg = MIMEText(body, 'html')
    msg['Subject'] = subject
    msg['From'] = '81 Buell Utilities <' + sender + '>'
    msg['To'] = ', '.join(recipients)

    with smtplib.SMTP('smtp.mail.me.com', 587) as server:
        server.ehlo()
        server.starttls()
        server.ehlo()
        server.login(sender_email, sender_password)
        server.sendmail(sender_email, recipients, msg.as_string())

    confirm(msg['To'], msg['Subject'], body)

def run_schedule():
    global dates
    global people

    schedule.every().day.at("10:00").do(check_bills)

    while True:
        dates = []
        people = []
        schedule.run_pending()        

        if dates:
            print('-------------- Email Scheduling --------------')    
            print('Unpaid Bills:')
            for i, date in enumerate(dates):
                print(f'- {date}: {people[i]}')
                new_date = datetime.datetime.strptime(date, date_format)
                today = time.strftime("%Y-%m-%d", time.localtime())
                today = datetime.datetime.strptime(today, date_format)
                delta = new_date - today
                days_left = delta.days
                print(f'  - Days until bill due: {days_left}')

                if days_left <= 7:
                    print('  - Sending Email')
                    send_email(date, people[i])
                    print('  - Email Sent')
                    time.sleep(1)
                else:
                    print('  - Not Sending Email')
            print('=================== Done ===================\n')
        
if __name__ == '__main__':
    print('Press Enter to call check_bills()')
    while True:
        dates = []
        people = []
        input()
        check_bills()        

        if dates:
            print('-------------- Email Scheduling --------------')    
            print('Unpaid Bills:')
            for i, date in enumerate(dates):
                print(f'- {date}: {people[i]}')
                new_date = datetime.datetime.strptime(date, date_format)
                today = time.strftime("%Y-%m-%d", time.localtime())
                today = datetime.datetime.strptime(today, date_format)
                delta = new_date - today
                days_left = delta.days
                print(f'  - Days until bill due: {days_left}')

                if days_left <= 7:
                    print('  - Sending Email')
                    send_email(date, people[i])
                    print('  - Email Sent')
                    time.sleep(1)
                else:
                    print('  - Not Sending Email')
            print('=================== Done ===================\n')
