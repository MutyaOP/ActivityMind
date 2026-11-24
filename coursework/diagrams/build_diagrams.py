"""
ActivityMind — барлық диаграммаларды .drawio форматында құрастырады.
Файлдарды draw.io-да ашып, өңдеуге болады. PNG экспорты:
    "C:\\Program Files\\draw.io\\draw.io.exe" -x -f png -s 2 -o png\\<name>.png <name>.drawio
(export_png.ps1 скрипті барлығын бір рет экспорттайды.)
"""
from html import escape
from pathlib import Path

OUT = Path(__file__).parent
FONT = 'fontFamily=Helvetica;fontSize=12;'


class Diagram:
    def __init__(self, name):
        self.name = name
        self.cells = []
        self.n = 2

    def _id(self):
        self.n += 1
        return f'c{self.n}'

    def v(self, value, x, y, w, h, style, parent='1'):
        i = self._id()
        self.cells.append(
            f'<mxCell id="{i}" value="{escape(value, quote=True)}" style="{FONT}{style}" vertex="1" parent="{parent}">'
            f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>')
        return i

    def e(self, s, t, value='', style='', points=None):
        i = self._id()
        pts = ''
        if points:
            pts = '<Array as="points">' + ''.join(f'<mxPoint x="{x}" y="{y}"/>' for x, y in points) + '</Array>'
        self.cells.append(
            f'<mxCell id="{i}" value="{escape(value, quote=True)}" style="{FONT}html=1;{style}" edge="1" parent="1" source="{s}" target="{t}">'
            f'<mxGeometry relative="1" as="geometry">{pts}</mxGeometry></mxCell>')
        return i

    def arrow(self, x1, y1, x2, y2, value='', style='endArrow=block;endFill=1;'):
        """Еркін нүктелер арасындағы бағыттама (sequence диаграммасы үшін)."""
        i = self._id()
        self.cells.append(
            f'<mxCell id="{i}" value="{escape(value, quote=True)}" style="{FONT}html=1;verticalAlign=bottom;{style}" edge="1" parent="1">'
            f'<mxGeometry relative="1" as="geometry"><mxPoint x="{x1}" y="{y1}" as="sourcePoint"/>'
            f'<mxPoint x="{x2}" y="{y2}" as="targetPoint"/></mxGeometry></mxCell>')
        return i

    def label(self, edge, text, pos):
        """Байланыс ұшындағы еселік белгісі (pos: -1 басы … 1 соңы)."""
        i = self._id()
        self.cells.append(
            f'<mxCell id="{i}" value="{escape(text)}" style="{FONT}edgeLabel;html=1;resizable=0;labelBackgroundColor=#ffffff;fontSize=11;" vertex="1" connectable="0" parent="{edge}">'
            f'<mxGeometry x="{pos}" relative="1" as="geometry"><mxPoint x="14" y="-8" as="offset"/></mxGeometry></mxCell>')

    def save(self):
        xml = ('<mxfile host="drawio"><diagram name="' + self.name + '"><mxGraphModel dx="1200" dy="800" grid="0" '
               'gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="0" pageScale="1" '
               'background="#ffffff" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>'
               + ''.join(self.cells) + '</root></mxGraphModel></diagram></mxfile>')
        (OUT / f'{self.name}.drawio').write_text(xml, encoding='utf-8')
        print('saved', self.name)


# ---------- Стильдер ----------
ACTOR = 'shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;html=1;outlineConnect=0;fontStyle=1;'
UC = 'ellipse;whiteSpace=wrap;html=1;fillColor=#ede9fe;strokeColor=#7c3aed;'
UC_EXT = 'ellipse;whiteSpace=wrap;html=1;fillColor=#e0f2fe;strokeColor=#0284c7;'
BOUND = 'rounded=1;arcSize=2;whiteSpace=wrap;html=1;fillColor=#fafafa;strokeColor=#64748b;verticalAlign=top;fontStyle=1;fontSize=14;'
ASSOC = 'endArrow=none;'
INCL = 'endArrow=open;dashed=1;endSize=10;fontColor=#475569;'
CLS = 'verticalAlign=top;align=left;overflow=fill;html=1;whiteSpace=wrap;fillColor=#ffffff;strokeColor=#475569;spacing=0;'
ACT = 'rounded=1;arcSize=40;whiteSpace=wrap;html=1;fillColor=#ede9fe;strokeColor=#7c3aed;'
DEC = 'rhombus;whiteSpace=wrap;html=1;fillColor=#fef3c7;strokeColor=#d97706;fontSize=11;'
START = 'ellipse;html=1;fillColor=#111827;strokeColor=#111827;'
END = 'ellipse;html=1;shape=endState;fillColor=#111827;strokeColor=#111827;'
FLOW = 'edgeStyle=orthogonalEdgeStyle;rounded=1;endArrow=block;endFill=1;fontSize=11;'
LANE = 'swimlane;startSize=32;html=1;fillColor=#f1f5f9;strokeColor=#64748b;fontStyle=1;fontSize=13;swimlaneFillColor=#ffffff;'


def cls_label(title, attrs, methods, stereo=''):
    st = f'<i>«{stereo}»</i><br>' if stereo else ''
    a = '<br>'.join(escape(x) for x in attrs)
    m = '<br>'.join(escape(x) for x in methods)
    return (f'<p style="margin:0;margin-top:4px;text-align:center;">{st}<b>{escape(title)}</b></p>'
            f'<hr size="1" style="border-style:solid;"><p style="margin:0 6px;">{a}</p>'
            + (f'<hr size="1" style="border-style:solid;"><p style="margin:0 6px;">{m}</p>' if methods else ''))


# =====================================================================
# 1. USE CASE
# =====================================================================
def use_case():
    d = Diagram('01_use_case')
    d.v('ActivityMind', 170, 10, 820, 900, BOUND)
    user = d.v('Пайдаланушы', 40, 300, 40, 80, ACTOR)
    admin = d.v('Әкімші', 40, 720, 40, 80, ACTOR)
    agent = d.v('Desktop Agent', 1080, 560, 40, 80, ACTOR + 'fillColor=#e0f2fe;')
    ai = d.v('AI Analyzer\n(сыртқы жүйе)', 1080, 420, 40, 80, ACTOR + 'fillColor=#e0f2fe;')
    W, H = 190, 56
    uc = {}
    spec = [
        ('01', 'UC-01 Тіркелу және жүйеге кіру', 220, 50),
        ('02', 'UC-02 Тапсырмаларды басқару', 220, 130),
        ('03', 'UC-03 Таймерді бастау / тоқтату', 220, 210),
        ('04', 'UC-04 Уақытты қолмен енгізу', 470, 170),
        ('05', 'UC-05 Әдетті белгілеу', 220, 290),
        ('06', 'UC-06 Аналитиканы көру', 220, 370),
        ('14', 'UC-14 Күндік статистиканы есептеу', 520, 370),
        ('07', 'UC-07 Ұсыныстарды алу', 220, 450),
        ('08', 'UC-08 AI-талдау сұрау', 770, 450),
        ('09', 'UC-09 Құрылғыны тіркеу (токен алу)', 220, 530),
        ('15', 'UC-15 Профильді өңдеу / аккаунтты жою', 220, 610),
        ('10', 'UC-10 Белсенділік пакетін жіберу', 770, 560),
        ('11', 'UC-11 Құрылғы токенін тексеру', 500, 640),
        ('16', 'UC-16 Қолданбаны санатқа бөлу', 770, 680),
        ('12', 'UC-12 Пайдаланушыларды басқару', 220, 740),
        ('13', 'UC-13 Жүйе статистикасын көру', 220, 820),
    ]
    for k, t, x, y in spec:
        style = UC_EXT if k in ('10', '11', '16', '08') else UC
        uc[k] = d.v(t, x, y, W, H, style)
    for k in ('01', '02', '03', '05', '06', '07', '09', '15'):
        d.e(user, uc[k], style=ASSOC)
    for k in ('12', '13'):
        d.e(admin, uc[k], style=ASSOC)
    d.e(agent, uc['10'], style=ASSOC)
    d.e(ai, uc['08'], style=ASSOC)
    d.e(uc['04'], uc['03'], '«extend»', INCL)
    d.e(uc['06'], uc['14'], '«include»', INCL)
    d.e(uc['07'], uc['14'], '«include»', INCL)
    d.e(uc['08'], uc['07'], '«extend»', INCL)
    d.e(uc['10'], uc['11'], '«include»', INCL)
    d.e(uc['10'], uc['16'], '«include»', INCL)
    d.v('Әкімші — Пайдаланушының мұрагері: ол да UC-02…UC-15 орындай алады', 40, 870, 120, 60,
        'text;html=1;whiteSpace=wrap;fontSize=10;fontColor=#64748b;')
    d.e(admin, user, style='endArrow=block;endFill=0;endSize=12;', points=[(20, 760), (20, 340)])
    d.save()


# =====================================================================
# 2. ACTIVITY 1 — тапсырма → таймер → статистика → ұсыныс
# =====================================================================
def activity_focus():
    d = Diagram('02_activity_focus_session')
    lanes = [('Пайдаланушы', 0), ('Веб-платформа (Laravel)', 300), ('AI Analyzer', 600)]
    for t, x in lanes:
        d.v(t, x, 0, 300, 1010, LANE)
    s = d.v('', 135, 50, 30, 30, START)
    a1 = d.v('Жүйеге кіру', 80, 100, 140, 44, ACT)
    a2 = d.v('Тапсырма құру немесе таңдау', 80, 175, 140, 50, ACT)
    a3 = d.v('«Старт» батырмасын басу', 80, 255, 140, 44, ACT)
    a4 = d.v('Белсенді таймер бар ма?', 380, 240, 140, 80, DEC)
    a5 = d.v('Алдыңғы таймерді тоқтату (duration_sec)', 470, 335, 125, 60, ACT)
    a6 = d.v('Жаңа time_entry жасау (ended_at = NULL)', 380, 425, 150, 50, ACT)
    a7 = d.v('Жұмыс істеу (фокус)', 80, 430, 140, 44, ACT)
    a8 = d.v('«Тоқтату» батырмасын басу', 80, 510, 140, 44, ACT)
    a9 = d.v('ended_at, duration_sec сақтау', 380, 510, 150, 44, ACT)
    a10 = d.v('StatsService::sync — daily_stats upsert', 380, 590, 150, 50, ACT)
    a11 = d.v('Ереже кідірісі өтті ме? (rule_key)', 380, 670, 150, 80, DEC)
    a12 = d.v('AI қосулы ма?', 395, 785, 120, 70, DEC)
    a13 = d.v('14 күн агрегаттарын талдау (регрессия, pandas)', 680, 780, 150, 60, ACT + 'fillColor=#e0f2fe;strokeColor=#0284c7;')
    a14 = d.v('Ұсыныстарды сақтау (≤4)', 380, 890, 150, 44, ACT)
    a15 = d.v('Дашбордта ұсынысты көру', 80, 890, 140, 44, ACT)
    en = d.v('', 135, 965, 30, 30, END)
    for a, b, t in [(s, a1, ''), (a1, a2, ''), (a2, a3, ''), (a3, a4, ''), (a4, a5, 'иә'), (a5, a6, ''),
                    (a4, a6, 'жоқ'), (a6, a7, ''), (a7, a8, ''), (a8, a9, ''), (a9, a10, ''), (a10, a11, ''),
                    (a11, a12, 'иә'), (a12, a13, 'иә'), (a12, a14, 'жоқ'),
                    (a14, a15, ''), (a15, en, '')]:
        d.e(a, b, t, FLOW)
    d.e(a13, a14, '≤3 қорытынды', FLOW, points=[(755, 912)])
    d.e(a11, a15, 'жоқ (қайталанбайды)', FLOW, points=[(310, 710), (310, 870), (150, 870)])
    d.save()


# =====================================================================
# 3. ACTIVITY 2 — Desktop Agent: офлайн буфер және синхрондау («қиын жер»)
# =====================================================================
def activity_agent():
    d = Diagram('03_activity_agent_sync')
    for t, x in [('Пайдаланушы', 0), ('Desktop Agent (Python)', 260), ('Agent API (Laravel)', 560)]:
        d.v(t, x, 0, {0: 260, 260: 300, 560: 320}[x], 1060, LANE)
    s = d.v('', 115, 50, 30, 30, START)
    u1 = d.v('«Құрылғылар» бетінде құрылғы жасау', 60, 100, 140, 50, ACT)
    p1 = d.v('Токен генерациялау, SHA-256 хэшін сақтау', 640, 100, 160, 50, ACT)
    u2 = d.v('Токенді config.ini-ге жазу (бір рет көрсетіледі)', 60, 190, 140, 60, ACT)
    g1 = d.v('Белсенді терезені өлшеу (әр 5 с)', 320, 280, 140, 50, ACT)
    g2 = d.v('Сессия жабылды ма?', 330, 360, 120, 70, DEC)
    g3 = d.v('Сессияны SQLite буферге жазу (sent = 0)', 320, 460, 140, 50, ACT)
    g4 = d.v('Әр 60 с: жіберілмегендерді алу (≤500)', 320, 540, 140, 50, ACT)
    g5 = d.v('Желі бар ма?', 330, 620, 120, 70, DEC)
    g6 = d.v('Буферде қалдыру, келесі циклде қайталау', 300 - 20, 760, 140, 50, ACT + 'fillColor=#fee2e2;strokeColor=#dc2626;')
    p2 = d.v('Токенді тексеру (401 / жалғастыру)', 640, 620, 160, 50, ACT)
    p3 = d.v('Валидация + санатқа бөлу (AppCategorizer)', 640, 700, 160, 50, ACT)
    p4 = d.v('INSERT … UNIQUE(device_id, started_at, app_name): дубльдер есептеледі, жазылмайды', 625, 780, 190, 70, ACT)
    p5 = d.v('200 {accepted, duplicates}', 640, 880, 160, 44, ACT)
    g7 = d.v('Жіберілгендерді sent = 1 деп белгілеу', 320, 880, 140, 50, ACT)
    en = d.v('', 375, 1000, 30, 30, END)
    for a, b, t in [(s, u1, ''), (u1, p1, ''), (u2, g1, 'агентті іске қосу'), (g1, g2, ''),
                    (g2, g3, 'иә'), (g3, g4, ''), (g4, g5, ''), (g5, p2, 'иә: POST /api/agent/activity'),
                    (g5, g6, 'жоқ'), (p2, p3, ''), (p3, p4, ''), (p4, p5, ''), (p5, g7, ''), (g7, en, '')]:
        d.e(a, b, t, FLOW)
    d.e(p1, u2, 'токен', FLOW, points=[(720, 220)])
    d.e(g2, g1, 'жоқ', FLOW, points=[(480, 395), (480, 305)])
    d.e(g6, g4, 'қайталау', FLOW, points=[(290, 785), (290, 565)])
    d.save()


# =====================================================================
# 4. CLASS
# =====================================================================
def class_diagram():
    d = Diagram('04_class')
    C = {}

    def c(key, x, y, w, h, title, attrs, methods, stereo='', fill='#ffffff'):
        C[key] = d.v(cls_label(title, attrs, methods, stereo), x, y, w, h, CLS + f'fillColor={fill};')

    c('model', 520, 0, 220, 70, 'Model', ['# table: string'], ['+ save(): bool', '+ delete(): bool'], 'abstract', '#f1f5f9')
    c('user', 20, 140, 220, 190, 'User', ['- id: int', '- name: string', '- email: string', '- password: string',
                                          '- role: Role {user, admin}', '- timezone: string', '- dailyFocusGoalMin: int'],
      ['+ isAdmin(): bool', '+ tasks(): Task[]', '+ devices(): Device[]'])
    c('cat', 300, 140, 200, 120, 'Category', ['- id: int', '- name: string', '- color: string', '- type: CategoryType'],
      ['+ isSystem(): bool'])
    c('task', 560, 140, 220, 190, 'Task', ['- id: int', '- title: string', '- priority: Priority', '- status: Status',
                                           '- dueDate: Date', '- estimatedMin: int', '- completedAt: DateTime'],
      ['+ isOverdue(): bool', '+ toggle(): void', '+ spentSec(): int'])
    c('te', 840, 140, 220, 160, 'TimeEntry', ['- id: int', '- startedAt: DateTime', '- endedAt: DateTime', '- durationSec: int',
                                              '- note: string'], ['+ isRunning(): bool', '+ stop(at): void'])
    c('habit', 20, 420, 220, 150, 'Habit', ['- id: int', '- title: string', '- frequency: Frequency', '- targetPerPeriod: int',
                                            '- isActive: bool'], ['+ toggle(date): void', '+ isDone(date): bool'])
    c('hlog', 20, 650, 200, 90, 'HabitLog', ['- logDate: Date', '- isDone: bool'], [])
    c('device', 300, 420, 220, 150, 'Device', ['- id: int', '- name: string', '- platform: string', '- tokenHash: string',
                                               '- lastSyncAt: DateTime'], ['+ issueToken(): string', '+ isOnline(): bool'])
    c('alog', 300, 650, 220, 130, 'ActivityLog', ['- appName: string', '- windowTitle: string', '- startedAt: DateTime',
                                                  '- durationSec: int', '- isIdle: bool'], [])
    c('stat', 580, 420, 220, 150, 'DailyStat', ['- statDate: Date', '- focusSec: int', '- trackedSec: int', '- tasksDone: int',
                                                '- habitsDone: int', '- productivityScore: int'], [])
    c('rec', 860, 420, 220, 150, 'Recommendation', ['- type: RecType', '- text: string', '- source: {rule, ai}',
                                                    '- ruleKey: string', '- isRead: bool'], ['+ markRead(): void'])
    # Сервистер
    c('timer', 580, 790, 230, 100, 'TimerService', [], ['+ start(user, task?, cat?): TimeEntry', '+ stop(user): TimeEntry',
                                                       '+ addManual(...): TimeEntry'], 'service', '#f5f3ff')
    c('stats', 580, 650, 230, 80, 'StatsService', [], ['+ sync(user, from, to): array', '+ analytics(user, days): array'],
      'service', '#f5f3ff')
    c('recsvc', 860, 650, 240, 120, 'RecommendationService', ['- rules: RecommendationRule[]', '- insights: InsightProvider'],
      ['+ generate(user): int', '- recentlyCreated(key): bool'], 'service', '#f5f3ff')
    c('rule', 1140, 650, 220, 70, 'RecommendationRule', [], ['+ evaluate(ctx): ?Advice'], 'interface', '#e0f2fe')
    c('ip', 1140, 790, 220, 70, 'InsightProvider', [], ['+ suggest(user, metrics): Advice[]'], 'interface', '#e0f2fe')
    c('aic', 1140, 920, 220, 84, 'AiAnalyzerClient', ['- url, token, timeout'], ['+ suggest(user, metrics): Advice[]'], '', '#f5f3ff')
    c('cat_svc', 300, 850, 220, 70, 'AppCategorizer', [], ['+ categorize(app, title): ?Category'], 'service', '#f5f3ff')

    INH = 'endArrow=block;endFill=0;endSize=12;edgeStyle=orthogonalEdgeStyle;'
    INH = 'endArrow=block;endFill=0;endSize=12;'
    d.e(C['user'], C['model'], '', INH, points=[(130, 35)])
    d.e(C['task'], C['model'], '', INH)
    d.e(C['te'], C['model'], '', INH, points=[(950, 35)])
    d.v('… барлық сущность-кластар Model-ден мұрагерлік алады', 250, 70, 270, 30, 'text;html=1;fontSize=10;fontColor=#64748b;')

    def rel(a, b, ma, mb, style, points=None):
        e = d.e(C[a], C[b], '', style, points=points)
        d.label(e, ma, -0.8)
        d.label(e, mb, 0.8)

    COMP = 'startArrow=diamondThin;startFill=1;startSize=14;endArrow=none;'
    AGGR = 'startArrow=diamondThin;startFill=0;startSize=14;endArrow=none;'
    ASS = 'endArrow=open;endSize=8;'
    rel('user', 'habit', '1', '0..*', COMP)
    rel('habit', 'hlog', '1', '0..*', COMP)
    rel('user', 'device', '1', '0..*', COMP)
    rel('device', 'alog', '1', '0..*', COMP)
    rel('user', 'task', '1', '0..*', COMP, [(200, 115), (620, 115)])
    rel('task', 'te', '0..1', '0..*', AGGR)
    rel('cat', 'task', '0..1', '0..*', AGGR)
    rel('user', 'cat', '0..1', '0..*', ASS)
    rel('user', 'stat', '1', '0..*', COMP, [(260, 375), (690, 375)])
    rel('user', 'rec', '1', '0..*', COMP, [(270, 395), (970, 395)])
    DEP = 'endArrow=open;dashed=1;endSize=8;'
    REAL = 'endArrow=block;endFill=0;dashed=1;endSize=12;'
    d.e(C['timer'], C['te'], '«create»', DEP, points=[(830, 840), (830, 320), (900, 320)])
    d.e(C['stats'], C['stat'], '«upsert»', DEP)
    d.e(C['recsvc'], C['rec'], '«create»', DEP)
    e = d.e(C['recsvc'], C['rule'], '', 'startArrow=diamondThin;startFill=0;startSize=14;endArrow=none;')
    d.label(e, '1..*', 0.8)
    d.e(C['recsvc'], C['ip'], '', ASS, points=[(980, 825)])
    d.e(C['aic'], C['ip'], '', REAL)
    d.e(C['cat_svc'], C['alog'], '«use»', DEP)
    d.save()


# =====================================================================
# 5–6. SEQUENCE
# =====================================================================
LIFE = 'shape=umlLifeline;perimeter=lifelinePerimeter;whiteSpace=wrap;html=1;container=0;collapsible=0;recursiveResize=0;size=46;fillColor=#ede9fe;strokeColor=#7c3aed;fontStyle=1;'
ACTV = 'html=1;fillColor=#ffffff;strokeColor=#475569;'
SYNC = 'endArrow=block;endFill=1;'
RET = 'endArrow=open;dashed=1;'
FRAME = 'shape=umlFrame;whiteSpace=wrap;html=1;width=110;height=26;fillColor=none;strokeColor=#64748b;fontStyle=1;'


def seq(name, parts, msgs, frames, height):
    d = Diagram(name)
    xs = {}
    for i, (k, title) in enumerate(parts):
        x = 20 + i * 190
        xs[k] = x + 70
        d.v(title, x, 10, 140, height, LIFE)
    for (x0, y0, w, h, t) in frames:
        d.v(t, x0, y0, w, h, FRAME)
    for (a, b, y, t, kind) in msgs:
        if a == b:
            x = xs[a]
            d.arrow(x + 5, y, x + 5, y + 26, t, SYNC + f'curved=0;rounded=0;edgeStyle=orthogonalEdgeStyle;')
            continue
        st = RET if kind == 'r' else SYNC
        d.arrow(xs[a] + (5 if xs[b] > xs[a] else -5), y, xs[b] + (-5 if xs[b] > xs[a] else 5), y, t, st)
    d.save()


def seq_agent():
    parts = [('agent', 'Desktop Agent'), ('buf', 'SQLite буфер'), ('api', 'AgentActivityController'),
             ('auth', 'DeviceTokenAuth'), ('cat', 'AppCategorizer'), ('db', 'MySQL')]
    m = [
        ('agent', 'buf', 100, 'save(session, sent=0)', 's'),
        ('agent', 'buf', 135, 'pending(limit=500)', 's'),
        ('buf', 'agent', 165, 'items[ ]', 'r'),
        ('agent', 'api', 230, 'POST /api/agent/activity (Bearer amd_…)', 's'),
        ('api', 'auth', 270, 'sha256(token) → devices.token_hash', 's'),
        ('auth', 'api', 300, 'device', 'r'),
        ('api', 'cat', 340, 'categorize(app, title)', 's'),
        ('cat', 'api', 370, 'category_id | null', 'r'),
        ('api', 'db', 410, 'INSERT … ON CONFLICT DO NOTHING', 's'),
        ('db', 'api', 440, 'inserted = N', 'r'),
        ('api', 'db', 480, 'UPDATE devices SET last_sync_at', 's'),
        ('api', 'agent', 520, '200 {accepted: N, duplicates: M}', 'r'),
        ('agent', 'buf', 560, 'markSent(ids)', 's'),
        ('api', 'agent', 650, '— желі қатесі / 5xx —', 'r'),
        ('agent', 'buf', 690, 'қалдыру (sent = 0), келесі циклде қайта жіберу', 's'),
    ]
    frames = [(10, 200, 1150, 390, 'alt [желі бар]'), (10, 610, 1150, 110, '[желі жоқ]'),
              (10, 55, 560, 130, 'loop [әр 60 с]')]
    seq('05_sequence_agent_sync', parts, m, frames, 760)


def seq_recommend():
    parts = [('u', 'Пайдаланушы'), ('c', 'RecommendationController'), ('s', 'RecommendationService'),
             ('st', 'StatsService'), ('ai', 'AiAnalyzerClient'), ('db', 'MySQL')]
    m = [
        ('u', 'c', 80, 'POST /recommendations/generate', 's'),
        ('c', 's', 120, 'generate(user)', 's'),
        ('s', 'st', 160, 'sync(user, −14 күн, бүгін)', 's'),
        ('st', 'db', 195, 'UPSERT daily_stats', 's'),
        ('st', 's', 230, 'context', 'r'),
        ('s', 's', 260, 'rules → кандидаттар', 's'),
        ('s', 'ai', 340, 'suggest(metrics) — тек сандар', 's'),
        ('ai', 's', 380, '≤3 {key, type, text}', 'r'),
        ('ai', 's', 450, 'таймаут 5 с / қате → [ ]', 'r'),
        ('s', 'db', 520, 'SELECT … rule_key, кідіріс', 's'),
        ('s', 'db', 560, 'INSERT recommendations (≤4)', 's'),
        ('s', 'c', 600, 'created = N', 'r'),
        ('c', 'u', 640, 'redirect + «N жаңа кеңес»', 'r'),
    ]
    frames = [(10, 310, 1150, 160, 'opt [AI қосулы]'), (10, 495, 1150, 85, 'loop [әр кандидат]')]
    seq('06_sequence_recommendations', parts, m, frames, 700)


# =====================================================================
# 7. ER
# =====================================================================
def er():
    d = Diagram('07_er')
    ENT = 'swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize=28;fillColor=#ede9fe;strokeColor=#7c3aed;horizontalStack=0;resizeParent=1;resizeLast=0;collapsible=0;html=1;swimlaneFillColor=#ffffff;'
    ROW = 'text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=6;html=1;fontSize=11;'
    T = {}

    def ent(key, x, y, cols, w=210):
        h = 28 + 20 * len(cols)
        pid = d.v(key, x, y, w, h, ENT)
        for i, c in enumerate(cols):
            st = ROW + ('fontStyle=5;' if c.startswith('PK') else 'fontStyle=2;' if c.startswith('FK') else '')
            d.v(c, 0, 28 + i * 20, w, 20, st, parent=pid)
        T[key] = pid

    ent('categories', 20, 20, ['PK id', 'FK user_id (null = жүйелік)', 'name', 'color', 'type'])
    ent('tasks', 20, 220, ['PK id', 'FK user_id', 'FK category_id', 'title', 'priority', 'status', 'due_date',
                           'estimated_min', 'completed_at'])
    ent('time_entries', 20, 480, ['PK id', 'FK user_id', 'FK task_id', 'FK category_id', 'started_at', 'ended_at',
                                  'duration_sec', 'note'])
    ent('habits', 360, 20, ['PK id', 'FK user_id', 'title', 'frequency', 'target_per_period', 'is_active'])
    ent('users', 360, 260, ['PK id', 'name', 'email (UQ)', 'password', 'role', 'timezone', 'daily_focus_goal_min'])
    ent('recommendations', 360, 480, ['PK id', 'FK user_id', 'type', 'text', 'source', 'rule_key', 'is_read', 'created_at'])
    ent('habit_logs', 700, 20, ['PK id', 'FK habit_id', 'log_date', 'is_done', 'UQ (habit_id, log_date)'], 230)
    ent('devices', 700, 260, ['PK id', 'FK user_id', 'name', 'platform', 'token_hash (UQ)', 'last_sync_at'])
    ent('activity_logs', 700, 480, ['PK id', 'FK device_id', 'FK category_id', 'app_name', 'window_title', 'started_at',
                                    'duration_sec', 'is_idle', 'UQ (device_id, started_at, app_name)'], 260)
    ent('daily_stats', 700, 740, ['PK id', 'FK user_id', 'stat_date', 'focus_sec', 'tracked_sec', 'tasks_done',
                                  'habits_done', 'habits_total', 'productivity_score', 'UQ (user_id, stat_date)'], 220)

    ONE_MANY = 'endArrow=ERmany;startArrow=ERmandOne;endFill=0;startFill=0;endSize=12;startSize=12;'
    OPT_MANY = 'endArrow=ERmany;startArrow=ERzeroToOne;endFill=0;startFill=0;endSize=12;startSize=12;'
    for a, b, st, pts in [
        ('users', 'tasks', ONE_MANY, None),
        ('users', 'time_entries', ONE_MANY, [(330, 410), (330, 570)]),
        ('users', 'habits', ONE_MANY, None),
        ('habits', 'habit_logs', ONE_MANY, None),
        ('users', 'devices', ONE_MANY, None),
        ('devices', 'activity_logs', ONE_MANY, None),
        ('users', 'daily_stats', ONE_MANY, [(650, 420), (650, 850)]),
        ('users', 'recommendations', ONE_MANY, None),
        ('categories', 'tasks', OPT_MANY, None),
        ('tasks', 'time_entries', OPT_MANY, None),
        ('users', 'categories', OPT_MANY, [(290, 290), (290, 100)]),
        ('categories', 'activity_logs', OPT_MANY, [(310, 50), (310, 5), (1000, 5), (1000, 580)]),
    ]:
        d.e(T[a], T[b], '', st, points=pts)
    d.save()


# =====================================================================
# 8–9. C4
# =====================================================================
P = 'rounded=1;whiteSpace=wrap;html=1;fillColor=#08427b;strokeColor=#073b6f;fontColor=#ffffff;arcSize=20;'
SYS = 'rounded=1;whiteSpace=wrap;html=1;fillColor=#1168bd;strokeColor=#0b4884;fontColor=#ffffff;arcSize=8;'
EXT = 'rounded=1;whiteSpace=wrap;html=1;fillColor=#8c8c8c;strokeColor=#6b6b6b;fontColor=#ffffff;arcSize=8;'
CONT = 'rounded=1;whiteSpace=wrap;html=1;fillColor=#438dd5;strokeColor=#3c7fc0;fontColor=#ffffff;arcSize=8;'
DBS = 'shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;size=12;fillColor=#438dd5;strokeColor=#3c7fc0;fontColor=#ffffff;'
REL = 'endArrow=block;endFill=1;dashed=1;fontSize=11;fontColor=#334155;'
BND = 'rounded=1;arcSize=2;whiteSpace=wrap;html=1;fillColor=none;dashed=1;strokeColor=#64748b;verticalAlign=bottom;align=left;spacingLeft=10;fontStyle=1;'


def c4_context():
    d = Diagram('08_c4_context')
    u = d.v('<b>Пайдаланушы</b><br>[Person]<br><font style="font-size:11px">Студент / маман: уақытын, тапсырмалары мен әдеттерін бақылайды</font>', 40, 30, 220, 110, P)
    a = d.v('<b>Әкімші</b><br>[Person]<br><font style="font-size:11px">Пайдаланушылар мен рөлдерді басқарады</font>', 560, 30, 220, 100, P)
    s = d.v('<b>ActivityMind</b><br>[Software System]<br><font style="font-size:11px">Тапсырмалар, таймер, әдеттер, белсенділікті автоматты есепке алу, аналитика және ұсыныстар</font>', 260, 250, 300, 130, SYS)
    ag = d.v('<b>Desktop Agent</b><br>[Software System, Windows]<br><font style="font-size:11px">Белсенді терезені бақылап, сессияларды жібереді</font>', 20, 470, 240, 110, EXT)
    ai = d.v('<b>AI Analyzer</b><br>[Software System, Python]<br><font style="font-size:11px">14 күндік агрегаттардан заңдылықтар табады</font>', 560, 470, 240, 110, EXT)
    mail = d.v('<b>SMTP пошта</b><br>[External System]<br><font style="font-size:11px">Құпиясөзді қалпына келтіру хаттары</font>', 600, 250, 200, 100, EXT)
    d.e(u, s, 'Пайдаланады<br>[HTTPS, браузер]', REL)
    d.e(a, s, 'Басқарады<br>[HTTPS]', REL)
    d.e(u, ag, 'Орнатады, токен енгізеді', REL, points=[(60, 300)])
    d.e(ag, s, 'Белсенділік сессиялары<br>[JSON/HTTPS, Bearer]', REL)
    d.e(s, ai, 'Агрегаттар → қорытындылар<br>[JSON/HTTP, 5 с]', REL)
    d.e(s, mail, 'Хат жібереді [SMTP]', REL)
    d.save()


def c4_containers():
    d = Diagram('09_c4_containers')
    u = d.v('<b>Пайдаланушы / Әкімші</b><br>[Person]', 330, 0, 220, 70, P)
    d.v('ActivityMind [Software System]', 160, 110, 620, 520, BND)
    web = d.v('<b>Веб-клиент</b><br>[Container: Blade + Tailwind + Alpine.js + Chart.js]<br><font style="font-size:11px">Беттер, графиктер, таймер</font>', 190, 140, 250, 100, CONT)
    app = d.v('<b>Laravel қосымшасы</b><br>[Container: PHP 8.3, Laravel 13]<br><font style="font-size:11px">MVC + сервистік қабат, Policies, Agent API, Scheduler</font>', 190, 300, 250, 110, CONT)
    db = d.v('<b>Деректер қоры</b><br>[Container: MySQL / SQLite]<br><font style="font-size:11px">10 кесте, 3NF</font>', 220, 480, 190, 110, DBS)
    ai = d.v('<b>AI Analyzer</b><br>[Container: Python, Flask, pandas, scikit-learn]<br><font style="font-size:11px">POST /analyze, күй сақтамайды</font>', 510, 300, 240, 110, CONT)
    q = d.v('<b>Кезек / Scheduler</b><br>[Container: Laravel queue + cron]<br><font style="font-size:11px">recalc-stats, ұсыныстар генерациясы</font>', 510, 480, 240, 100, CONT)
    ag = d.v('<b>Desktop Agent</b><br>[Container: Python 3, ctypes]<br><font style="font-size:11px">Терезені өлшеу, сессия құрастыру</font>', 820, 160, 210, 100, EXT)
    buf = d.v('<b>Жергілікті буфер</b><br>[SQLite]', 840, 330, 170, 90, DBS.replace('#438dd5', '#8c8c8c').replace('#3c7fc0', '#6b6b6b'))
    mail = d.v('<b>SMTP</b><br>[External]', 0, 320, 130, 70, EXT)
    d.e(u, web, 'HTTPS', REL)
    d.e(web, app, 'Формалар, сессия, CSRF<br>[HTTPS]', REL)
    d.e(app, db, 'Eloquent ORM<br>[SQL]', REL)
    d.e(app, ai, 'JSON<br>[HTTP, 5 с]', REL)
    d.e(q, app, 'Командалар', REL)
    d.e(ag, buf, 'Сессиялар', REL)
    d.e(ag, app, 'POST /api/agent/activity<br>[JSON/HTTPS, Bearer token]', REL, points=[(925, 120), (470, 120), (470, 320)])
    d.e(app, mail, 'SMTP', REL)
    d.save()


# =====================================================================
# 10. SOLID: дейін / кейін
# =====================================================================
def solid():
    d = Diagram('10_solid_strategy')
    d.v('<b>ДЕЙІН</b>: SRP / OCP / DIP бұзылған', 20, 0, 360, 30, 'text;html=1;fontSize=14;')
    before = d.v(cls_label('RecommendationService', ['- stats: StatsService', '- habits: HabitService', '- ai: AiAnalyzerClient  ⟵ нақты класс (DIP)'],
                           ['+ generate(user): int', '- ruleCandidates(ctx): array  ⟵ 12 ереже бір әдісте, 140 жол (SRP, OCP)',
                            '- aiCandidates(user, ctx): array', '- recentlyCreated(key, days): bool']),
                 20, 40, 380, 170, CLS + 'fillColor=#fee2e2;strokeColor=#dc2626;')
    aic = d.v(cls_label('AiAnalyzerClient', [], ['+ suggest(user, metrics): array']), 100, 280, 220, 60, CLS)
    d.e(before, aic, '', 'endArrow=open;endSize=8;')

    d.v('<b>КЕЙІН</b>: Strategy үлгісі + интерфейске тәуелділік', 470, 0, 420, 30, 'text;html=1;fontSize=14;')
    svc = d.v(cls_label('RecommendationService', ['- rules: iterable<RecommendationRule>', '- insights: InsightProvider'],
                        ['+ generate(user): int', '- isCoolingDown(key, days): bool'], 'service'),
              470, 40, 300, 125, CLS + 'fillColor=#dcfce7;strokeColor=#16a34a;')
    rule = d.v(cls_label('RecommendationRule', [], ['+ key(): string', '+ cooldownDays(): int', '+ evaluate(ctx): ?Advice'], 'interface'),
               1040, 40, 230, 100, CLS + 'fillColor=#e0f2fe;')
    ip = d.v(cls_label('InsightProvider', [], ['+ suggest(user, metrics): Advice[]'], 'interface'), 470, 220, 240, 70, CLS + 'fillColor=#e0f2fe;')
    aic2 = d.v(cls_label('AiAnalyzerClient', [], ['+ suggest(user, metrics): Advice[]']), 470, 350, 240, 60, CLS)
    nul = d.v(cls_label('NullInsightProvider', [], ['+ suggest(): [ ]']), 740, 350, 180, 60, CLS)
    rs = []
    for i, n in enumerate(['LowFocusRule', 'OverdueTasksRule', 'LongSessionsRule', 'BestHourRule']):
        rs.append(d.v(cls_label(n, [], ['+ evaluate(ctx): ?Advice']), 800 + i * 185, 220, 175, 56, CLS))
    e = d.e(svc, rule, '', 'startArrow=diamondThin;startFill=0;startSize=14;endArrow=open;')
    d.label(e, '1..*', 0.8)
    d.e(svc, ip, '', 'endArrow=open;endSize=8;')
    REAL = 'endArrow=block;endFill=0;dashed=1;endSize=12;'
    for r in rs:
        d.e(r, rule, '', REAL)
    d.e(aic2, ip, '', REAL)
    d.e(nul, ip, '', REAL)
    d.save()


if __name__ == '__main__':
    use_case(); activity_focus(); activity_agent(); class_diagram(); seq_agent(); seq_recommend()
    er(); c4_context(); c4_containers(); solid()
