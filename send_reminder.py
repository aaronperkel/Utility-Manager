import db
import sys

dates = sys.argv[1]
people = sys.argv[2]

db.send_email(dates, people)

