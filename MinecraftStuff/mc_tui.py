#!/usr/bin/env python3
"""
Minecraft Server TUI - A terminal user interface for managing Minecraft servers
"""

import asyncio
import re
import os
import yaml
from pathlib import Path
from datetime import datetime
from typing import Optional, List
from dataclasses import dataclass

try:
    from textual.app import App, ComposeResult
    from textual.containers import Container, Horizontal, Vertical
    from textual.widgets import (
        Header, Footer, Static, Input, ListView, ListItem, Label,
        Log, DataTable, Button, RichLog
    )
    from textual.reactive import reactive
    from textual import events
    from textual.binding import Binding
except ImportError:
    print("Error: textual library not found. Install with: pip install textual")
    exit(1)

try:
    from mcrcon import MCRcon
except ImportError:
    print("Error: mcrcon library not found. Install with: pip install mcrcon")
    exit(1)


@dataclass
class ServerInfo:
    """Data class for server information"""
    players_online: int = 0
    max_players: int = 0
    players: List[str] = None
    tps: float = 20.0
    motd: str = ""
    version: str = ""

    def __post_init__(self):
        if self.players is None:
            self.players = []


class RCONManager:
    """Manages RCON connection to Minecraft server"""
    
    def __init__(self, host: str, port: int, password: str):
        self.host = host
        self.port = port
        self.password = password
        self.connected = False
    
    async def connect(self) -> bool:
        """Establish RCON connection"""
        try:
            self.rcon = MCRcon(self.host, self.password, self.port)
            self.rcon.connect()
            self.connected = True
            return True
        except Exception as e:
            print(f"RCON connection failed: {e}")
            self.connected = False
            return False
    
    async def send_command(self, command: str) -> str:
        """Send command to server via RCON"""
        if not self.connected:
            return "Not connected to server"
        
        try:
            response = self.rcon.command(command)
            return response
        except Exception as e:
            return f"Command failed: {e}"
    
    def disconnect(self):
        """Close RCON connection"""
        if self.connected:
            try:
                self.rcon.disconnect()
                self.connected = False
            except:
                pass


class PlayerPanel(Vertical):
    """Panel showing player information"""
    
    def __init__(self, rcon_manager: RCONManager):
        super().__init__()
        self.rcon_manager = rcon_manager
        self.server_info = ServerInfo()
    
    def compose(self) -> ComposeResult:
        yield Label("Players Online", classes="panel-title")
        yield Label("0 / 0", id="player-count", classes="stat-value")
        yield ListView(id="player-list")
    
    async def update_players(self):
        """Update player list from server"""
        try:
            response = await self.rcon_manager.send_command("list")
            # Parse response like "There are 5 of a max of 20 players online: player1, player2, ..."
            match = re.search(r"There are (\d+) of a max of (\d+) players online:?(.*)", response)
            if match:
                online = int(match.group(1))
                max_players = int(match.group(2))
                players_str = match.group(3).strip()
                
                self.server_info.players_online = online
                self.server_info.max_players = max_players
                
                if players_str:
                    self.server_info.players = [p.strip() for p in players_str.split(",")]
                else:
                    self.server_info.players = []
                
                # Update UI
                self.query_one("#player-count", Label).update(f"{online} / {max_players}")
                
                player_list = self.query_one("#player-list", ListView)
                player_list.clear()
                
                for player in self.server_info.players:
                    player_list.append(ListItem(Label(player)))
        except Exception as e:
            print(f"Error updating players: {e}")


class TPSPanel(Vertical):
    """Panel showing TPS (Ticks Per Second)"""
    
    def __init__(self, rcon_manager: RCONManager):
        super().__init__()
        self.rcon_manager = rcon_manager
        self.tps = 20.0
    
    def compose(self) -> ComposeResult:
        yield Label("TPS", classes="panel-title")
        yield Label("20.0", id="tps-value", classes="stat-value")
        yield Label("Server Performance", id="tps-status", classes="stat-label")
    
    async def update_tps(self):
        """Update TPS from server"""
        try:
            # Use /tps command if available (Forge/Paper servers)
            response = await self.rcon_manager.send_command("tps")
            
            # Parse TPS from response (format varies by server type)
            tps_match = re.search(r"(\d+\.?\d*)", response)
            if tps_match:
                self.tps = float(tps_match.group(1))
            else:
                # Fallback: try to get from debug
                response = await self.rcon_manager.send_command("debug report")
                # This is server-specific, might not work on all servers
                self.tps = 20.0  # Default if can't determine
            
            # Update UI
            tps_label = self.query_one("#tps-value", Label)
            tps_label.update(f"{self.tps:.1f}")
            
            status_label = self.query_one("#tps-status", Label)
            if self.tps >= 19.5:
                status_label.update("Excellent")
                tps_label.styles.color = "green"
            elif self.tps >= 18.0:
                status_label.update("Good")
                tps_label.styles.color = "yellow"
            elif self.tps >= 15.0:
                status_label.update("Fair")
                tps_label.styles.color = "orange"
            else:
                status_label.update("Poor")
                tps_label.styles.color = "red"
                
        except Exception as e:
            print(f"Error updating TPS: {e}")


class LogPanel(Vertical):
    """Panel showing server logs"""
    
    def __init__(self, log_path: str):
        super().__init__()
        self.log_path = log_path
        self.last_position = 0
    
    def compose(self) -> ComposeResult:
        yield Label("Server Logs", classes="panel-title")
        yield RichLog(id="server-log", auto_scroll=True)
    
    async def update_logs(self):
        """Update logs from server log file"""
        if not self.log_path or not os.path.exists(self.log_path):
            return
        
        try:
            with open(self.log_path, 'r') as f:
                f.seek(self.last_position)
                new_lines = f.readlines()
                self.last_position = f.tell()
                
                log_widget = self.query_one("#server-log", RichLog)
                for line in new_lines:
                    log_widget.write(line.strip())
        except Exception as e:
            print(f"Error reading logs: {e}")


class CommandInput(Input):
    """Command input with auto-completion"""
    
    def __init__(self, rcon_manager: RCONManager, log_panel: LogPanel):
        super().__init__(placeholder="Enter command (Tab for autocomplete)...")
        self.rcon_manager = rcon_manager
        self.log_panel = log_panel
        self.suggestions = self._get_base_commands()
        self.current_suggestion_index = 0
    
    def _get_base_commands(self) -> List[str]:
        """Get base Minecraft commands for auto-completion"""
        return [
            "/op", "/deop", "/ban", "/pardon", "/ban-ip", "/pardon-ip",
            "/kick", "/whitelist", "/gamemode", "/give", "/tp", "/teleport",
            "/kill", "/time", "/weather", "/difficulty", "/defaultgamemode",
            "/seed", "/setworldspawn", "/spawnpoint", "/gamerule",
            "/clear", "/effect", "/enchant", "/xp", "/experience",
            "/title", "/subtitle", "/tellraw", "/msg", "/tell", "/say",
            "/me", "/team", "/scoreboard", "/data", "/execute",
            "/help", "/list", "/stop", "/save-all", "/save-on", "/save-off",
            "/tpall", "/reload"
        ]
    
    def _get_completions(self, prefix: str) -> List[str]:
        """Get command completions based on prefix"""
        if not prefix.startswith("/"):
            return []
        
        completions = []
        for cmd in self.suggestions:
            if cmd.startswith(prefix):
                completions.append(cmd)
        
        return completions
    
    async def action_submit(self) -> None:
        """Handle command submission"""
        command = self.value
        if command:
            # Send command via RCON
            response = await self.rcon_manager.send_command(command)
            
            # Log the command and response
            log_widget = self.log_panel.query_one("#server-log", RichLog)
            log_widget.write(f"[COMMAND] {command}")
            log_widget.write(f"[RESPONSE] {response}")
            
            # Clear input
            self.value = ""
    
    def on_key(self, event: events.Key) -> None:
        """Handle key events for auto-completion"""
        if event.key == "tab":
            # Auto-complete
            completions = self._get_completions(self.value)
            if completions:
                if len(completions) == 1:
                    self.value = completions[0] + " "
                else:
                    # Show all completions
                    log_widget = self.log_panel.query_one("#server-log", RichLog)
                    log_widget.write(f"[COMPLETIONS] {', '.join(completions)}")
        else:
            super().on_key(event)


class MinecraftTUI(App):
    """Main TUI application for Minecraft server management"""
    
    CSS = """
    Screen {
        background: #1a1a2e;
    }
    
    .panel-title {
        text-align: center;
        text-style: bold;
        color: #ffd700;
        padding: 1;
    }
    
    .stat-value {
        text-align: center;
        text-style: bold;
        font-size: 24;
        padding: 1;
    }
    
    .stat-label {
        text-align: center;
        color: #888;
        padding: 1;
    }
    
    #main-container {
        height: 1fr;
    }
    
    #top-panel {
        height: 3fr;
    }
    
    #bottom-panel {
        height: 2fr;
    }
    
    #left-panel {
        width: 30;
    }
    
    #right-panel {
        width: 1fr;
    }
    
    #command-panel {
        height: 3;
    }
    
    #player-list {
        height: 1fr;
    }
    
    #server-log {
        height: 1fr;
        background: #0f0f1a;
    }
    
    Input {
        margin: 1;
    }
    """
    
    BINDINGS = [
        Binding("q", "quit", "Quit"),
        Binding("ctrl+c", "quit", "Quit"),
    ]
    
    def __init__(self):
        super().__init__()
        self.config = self._load_config()
        self.rcon_manager = RCONManager(
            self.config['rcon']['host'],
            self.config['rcon']['port'],
            self.config['rcon']['password']
        )
    
    def _load_config(self) -> dict:
        """Load configuration from YAML file"""
        config_path = Path(__file__).parent / "config.yaml"
        if config_path.exists():
            with open(config_path, 'r') as f:
                return yaml.safe_load(f)
        else:
            # Return default config
            return {
                'rcon': {'host': 'localhost', 'port': 25575, 'password': ''},
                'log_path': '',
                'update_intervals': {'players': 2, 'tps': 1, 'logs': 0.5}
            }
    
    def compose(self) -> ComposeResult:
        """Compose the TUI layout"""
        yield Header()
        
        with Container(id="main-container"):
            with Horizontal(id="top-panel"):
                with Vertical(id="left-panel"):
                    yield PlayerPanel(self.rcon_manager)
                with Vertical(id="right-panel"):
                    yield TPSPanel(self.rcon_manager)
            
            with Vertical(id="bottom-panel"):
                yield LogPanel(self.config.get('log_path', ''))
        
        with Container(id="command-panel"):
            yield CommandInput(self.rcon_manager, self.query_one(LogPanel))
        
        yield Footer()
    
    async def on_mount(self) -> None:
        """Initialize the application"""
        # Connect to RCON
        connected = await self.rcon_manager.connect()
        if not connected:
            self.exit(message="Failed to connect to RCON server")
            return
        
        # Start update tasks
        self._start_update_tasks()
    
    def _start_update_tasks(self):
        """Start periodic update tasks"""
        intervals = self.config.get('update_intervals', {})
        
        # Player updates
        async def update_players():
            player_panel = self.query_one(PlayerPanel)
            while True:
                await player_panel.update_players()
                await asyncio.sleep(intervals.get('players', 2))
        
        # TPS updates
        async def update_tps():
            tps_panel = self.query_one(TPSPanel)
            while True:
                await tps_panel.update_tps()
                await asyncio.sleep(intervals.get('tps', 1))
        
        # Log updates
        async def update_logs():
            log_panel = self.query_one(LogPanel)
            while True:
                await log_panel.update_logs()
                await asyncio.sleep(intervals.get('logs', 0.5))
        
        # Start all tasks
        asyncio.create_task(update_players())
        asyncio.create_task(update_tps())
        asyncio.create_task(update_logs())
    
    async def on_unmount(self) -> None:
        """Clean up on exit"""
        self.rcon_manager.disconnect()


def main():
    """Main entry point"""
    app = MinecraftTUI()
    app.run()


if __name__ == "__main__":
    main()
