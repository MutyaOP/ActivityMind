"""
schema.sql + queries.sql файлдарын SQLite-та іске қосады.
Нәтиже: activitymind.db (деректер қоры) және results.txt (әр сұраудың нәтижесі).

    python run_db.py
"""
import re
import sqlite3
import sys
from pathlib import Path

HERE = Path(__file__).parent
sys.stdout.reconfigure(encoding="utf-8")
DB = HERE / 'activitymind.db'

if DB.exists():
    DB.unlink()

con = sqlite3.connect(DB)
con.execute('PRAGMA foreign_keys = ON')
con.executescript((HERE / 'schema.sql').read_text(encoding='utf-8'))

tables = [r[0] for r in con.execute(
    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")]
out = ['Кестелер және жазбалар саны:']
for t in tables:
    out.append(f'  {t:<16} {con.execute(f"SELECT COUNT(*) FROM {t}").fetchone()[0]}')

# Сұрауларды бөлу: «-- Q» деп басталатын түсініктеме — жаңа блок
text = (HERE / 'queries.sql').read_text(encoding='utf-8')
blocks = re.split(r'\n(?=-- Q\d)', text)
for block in blocks:
    m = re.match(r'-- (Q\d+[^\n]*)', block)
    if not m:
        continue
    out.append('\n' + '=' * 78 + '\n' + m.group(1))
    body = '\n'.join(l for l in block.splitlines() if not l.strip().startswith('--'))
    for stmt in [s.strip() for s in body.split(';') if s.strip()]:
        cur = con.execute(stmt)
        if cur.description:
            cols = [d[0] for d in cur.description]
            rows = cur.fetchall()
            widths = [max(len(str(c)), *(len(str(r[i])) for r in rows)) if rows else len(str(c))
                      for i, c in enumerate(cols)]
            out.append(' | '.join(str(c).ljust(w) for c, w in zip(cols, widths)))
            out.append('-+-'.join('-' * w for w in widths))
            for r in rows:
                out.append(' | '.join(str(v).ljust(w) for v, w in zip(r, widths)))
con.commit()
con.close()

(HERE / 'results.txt').write_text('\n'.join(out) + '\n', encoding='utf-8')
print('\n'.join(out))
