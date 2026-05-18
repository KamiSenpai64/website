#!/usr/bin/env python3
"""
╔═══════════════════════════════════════════╗
║       Minecraft Server Setup TUI          ║
║   Vanilla server installer for Linux      ║
╚═══════════════════════════════════════════╝

Dependencies: python3 (stdlib only)
Usage: python3 mcserver-setup.py
"""

import curses
import curses.textpad
import json
import os
import stat
import subprocess
import sys
import urllib.request
import urllib.error
from pathlib import Path


# ─── Mojang manifest URL ───────────────────────────────────────────────────────
MANIFEST_URL = "https://launchermeta.mojang.com/mc/game/version_manifest_v2.json"

# ─── Color pair IDs ────────────────────────────────────────────────────────────
C_NORMAL   = 1
C_HEADER   = 2
C_SELECTED = 3
C_SUCCESS  = 4
C_ERROR    = 5
C_ACCENT   = 6
C_DIM      = 7
C_INPUT    = 8
C_BORDER   = 9


def init_colors():
    curses.start_color()
    curses.use_default_colors()
    # background = terminal default (-1)
    curses.init_pair(C_NORMAL,   curses.COLOR_WHITE,   -1)
    curses.init_pair(C_HEADER,   curses.COLOR_BLACK,   curses.COLOR_GREEN)
    curses.init_pair(C_SELECTED, curses.COLOR_BLACK,   curses.COLOR_CYAN)
    curses.init_pair(C_SUCCESS,  curses.COLOR_GREEN,   -1)
    curses.init_pair(C_ERROR,    curses.COLOR_RED,     -1)
    curses.init_pair(C_ACCENT,   curses.COLOR_CYAN,    -1)
    curses.init_pair(C_DIM,      curses.COLOR_BLACK+8, -1)  # bright black = gray
    curses.init_pair(C_INPUT,    curses.COLOR_YELLOW,  -1)
    curses.init_pair(C_BORDER,   curses.COLOR_GREEN,   -1)


# ─── Drawing helpers ───────────────────────────────────────────────────────────

def draw_box(win, title=""):
    h, w = win.getmaxyx()
    win.attron(curses.color_pair(C_BORDER))
    win.border()
    win.attroff(curses.color_pair(C_BORDER))
    if title:
        label = f"  {title}  "
        x = max(2, (w - len(label)) // 2)
        win.attron(curses.color_pair(C_ACCENT) | curses.A_BOLD)
        win.addstr(0, x, label)
        win.attroff(curses.color_pair(C_ACCENT) | curses.A_BOLD)


def safe_addstr(win, y, x, text, attr=0):
    h, w = win.getmaxyx()
    if y < 0 or y >= h:
        return
    max_len = w - x - 1
    if max_len <= 0:
        return
    win.addstr(y, x, text[:max_len], attr)


def center_text(win, y, text, attr=0):
    h, w = win.getmaxyx()
    x = max(0, (w - len(text)) // 2)
    safe_addstr(win, y, x, text, attr)


# ─── Splash screen ─────────────────────────────────────────────────────────────

def draw_splash(stdscr):
    stdscr.clear()
    h, w = stdscr.getmaxyx()

    art = [
        r"  ███╗   ███╗ ██████╗    ███████╗███████╗██████╗ ██╗   ██╗███████╗██████╗  ",
        r"  ████╗ ████║██╔════╝    ██╔════╝██╔════╝██╔══██╗██║   ██║██╔════╝██╔══██╗ ",
        r"  ██╔████╔██║██║         ███████╗█████╗  ██████╔╝██║   ██║█████╗  ██████╔╝ ",
        r"  ██║╚██╔╝██║██║         ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██╔══╝  ██╔══██╗ ",
        r"  ██║ ╚═╝ ██║╚██████╗    ███████║███████╗██║  ██║ ╚████╔╝ ███████╗██║  ██║ ",
        r"  ╚═╝     ╚═╝ ╚═════╝    ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚══════╝╚═╝  ╚═╝ ",
    ]
    subtitle = "[ Vanilla Server Installer for Linux ]"
    hint     = "Press any key to begin..."

    start_y = max(1, (h - len(art) - 4) // 2)

    for i, line in enumerate(art):
        if start_y + i >= h - 1:
            break
        x = max(0, (w - len(line)) // 2)
        safe_addstr(stdscr, start_y + i, x, line,
                    curses.color_pair(C_SUCCESS) | curses.A_BOLD)

    center_text(stdscr, start_y + len(art) + 1, subtitle,
                curses.color_pair(C_ACCENT))
    center_text(stdscr, start_y + len(art) + 3, hint,
                curses.color_pair(C_DIM) | curses.A_ITALIC)

    stdscr.refresh()
    stdscr.getch()


# ─── Fetch version manifest ────────────────────────────────────────────────────

def fetch_versions(stdscr):
    h, w = stdscr.getmaxyx()
    stdscr.clear()
    center_text(stdscr, h // 2 - 1, "Fetching version manifest from Mojang…",
                curses.color_pair(C_ACCENT) | curses.A_BOLD)
    center_text(stdscr, h // 2 + 1, "Please wait…", curses.color_pair(C_DIM))
    stdscr.refresh()

    try:
        with urllib.request.urlopen(MANIFEST_URL, timeout=15) as resp:
            data = json.loads(resp.read().decode())
    except Exception as e:
        return None, str(e)

    versions = []
    for v in data["versions"]:
        if v["type"] in ("release", "snapshot"):
            versions.append({
                "id":      v["id"],
                "type":    v["type"],
                "url":     v["url"],
                "release": v.get("releaseTime", ""),
            })
    return versions, None


# ─── Version type filter menu ──────────────────────────────────────────────────

def select_version_type(stdscr):
    options = ["Release versions only", "Snapshots only", "All versions"]
    idx = 0
    h, w = stdscr.getmaxyx()
    bh, bw = 12, 48
    by, bx = (h - bh) // 2, (w - bw) // 2
    win = curses.newwin(bh, bw, by, bx)
    win.keypad(True)

    while True:
        win.clear()
        draw_box(win, "Version Filter")
        safe_addstr(win, 2, 3, "Select which versions to show:",
                    curses.color_pair(C_ACCENT))
        for i, opt in enumerate(options):
            attr = curses.color_pair(C_SELECTED) | curses.A_BOLD if i == idx \
                   else curses.color_pair(C_NORMAL)
            marker = "▶ " if i == idx else "  "
            safe_addstr(win, 4 + i, 4, f"{marker}{opt}", attr)
        safe_addstr(win, bh - 2, 3,
                    "↑↓ navigate  Enter select  Q quit",
                    curses.color_pair(C_DIM))
        win.refresh()

        key = win.getch()
        if key in (curses.KEY_UP, ord('k')):
            idx = (idx - 1) % len(options)
        elif key in (curses.KEY_DOWN, ord('j')):
            idx = (idx + 1) % len(options)
        elif key in (curses.KEY_ENTER, 10, 13):
            return ["release", "snapshot", None][idx]
        elif key in (ord('q'), ord('Q'), 27):
            return "EXIT"


# ─── Version picker ─────────────────────────────────────────────────────────────

def pick_version(stdscr, versions):
    type_filter = select_version_type(stdscr)
    if type_filter == "EXIT":
        return None

    if type_filter is not None:
        filtered = [v for v in versions if v["type"] == type_filter]
    else:
        filtered = versions

    if not filtered:
        return None

    idx      = 0
    scroll   = 0
    query    = ""
    h, w     = stdscr.getmaxyx()
    list_h   = h - 10
    bh, bw   = h - 4, min(w - 4, 80)
    by, bx   = 2, (w - bw) // 2
    win      = curses.newwin(bh, bw, by, bx)
    win.keypad(True)
    curses.curs_set(0)

    while True:
        if query:
            display = [v for v in filtered
                       if query.lower() in v["id"].lower()]
        else:
            display = filtered

        win.clear()
        draw_box(win, "Select Minecraft Version")

        # Search bar
        safe_addstr(win, 1, 2, "Search: ", curses.color_pair(C_DIM))
        safe_addstr(win, 1, 10, query + " ",
                    curses.color_pair(C_INPUT) | curses.A_BOLD)
        safe_addstr(win, 1, bw - 20,
                    f"{len(display)} versions",
                    curses.color_pair(C_DIM))

        # Column header
        safe_addstr(win, 2, 2, f"{'Version':<16} {'Type':<12} {'Released':<12}",
                    curses.color_pair(C_ACCENT) | curses.A_UNDERLINE)

        visible = list_h - 2
        if idx < scroll:
            scroll = idx
        elif idx >= scroll + visible:
            scroll = idx - visible + 1

        for i, v in enumerate(display[scroll:scroll + visible]):
            real_i = scroll + i
            y = 3 + i
            if y >= bh - 3:
                break
            rel_date = v["release"][:10] if v["release"] else "unknown"
            line = f"  {v['id']:<16} {v['type']:<12} {rel_date:<12}"
            attr = curses.color_pair(C_SELECTED) | curses.A_BOLD \
                   if real_i == idx else curses.color_pair(C_NORMAL)
            safe_addstr(win, y, 1, line, attr)

        if not display:
            center_text(win, bh // 2, "No versions match your search.",
                        curses.color_pair(C_ERROR))

        safe_addstr(win, bh - 2, 2,
                    "↑↓ navigate  Enter select  Type to search  Backspace clear  Q quit",
                    curses.color_pair(C_DIM))
        win.refresh()

        key = win.getch()
        if key in (curses.KEY_UP, ord('k')):
            if display:
                idx = (idx - 1) % len(display)
        elif key in (curses.KEY_DOWN, ord('j')):
            if display:
                idx = (idx + 1) % len(display)
        elif key in (curses.KEY_ENTER, 10, 13):
            if display:
                return display[idx]
        elif key in (curses.KEY_BACKSPACE, 127, 8):
            query = query[:-1]
            idx   = 0
            scroll = 0
        elif key in (ord('q'), ord('Q'), 27):
            return None
        elif 32 <= key <= 126:
            query += chr(key)
            idx    = 0
            scroll = 0


# ─── Port input ────────────────────────────────────────────────────────────────

def input_port(stdscr):
    h, w    = stdscr.getmaxyx()
    bh, bw  = 10, 50
    by, bx  = (h - bh) // 2, (w - bw) // 2
    win     = curses.newwin(bh, bw, by, bx)
    win.keypad(True)
    port_str = "25565"
    curses.curs_set(1)

    while True:
        win.clear()
        draw_box(win, "Server Port")
        safe_addstr(win, 2, 3,
                    "Default Minecraft port is 25565.",
                    curses.color_pair(C_DIM))
        safe_addstr(win, 3, 3,
                    "Enter a port (1024–65535):",
                    curses.color_pair(C_ACCENT))
        safe_addstr(win, 4, 3, f"Port: {port_str}_",
                    curses.color_pair(C_INPUT) | curses.A_BOLD)
        safe_addstr(win, bh - 2, 3,
                    "Backspace edit  Enter confirm  Q quit",
                    curses.color_pair(C_DIM))
        win.refresh()

        key = win.getch()
        if key in (curses.KEY_ENTER, 10, 13):
            try:
                p = int(port_str)
                if 1024 <= p <= 65535:
                    curses.curs_set(0)
                    return p
                else:
                    port_str = "25565"
            except ValueError:
                port_str = "25565"
        elif key in (curses.KEY_BACKSPACE, 127, 8):
            port_str = port_str[:-1]
        elif ord('0') <= key <= ord('9') and len(port_str) < 5:
            port_str += chr(key)
        elif key in (ord('q'), ord('Q'), 27):
            curses.curs_set(0)
            return None


# ─── RAM input ─────────────────────────────────────────────────────────────────

def input_ram(stdscr):
    h, w   = stdscr.getmaxyx()
    bh, bw = 12, 54
    by, bx = (h - bh) // 2, (w - bw) // 2
    win    = curses.newwin(bh, bw, by, bx)
    win.keypad(True)
    options = ["1G", "2G", "4G", "6G", "8G", "Custom"]
    idx    = 1
    curses.curs_set(0)

    custom_mode = False
    custom_val  = ""

    while True:
        win.clear()
        draw_box(win, "Allocated RAM")
        safe_addstr(win, 1, 3, "How much RAM for the server?",
                    curses.color_pair(C_ACCENT))

        if custom_mode:
            safe_addstr(win, 3, 3,
                        "Enter custom RAM (e.g. 3G, 512M):",
                        curses.color_pair(C_DIM))
            safe_addstr(win, 4, 3, f"RAM: {custom_val}_",
                        curses.color_pair(C_INPUT) | curses.A_BOLD)
            safe_addstr(win, bh - 2, 3,
                        "Backspace edit  Enter confirm",
                        curses.color_pair(C_DIM))
            win.refresh()
            curses.curs_set(1)
            key = win.getch()
            if key in (curses.KEY_ENTER, 10, 13) and custom_val:
                curses.curs_set(0)
                return custom_val
            elif key in (curses.KEY_BACKSPACE, 127, 8):
                custom_val = custom_val[:-1]
            elif 32 <= key <= 126 and len(custom_val) < 8:
                custom_val += chr(key)
            elif key == 27:
                custom_mode = False
                curses.curs_set(0)
        else:
            for i, opt in enumerate(options):
                attr = curses.color_pair(C_SELECTED) | curses.A_BOLD \
                       if i == idx else curses.color_pair(C_NORMAL)
                marker = "▶ " if i == idx else "  "
                safe_addstr(win, 3 + i, 4, f"{marker}{opt}", attr)
            safe_addstr(win, bh - 2, 3,
                        "↑↓ navigate  Enter select  Q quit",
                        curses.color_pair(C_DIM))
            win.refresh()
            key = win.getch()
            if key in (curses.KEY_UP, ord('k')):
                idx = (idx - 1) % len(options)
            elif key in (curses.KEY_DOWN, ord('j')):
                idx = (idx + 1) % len(options)
            elif key in (curses.KEY_ENTER, 10, 13):
                if options[idx] == "Custom":
                    custom_mode = True
                else:
                    return options[idx]
            elif key in (ord('q'), ord('Q'), 27):
                return None


# ─── Confirm summary ───────────────────────────────────────────────────────────

def confirm_setup(stdscr, version_id, port, ram, install_dir, use_systemd):
    h, w   = stdscr.getmaxyx()
    bh, bw = 16, 60
    by, bx = (h - bh) // 2, (w - bw) // 2
    win    = curses.newwin(bh, bw, by, bx)
    win.keypad(True)

    rows = [
        ("Version",         version_id),
        ("Port",            str(port)),
        ("RAM",             ram),
        ("Install dir",     str(install_dir)),
        ("24/7 service",    "systemd (recommended)" if use_systemd else "Scripts only"),
    ]
    idx = 0
    options = ["Install", "Cancel"]

    while True:
        win.clear()
        draw_box(win, "Confirm Setup")
        safe_addstr(win, 1, 3, "Review your configuration:",
                    curses.color_pair(C_ACCENT) | curses.A_BOLD)
        for i, (k, v) in enumerate(rows):
            safe_addstr(win, 3 + i, 4,
                        f"{k:<16}: ", curses.color_pair(C_DIM))
            safe_addstr(win, 3 + i, 4 + 18, v,
                        curses.color_pair(C_INPUT) | curses.A_BOLD)

        for i, opt in enumerate(options):
            attr = curses.color_pair(C_SELECTED) | curses.A_BOLD \
                   if i == idx else curses.color_pair(C_NORMAL)
            marker = "▶ " if i == idx else "  "
            safe_addstr(win, bh - 4 + i, 4, f"{marker}{opt}", attr)

        safe_addstr(win, bh - 2, 3,
                    "↑↓ navigate  Enter select",
                    curses.color_pair(C_DIM))
        win.refresh()

        key = win.getch()
        if key in (curses.KEY_UP, curses.KEY_DOWN, ord('k'), ord('j')):
            idx = (idx + 1) % 2
        elif key in (curses.KEY_ENTER, 10, 13):
            return idx == 0
        elif key in (ord('q'), ord('Q'), 27):
            return False


# ─── Ask systemd ───────────────────────────────────────────────────────────────

def ask_systemd(stdscr):
    h, w   = stdscr.getmaxyx()
    bh, bw = 13, 60
    by, bx = (h - bh) // 2, (w - bw) // 2
    win    = curses.newwin(bh, bw, by, bx)
    win.keypad(True)
    idx    = 0
    options = ["Yes – install as systemd service (runs 24/7)",
               "No – start/stop scripts only"]

    while True:
        win.clear()
        draw_box(win, "24/7 Server – Systemd")
        safe_addstr(win, 1, 3,
                    "Install as a systemd service for 24/7 uptime?",
                    curses.color_pair(C_ACCENT))
        safe_addstr(win, 2, 3,
                    "(Requires sudo – auto-starts on boot)",
                    curses.color_pair(C_DIM))
        for i, opt in enumerate(options):
            attr = curses.color_pair(C_SELECTED) | curses.A_BOLD \
                   if i == idx else curses.color_pair(C_NORMAL)
            marker = "▶ " if i == idx else "  "
            safe_addstr(win, 4 + i * 2, 4, f"{marker}{opt}", attr)

        safe_addstr(win, bh - 2, 3,
                    "↑↓ navigate  Enter select",
                    curses.color_pair(C_DIM))
        win.refresh()

        key = win.getch()
        if key in (curses.KEY_UP, ord('k')):
            idx = (idx - 1) % 2
        elif key in (curses.KEY_DOWN, ord('j')):
            idx = (idx + 1) % 2
        elif key in (curses.KEY_ENTER, 10, 13):
            return idx == 0
        elif key in (ord('q'), ord('Q'), 27):
            return False


# ─── Progress screen ───────────────────────────────────────────────────────────

def progress_screen(stdscr, steps):
    """
    steps: list of (label, callable) where callable(update_fn) performs the work.
    update_fn(msg) appends a log line.
    """
    h, w   = stdscr.getmaxyx()
    bh     = min(h - 4, max(20, len(steps) * 3 + 8))
    bw     = min(w - 4, 76)
    by, bx = (h - bh) // 2, (w - bw) // 2
    win    = curses.newwin(bh, bw, by, bx)
    win.keypad(True)
    logs   = []

    def redraw(current_step, total, current_label, status="running"):
        win.clear()
        draw_box(win, "Installing…")
        bar_w   = bw - 10
        filled  = int(bar_w * current_step / max(total, 1))
        bar     = "█" * filled + "░" * (bar_w - filled)
        pct     = int(100 * current_step / max(total, 1))
        safe_addstr(win, 1, 3,
                    f"[{bar}] {pct:3d}%",
                    curses.color_pair(C_SUCCESS))
        safe_addstr(win, 2, 3, f"Step {current_step}/{total}: {current_label}",
                    curses.color_pair(C_ACCENT) | curses.A_BOLD)
        log_start = 4
        log_lines = bh - log_start - 2
        visible_logs = logs[-log_lines:]
        for i, line in enumerate(visible_logs):
            attr = curses.color_pair(C_DIM)
            safe_addstr(win, log_start + i, 3, line[:bw - 6], attr)
        win.refresh()

    errors = []
    for step_i, (label, fn) in enumerate(steps):
        def update(msg, _i=step_i, _lbl=label):
            logs.append(f"  {msg}")
            redraw(_i + 1, len(steps), _lbl)

        redraw(step_i, len(steps), label)
        try:
            fn(update)
        except Exception as e:
            errors.append(f"Step '{label}' failed: {e}")
            logs.append(f"ERROR: {e}")
            redraw(step_i + 1, len(steps), label, "error")
            break

    redraw(len(steps), len(steps), "Done!")
    return errors


# ─── Fetch server jar URL from version manifest ────────────────────────────────

def get_server_url(version_meta_url):
    with urllib.request.urlopen(version_meta_url, timeout=15) as resp:
        meta = json.loads(resp.read().decode())
    downloads = meta.get("downloads", {})
    server = downloads.get("server")
    if not server:
        raise RuntimeError("No server download available for this version.")
    return server["url"], server.get("size", 0)


# ─── Download with progress ─────────────────────────────────────────────────────

def download_file(url, dest_path, update_fn, total_size=0):
    block = 65536
    downloaded = 0
    with urllib.request.urlopen(url, timeout=60) as resp:
        if total_size == 0:
            cl = resp.headers.get("Content-Length")
            total_size = int(cl) if cl else 0
        with open(dest_path, "wb") as f:
            while True:
                chunk = resp.read(block)
                if not chunk:
                    break
                f.write(chunk)
                downloaded += len(chunk)
                if total_size:
                    pct = downloaded * 100 // total_size
                    mb  = downloaded / 1_048_576
                    update_fn(f"Downloaded {mb:.1f} MB ({pct}%)")
                else:
                    mb = downloaded / 1_048_576
                    update_fn(f"Downloaded {mb:.1f} MB")


# ─── Write start script ────────────────────────────────────────────────────────

def write_start_script(install_dir, jar_name, ram):
    path   = install_dir / "start.sh"
    lines = [
        "#!/usr/bin/env bash",
        "# Minecraft Server - start script (auto-generated)",
        "# Keeps the server running; CTRL+C or stop.sh will exit cleanly.",
        'cd "$(dirname "$0")"',
        "",
        "# ── Check for Java ────────────────────────────────────────────",
        'if ! command -v java &>/dev/null; then',
        '    echo "ERROR: Java is not installed or not in PATH."',
        '    echo ""',
        '    echo "Install Java (17 or 21 recommended):"',
        '    echo "  Ubuntu/Debian : sudo apt update && sudo apt install openjdk-21-jre-headless"',
        '    echo "  Fedora/RHEL   : sudo dnf install java-21-openjdk-headless"',
        '    echo "  Arch          : sudo pacman -S jre21-openjdk-headless"',
        '    echo ""',
        '    echo "Then run this script again."',
        '    exit 1',
        'fi',
        "",
        'echo "Using Java: $(java -version 2>&1 | head -1)"',
        'echo "Starting server... (CTRL+C to stop)"',
        "",
        "# ── Trap SIGINT/SIGTERM – kill child Java process and exit ─────",
        "JAVA_PID=",
        "stop_server() {",
        '    echo ""',
        '    echo "Stopping server..."',
        '    if [ -n "$JAVA_PID" ]; then',
        '        kill "$JAVA_PID" 2>/dev/null',
        '        wait "$JAVA_PID" 2>/dev/null',
        '    fi',
        '    exit 0',
        "}",
        "trap stop_server SIGINT SIGTERM",
        "",
        "# ── Restart loop ──────────────────────────────────────────────",
        "while true; do",
        f'    java -Xms{ram} -Xmx{ram} -jar {jar_name} --nogui &',
        "    JAVA_PID=$!",
        '    wait "$JAVA_PID"',
        "    EXIT_CODE=$?",
        '    # If killed by trap, exit code will be non-zero from signal — exit cleanly',
        '    if [ $EXIT_CODE -eq 0 ] || [ $EXIT_CODE -eq 143 ]; then',
        '        # 143 = SIGTERM; treat as intentional stop',
        '        break',
        '    fi',
        '    echo "Server restarting..."',
        '    echo "Press CTRL + C to stop."',
        "done",
    ]
    path.write_text("\n".join(lines) + "\n")
    path.chmod(path.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)
    return path


def write_stop_script(install_dir):
    path  = install_dir / "stop.sh"
    lines = [
        "#!/usr/bin/env bash",
        "# Minecraft Server - stop script (auto-generated)",
        "# Sends SIGTERM to the start.sh process group, which triggers its trap.",
        "",
        "# Find the start.sh bash process for THIS server directory",
        f'DIR="{install_dir}"',
        'PID=$(pgrep -f "bash.*start.sh" | while read pid; do',
        '    if ls -la /proc/$pid/cwd 2>/dev/null | grep -q "$DIR"; then',
        '        echo $pid; break',
        '    fi',
        'done)',
        "",
        'if [ -z "$PID" ]; then',
        '    # Fallback: kill by java process',
        '    JAVA_PID=$(pgrep -f "java.*' + install_dir.name + '" | head -1)',
        '    if [ -z "$JAVA_PID" ]; then',
        '        echo "No running server found for $DIR"',
        '        exit 1',
        '    fi',
        '    kill -SIGTERM "$JAVA_PID"',
        '    echo "Sent SIGTERM to Java process $JAVA_PID"',
        'else',
        '    kill -SIGTERM "$PID"',
        '    echo "Sent SIGTERM to start.sh (PID $PID) — server will stop after current shutdown."',
        'fi',
    ]
    path.write_text("\n".join(lines) + "\n")
    path.chmod(path.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)
    return path


# ─── Final summary screen ──────────────────────────────────────────────────────

def show_summary(stdscr, install_dir, port, ram, version_id, errors):
    h, w = stdscr.getmaxyx()
    stdscr.clear()

    lines = []
    if errors:
        lines.append(("  ✗  Installation had errors:", C_ERROR, True))
        for e in errors:
            lines.append((f"     {e}", C_ERROR, False))
    else:
        lines.append(("  ✔  Minecraft server installed successfully!", C_SUCCESS, True))

    lines.append(("", C_NORMAL, False))
    lines.append((f"  Directory : {install_dir}", C_ACCENT, False))
    lines.append((f"  Version   : {version_id}", C_ACCENT, False))
    lines.append((f"  Port      : {port}", C_ACCENT, False))
    lines.append((f"  RAM       : {ram}", C_ACCENT, False))
    lines.append(("", C_NORMAL, False))
    lines.append(("  Scripts:", C_NORMAL, True))
    lines.append((f"    {install_dir}/start.sh   ← runs server, auto-restarts on crash", C_DIM, False))
    lines.append((f"    {install_dir}/stop.sh    ← kills server + stops restart loop", C_DIM, False))
    lines.append(("", C_NORMAL, False))
    lines.append(("  EULA auto-accepted (eula=true).", C_SUCCESS, False))
    lines.append(("", C_NORMAL, False))
    lines.append(("  Press any key to exit.", C_DIM, False))

    start_y = max(1, (h - len(lines)) // 2)
    for i, (text, color, bold) in enumerate(lines):
        y = start_y + i
        if y >= h - 1:
            break
        attr = curses.color_pair(color)
        if bold:
            attr |= curses.A_BOLD
        safe_addstr(stdscr, y, 0, text, attr)

    stdscr.refresh()
    stdscr.getch()


# ─── Main TUI flow ─────────────────────────────────────────────────────────────

def main(stdscr):
    curses.curs_set(0)
    stdscr.keypad(True)
    init_colors()

    draw_splash(stdscr)

    # 1. Fetch versions
    versions, err = fetch_versions(stdscr)
    if err or not versions:
        stdscr.clear()
        center_text(stdscr, stdscr.getmaxyx()[0] // 2,
                    f"Failed to fetch versions: {err}",
                    curses.color_pair(C_ERROR) | curses.A_BOLD)
        stdscr.refresh()
        stdscr.getch()
        return

    # 2. Pick version
    chosen = pick_version(stdscr, versions)
    if not chosen:
        return

    # 3. Pick port
    port = input_port(stdscr)
    if port is None:
        return

    # 4. Pick RAM
    ram = input_ram(stdscr)
    if ram is None:
        return

    # 5. Determine install dir
    version_id  = chosen["id"]
    install_dir = Path.home() / f"MinecraftServer{version_id}"
    jar_name    = f"server-{version_id}.jar"

    # 6. Confirm
    ok = confirm_setup(stdscr, version_id, port, ram, install_dir, False)
    if not ok:
        return

    # 8. Build steps
    server_url_holder = [None]
    server_size_holder = [0]

    def step_fetch_url(update):
        update("Resolving server JAR URL…")
        url, size = get_server_url(chosen["url"])
        server_url_holder[0] = url
        server_size_holder[0] = size
        update(f"URL resolved. Size: {size / 1_048_576:.1f} MB")

    def step_create_dir(update):
        install_dir.mkdir(parents=True, exist_ok=True)
        update(f"Created: {install_dir}")

    def step_download(update):
        dest = install_dir / jar_name
        update(f"Downloading {jar_name}…")
        download_file(server_url_holder[0], dest,
                      update, server_size_holder[0])
        update("Download complete.")

    def step_eula(update):
        eula_path = install_dir / "eula.txt"
        eula_path.write_text(
            "# Minecraft EULA: https://aka.ms/MinecraftEULA\n"
            "# Auto-accepted by mcserver-setup.\n"
            "eula=true\n"
        )
        update("eula.txt written (eula=true, auto-accepted)")

    def step_server_props(update):
        props_path = install_dir / "server.properties"
        if not props_path.exists():
            props_path.write_text(
                f"# Minecraft server properties (auto-generated)\n"
                f"server-port={port}\n"
                f"online-mode=true\n"
                f"max-players=20\n"
                f"motd=A Minecraft Server\n"
            )
            update(f"server.properties written (port={port})")
        else:
            update("server.properties already exists, skipping.")

    def step_scripts(update):
        write_start_script(install_dir, jar_name, ram)
        update("start.sh written")
        write_stop_script(install_dir)
        update("stop.sh written")

    steps = [
        ("Resolving download URL", step_fetch_url),
        ("Creating server directory", step_create_dir),
        ("Downloading server JAR", step_download),
        ("Writing EULA file", step_eula),
        ("Writing server.properties", step_server_props),
        ("Writing start/stop scripts", step_scripts),
    ]

    errors = progress_screen(stdscr, steps)

    # 8. Summary
    show_summary(stdscr, install_dir, port, ram, version_id, errors)


# ─── Entry point ───────────────────────────────────────────────────────────────

if __name__ == "__main__":
    try:
        curses.wrapper(main)
    except KeyboardInterrupt:
        print("\nAborted.")
        sys.exit(0)
