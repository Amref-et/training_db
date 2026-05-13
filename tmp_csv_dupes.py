from pathlib import Path
import csv
path = Path(r'd:\MFR list\organizations-import-template.csv')
with path.open(newline='\n', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    d = {}
    for row in reader:
        name = row['organization']
        orgid = row['organization_id']
        d.setdefault(name, set()).add(orgid)

count_dupes = sum(1 for ids in d.values() if len(ids) > 1)
print('duplicate names count', count_dupes)
for name, ids in d.items():
    if len(ids) > 1:
        print(name, ids)
        break
