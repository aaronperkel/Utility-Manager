import sys
import smtplib
from email.mime.text import MIMEText
import os
from dotenv import load_dotenv

load_dotenv()

subject = sys.argv[1]
body_text = sys.argv[2]

sender_email = 'aaron.perkel@icloud.com'
sender_password = os.getenv('EMAIL_PASS')
recipients = ['aperkel@uvm.edu']

msg = MIMEText(body_text, 'html')
msg['Subject'] = subject
msg['From'] = '81 Buell Utilities <' + sender_email + '>'
msg['To'] = ', '.join(recipients)

with smtplib.SMTP('smtp.mail.me.com', 587) as server:
    server.ehlo()
    server.starttls()
    server.ehlo()
    server.login(sender_email, sender_password)
    server.sendmail(sender_email, recipients, msg.as_string())