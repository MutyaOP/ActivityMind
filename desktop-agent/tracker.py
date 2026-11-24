"""Замеры состояния компьютера (только Windows, на ctypes — без внешних зависимостей)."""
from __future__ import annotations

import ctypes
import os
import sys
from ctypes import wintypes

IDLE_LABEL = "Простой"
NO_WINDOW_LABEL = "Рабочий стол"
HIDDEN_LABEL = "[скрыто]"


def _require_windows() -> None:
    if sys.platform != "win32":
        raise NotImplementedError("Сбор активности работает только в Windows. Для демо используйте --simulate-days.")


class _LASTINPUTINFO(ctypes.Structure):
    _fields_ = [("cbSize", wintypes.UINT), ("dwTime", wintypes.DWORD)]


def get_idle_seconds() -> float:
    """Сколько секунд не было ввода с мыши и клавиатуры."""
    _require_windows()
    user32, kernel32 = ctypes.windll.user32, ctypes.windll.kernel32
    info = _LASTINPUTINFO()
    info.cbSize = ctypes.sizeof(_LASTINPUTINFO)
    if not user32.GetLastInputInfo(ctypes.byref(info)):
        return 0.0
    millis = (kernel32.GetTickCount() - info.dwTime) & 0xFFFFFFFF
    return millis / 1000.0


def get_active_window() -> tuple[str, str] | None:
    """(имя процесса без .exe, заголовок окна) или None, если активного окна нет."""
    _require_windows()
    user32, kernel32 = ctypes.windll.user32, ctypes.windll.kernel32
    kernel32.OpenProcess.restype = wintypes.HANDLE
    kernel32.OpenProcess.argtypes = [wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
    kernel32.QueryFullProcessImageNameW.argtypes = [
        wintypes.HANDLE, wintypes.DWORD, wintypes.LPWSTR, ctypes.POINTER(wintypes.DWORD)]
    kernel32.CloseHandle.argtypes = [wintypes.HANDLE]

    hwnd = user32.GetForegroundWindow()
    if not hwnd:
        return None

    length = user32.GetWindowTextLengthW(hwnd)
    title_buf = ctypes.create_unicode_buffer(length + 1)
    user32.GetWindowTextW(hwnd, title_buf, length + 1)

    pid = wintypes.DWORD()
    user32.GetWindowThreadProcessId(hwnd, ctypes.byref(pid))

    name = ""
    handle = kernel32.OpenProcess(0x1000, False, pid.value)  # PROCESS_QUERY_LIMITED_INFORMATION
    if handle:
        size = wintypes.DWORD(1024)
        path_buf = ctypes.create_unicode_buffer(1024)
        if kernel32.QueryFullProcessImageNameW(handle, 0, path_buf, ctypes.byref(size)):
            name = os.path.basename(path_buf.value)
        kernel32.CloseHandle(handle)

    if name.lower().endswith(".exe"):
        name = name[:-4]
    return (name or "unknown", title_buf.value)


def sample(idle_threshold_sec: int, ignore_apps: list[str]) -> tuple[str, str, bool, float]:
    """
    Один замер: (приложение, заголовок, простой, секунд без ввода).
    Заголовок возвращается всегда — что из него уйдёт на сайт, решает агент (см. browser.py, agent.prepare).
    """
    idle_sec = get_idle_seconds()
    if idle_sec >= idle_threshold_sec:
        return (IDLE_LABEL, "", True, idle_sec)

    window = get_active_window()
    if window is None:
        return (NO_WINDOW_LABEL, "", False, idle_sec)

    app, title = window
    if app.lower() in ignore_apps:
        return (HIDDEN_LABEL, "", False, idle_sec)

    return (app, title, False, idle_sec)
