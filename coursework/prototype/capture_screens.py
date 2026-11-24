"""
Жұмыс істеп тұрған ActivityMind (http://127.0.0.1:8123) экрандарының скриншоттарын алады.
Chrome headless + DevTools Protocol (websocket-client) — қосымша драйвер керек емес.

    python capture_screens.py            # screens/*.png
Алдын ала: php artisan serve --port=8123 және демо-деректер (php artisan demo:seed ...).
"""
import base64
import json
import subprocess
import sys
import tempfile
import time
import urllib.request
from pathlib import Path

import websocket

BASE = 'http://127.0.0.1:8123'
CHROME = r'C:\Program Files\Google\Chrome\Application\chrome.exe'
OUT = Path(__file__).parent / 'screens'
OUT.mkdir(exist_ok=True)
PORT = 9333

USER = ('aigerim@demo.test', 'password')            # демо-пайдаланушы (тек жергілікті тест БД)
ADMIN = ('admin@activitymind.local', 'password')     # AdminSeeder әдепкі тест тіркелгісі

GUEST_PAGES = [('01_login', '/login'), ('02_register', '/register')]
USER_PAGES = [('03_dashboard', '/dashboard'), ('04_tasks', '/tasks'), ('05_timer', '/timer'),
              ('06_habits', '/habits'), ('07_analytics', '/analytics'), ('08_recommendations', '/recommendations'),
              ('09_devices', '/devices'), ('10_profile', '/profile')]
ADMIN_PAGES = [('11_admin_dashboard', '/admin'), ('12_admin_users', '/admin/users')]


class Cdp:
    def __init__(self, ws_url):
        self.ws = websocket.create_connection(ws_url, timeout=30)
        self.i = 0

    def call(self, method, **params):
        self.i += 1
        self.ws.send(json.dumps({'id': self.i, 'method': method, 'params': params}))
        while True:
            msg = json.loads(self.ws.recv())
            if msg.get('id') == self.i:
                if 'error' in msg:
                    raise RuntimeError(msg['error'])
                return msg.get('result', {})

    def js(self, expr):
        return self.call('Runtime.evaluate', expression=expr, returnByValue=True, awaitPromise=True)['result'].get('value')

    def goto(self, url):
        self.call('Page.navigate', url=url)
        for _ in range(100):
            time.sleep(0.15)
            if self.js('document.readyState') == 'complete':
                break
        time.sleep(1.2)  # Chart.js анимациясы

    def shot(self, name, width=1440):
        h = int(self.js('Math.max(document.body.scrollHeight, document.documentElement.scrollHeight)') or 900)
        h = max(900, min(h, 2600))
        self.call('Emulation.setDeviceMetricsOverride', width=width, height=h, deviceScaleFactor=1, mobile=False)
        time.sleep(0.6)
        data = self.call('Page.captureScreenshot', format='png', captureBeyondViewport=True)['data']
        (OUT / f'{name}.png').write_bytes(base64.b64decode(data))
        self.call('Emulation.setDeviceMetricsOverride', width=width, height=900, deviceScaleFactor=1, mobile=False)
        print('saved', name, h)

    def login(self, email, password):
        self.goto(BASE + '/login')
        self.js(f"""(() => {{
            document.querySelector('input[name=email]').value = {json.dumps(email)};
            document.querySelector('input[name=password]').value = {json.dumps(password)};
            document.querySelector('form').submit();
        }})()""")
        time.sleep(2.5)

    def logout(self):
        self.js("document.querySelector('form[action$=\"/logout\"]')?.submit()")
        time.sleep(2)


def main():
    profile = tempfile.mkdtemp(prefix='am_chrome_')
    proc = subprocess.Popen([CHROME, '--headless=new', f'--remote-debugging-port={PORT}', f'--user-data-dir={profile}',
                             '--no-first-run', '--hide-scrollbars', '--window-size=1440,900', 'about:blank'])
    try:
        for _ in range(50):
            try:
                tabs = json.load(urllib.request.urlopen(f'http://127.0.0.1:{PORT}/json'))
                page = next(t for t in tabs if t['type'] == 'page')
                break
            except Exception:
                time.sleep(0.2)
        c = Cdp(page['webSocketDebuggerUrl'])
        c.call('Page.enable')
        c.call('Emulation.setDeviceMetricsOverride', width=1440, height=900, deviceScaleFactor=1, mobile=False)

        for name, path in GUEST_PAGES:
            c.goto(BASE + path)
            c.shot(name)

        c.login(*USER)
        for name, path in USER_PAGES:
            c.goto(BASE + path)
            c.shot(name)
        # Мобильді көрініс (бейімделгіштік)
        c.call('Emulation.setDeviceMetricsOverride', width=390, height=844, deviceScaleFactor=2, mobile=True)
        c.goto(BASE + '/dashboard')
        data = c.call('Page.captureScreenshot', format='png')['data']
        (OUT / '13_mobile_dashboard.png').write_bytes(base64.b64decode(data))
        print('saved 13_mobile_dashboard')
        c.call('Emulation.setDeviceMetricsOverride', width=1440, height=900, deviceScaleFactor=1, mobile=False)
        c.logout()

        c.login(*ADMIN)
        for name, path in ADMIN_PAGES:
            c.goto(BASE + path)
            c.shot(name)

        c.goto((Path(__file__).parent / 'design-system.html').resolve().as_uri())
        c.shot('00_design_system', width=1280)
    finally:
        proc.terminate()


if __name__ == '__main__':
    sys.stdout.reconfigure(encoding='utf-8')
    main()
