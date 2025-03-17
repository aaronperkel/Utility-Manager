import sys
import smtplib
from email.mime.text import MIMEText
import os
from dotenv import load_dotenv

load_dotenv('/users/a/p/aperkel/utilities.aperkel.w3.uvm.edu-root/config/.env')

subject = sys.argv[1]
body_text = sys.argv[2]

paragraphs = body_text.strip().split("\n\n")
html_paragraphs = []

for p in paragraphs:
    p = p.replace('\n', '<br>')
    html_paragraphs.append(f"<p style=\"font: 14pt serif;\">{p}</p>")

body_text = "".join(html_paragraphs)

body_text += """
<p style="font: 14pt serif;">
<span style="color: green;">
81 Buell Utilities</span><br>
P: (478)262-8935 | E: me@aaronperkel.com</p>
"""

sender_email = 'aaron.perkel@icloud.com'
sender = 'me@aaronperkel.com'
sender_password = os.getenv('EMAIL_PASS')
recipients = ['aperkel@uvm.edu', 'oacook@uvm.edu', 'bquacken@uvm.edu']

print("Email password:", sender_password)

msg = MIMEText(body_text, 'html')
msg['Subject'] = subject
msg['From'] = '81 Buell Utilities <' + sender + '>'
msg['To'] = ', '.join(recipients)

with smtplib.SMTP('smtp.mail.me.com', 587) as server:
    server.ehlo()
    server.starttls()
    server.ehlo()
    server.login(sender_email, sender_password)
    server.sendmail(sender_email, recipients, msg.as_string())
