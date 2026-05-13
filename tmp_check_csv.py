from pathlib import Path
import csv
path = Path(r'd:\MFR list\organizations-import-template.csv')
print('exists', path.exists())
if path.exists():
    with path.open(newline='\n', encoding='utf-8') as f:
        rows = list(csv.reader(f))
    print('rows', len(rows))
    print('header', rows[0] if rows else None)
    names = [row[7] if len(row) > 7 else '' for row in rows[1:]]
    print('unique names', len(set(names)), 'blank names', sum(1 for n in names if n.strip() == ''))
    print('duplicate count', len(names) - len(set(names)))
