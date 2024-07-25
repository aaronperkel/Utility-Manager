from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker
from email.mime.text import MIMEText
import datetime
from dotenv import load_dotenv
import smtplib
import schedule
import time
import os

load_dotenv()

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
    <span style="color: green;">
    81 Buell Utilities</span><br>
    P: (478)262-8935 | E: aaronperkel@gmail.com</p>"""

    reminder = """
    <hr>
    <span style="font: 12pt serif;">
        <p>
            <b>Reminder:</b>
        </p>
        <p>
            To stay up-to-date with our utility bills, you can subscribe to our shared calendar.
        </p>
        <div style="display: grid; grid-template-columns: auto auto; grid-gap: 10px;">
            <div>
                <p>
                    <b>Apple Calendar:</b>
                </p>
                <p>
                On your Apple device, click on or visit this link: 
                <a href="https://aperkel.w3.uvm.edu/utilities/cal.ics" target="_blank">https://aperkel.w3.uvm.edu/utilities/cal.ics</a>.
                </p>
            </div>
            <div>
                <p>
                    <b>Google Calendar:</b>
                </p>
                <p>
                    Open Google Calendar.
                    On the left side, find "Other calendars" and click the + icon.
                    Select "From URL" and enter the calendar URL: 		<a href="https://aperkel.w3.uvm.edu/utilities/cal.ics" target="_blank">https://aperkel.w3.uvm.edu/utilities/cal.ics</a>
                    Click "Add Calendar."
                </p>
            </div>
            <div style="grid-column: span 2;">
                <p>
                    <b>Other Calendar Applications:</b>
                </p>
                <p>
                Look for an option to add a calendar by URL.
                Enter the calendar URL: <a href="https://aperkel.w3.uvm.edu/utilities/cal.ics" target="_blank">https://aperkel.w3.uvm.edu/utilities/cal.ics</a> and follow the prompts.
                </p>
            </div>
        </div>
        <p>
        Thank you for keeping track of your payments!
        </p>
    </span>"""

    body += reminder

    return body

def check_bills():
    global dates
    global people

    ssl_args = {'ssl': {'ca': 'webdb-cacert.pem'}}
    db_engine = create_engine(
            'mysql://' + os.getenv('DBUSER') + ':' + os.getenv('DBPASS') + '@webdb.uvm.edu/' + os.getenv('DBNAME'),
            connect_args=ssl_args)
    Session = sessionmaker(bind=db_engine)
    db = Session()

    with db_engine.connect() as conn:
        result = conn.execute(text("SELECT fldDue, fldOwe FROM tblUtilities WHERE fldStatus = 'Unpaid'"))
    db.close()

    for row in result.all():
        dates.append(row[0])
        people.append(row[1])

    print(dates)
    print(people)
    
    return dates, people

def send_email(date):
    global date_format
    
    sender_email = 'aaronperkel@gmail.com'
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
    msg['From'] = '81 Buell Utilities <' + sender_email + '>'
    msg['To'] = ', '.join(recipients)

    with smtplib.SMTP_SSL('smtp.gmail.com', 465) as server:
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

    sender_email = 'aaronperkel@gmail.com'
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
    msg['From'] = '81 Buell Utilities <' + sender_email + '>'
    msg['To'] = ', '.join(recipients)

    with smtplib.SMTP_SSL('smtp.gmail.com', 465) as server:
        server.login(sender_email, sender_password)
        server.sendmail(sender_email, recipients, msg.as_string())

    confirm(msg['To'], msg['Subject'], body)

def confirm(recip, sub, msg):
    sender_email = 'aaronperkel@gmail.com'
    sender_password = os.getenv('EMAIL_PASS')
    subject = 'Mail Sent'

    body = '<p style="font: 12pt monospace;">An email was just sent via aperkel.w3.uvm.edu/utilities.</p>'

    body += '<hr>'
    
    body += '<p style="font: 12pt monospace;">To: ' + recip + '<br>'
    body += '<p style="font: 12pt monospace;">Subject: ' + sub + '</p>'

    body += msg

    msg = MIMEText(body, 'html')
    msg['Subject'] = subject
    msg['From'] = '81 Buell Utilities <' + sender_email + '>'
    msg['To'] = 'aaron.perkel27@gmail.com'

    with smtplib.SMTP_SSL('smtp.gmail.com', 465) as server:
        server.login(sender_email, sender_password)
        server.sendmail(sender_email, msg['To'], msg.as_string())

def new_bill():

    sender_email = 'aaronperkel@gmail.com'
    sender_password = os.getenv('EMAIL_PASS')
    recipients = ['aperkel@uvm.edu', 'oacook@uvm.edu', 'bquacken@uvm.edu']
    subject = 'New Bill Posted'

    body = get_email_body(1, sender_email)

    msg = MIMEText(body, 'html')
    msg['Subject'] = subject
    msg['From'] = '81 Buell Utilities <' + sender_email + '>'
    msg['To'] = ', '.join(recipients)

    with smtplib.SMTP_SSL('smtp.gmail.com', 465) as server:
        server.login(sender_email, sender_password)
        server.sendmail(sender_email, recipients, msg.as_string())

    confirm(msg['To'], msg['Subject'], body)
        
if __name__ == '__main__':

    send_email('2024-07-21', 'Aaron')

    # schedule.every().day.at("00:00").do(check_bills)
    # emails_sent = []

    # while True:
    #     dates = []
    #     people = []
    #     schedule.run_pending()

    #     # dates_to_remove = []
    #     # for date in dates:
    #     #     if date in emails_sent:
    #     #         dates_to_remove.append(date)

    #     # for date in dates_to_remove:
    #     #     dates.remove(date)

    #     if dates:
    #         date = dates[0]
    #         new_date = datetime.datetime.strptime(date, date_format)
    #         today = time.strftime("%m/%d/%y", time.localtime())
    #         today = datetime.datetime.strptime(today, date_format)
    #         delta = new_date - today
    #         days_left = delta.days
    #         emails_sent.append(date)

    #         if days_left <= 5:
    #             send_email(date)

    #     time.sleep(1)
