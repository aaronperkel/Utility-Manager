import db
import sys

dates = sys.argv[1]
people = sys.argv[2].replace('\\', '').replace("\"", '').replace("\'",'').split(", ")

print(people)

# db.send_email(dates, people)

