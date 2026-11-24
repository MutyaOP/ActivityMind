"""
Значок в области уведомлений Windows: «Пауза / Продолжить» и «Выход».
Нужны пакеты pystray и Pillow (есть в requirements.txt). Без них агент работает как раньше — в консоли.
"""
from __future__ import annotations

import logging
import threading

from control import Control

log = logging.getLogger("agent")

ACTIVE_COLOR = (232, 163, 61)   # янтарный, как на сайте
PAUSED_COLOR = (110, 116, 128)  # серый


def _image(color):
    from PIL import Image, ImageDraw

    img = Image.new("RGBA", (64, 64), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    draw.ellipse((6, 6, 58, 58), fill=color)
    if color == PAUSED_COLOR:  # две полосы паузы
        draw.rectangle((22, 20, 29, 44), fill=(255, 255, 255))
        draw.rectangle((35, 20, 42, 44), fill=(255, 255, 255))
    return img


def start(control: Control) -> bool:
    """Запускает значок в отдельном потоке. False — если трей недоступен (нет пакетов или не Windows)."""
    try:
        import pystray
    except Exception as exc:  # ImportError или ошибка бэкенда
        log.info("Значок в трее недоступен (%s). Пауза: остановите агент Ctrl+C.", exc)
        return False

    def title() -> str:
        return "ActivityMind — на паузе" if control.paused else "ActivityMind — идёт учёт"

    def on_toggle(icon, _item):
        control.toggle()
        icon.icon = _image(PAUSED_COLOR if control.paused else ACTIVE_COLOR)
        icon.title = title()
        log.info("Мониторинг %s из трея.", "приостановлен" if control.paused else "продолжен")

    def on_exit(icon, _item):
        control.stop()
        icon.stop()

    menu = pystray.Menu(
        pystray.MenuItem(lambda _i: "Продолжить" if control.paused else "Пауза", on_toggle, default=True),
        pystray.MenuItem("Выход", on_exit),
    )
    icon = pystray.Icon("ActivityMind", _image(ACTIVE_COLOR), title(), menu)
    threading.Thread(target=icon.run, name="tray", daemon=True).start()
    return True
