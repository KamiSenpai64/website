# Minecraft Server TUI

A modern Terminal User Interface (TUI) for managing and monitoring Minecraft servers in real-time.

## Features

- **Player Monitoring**: Real-time display of online players and player count
- **TPS Monitoring**: Track server performance with TPS (Ticks Per Second) indicator
- **Command Execution**: Send commands to your server with auto-completion support
- **Log Streaming**: View server logs in real-time
- **RCON Integration**: Communicate with your server via RCON protocol

## Requirements

- Python 3.7 or higher
- Minecraft server with RCON enabled
- Linux/Unix-like operating system

## Installation

1. Clone or navigate to the MinecraftStuff directory:
```bash
cd /home/opc/Website/MinecraftStuff
```

2. Install required Python packages:
```bash
pip install -r requirements.txt
```

## Configuration

Edit `config.yaml` to configure your server settings:

```yaml
# RCON Connection Settings
rcon:
  host: localhost        # Your server's IP address
  port: 25575           # RCON port (default: 25575)
  password: your_rcon_password  # Your RCON password

# Server Log Path (for log streaming)
log_path: /path/to/minecraft/server/logs/latest.log

# Update Intervals (in seconds)
update_intervals:
  players: 2            # Update player list every 2 seconds
  tps: 1                # Update TPS every 1 second
  logs: 0.5             # Update logs every 0.5 seconds
```

### Enabling RCON on Your Minecraft Server

To enable RCON, edit your `server.properties` file:

```properties
enable-rcon=true
rcon.port=25575
rcon.password=your_secure_password
broadcast-rcon-to-ops=false
```

Restart your server after making these changes.

## Usage

Run the TUI application:

```bash
python3 mc_tui.py
```

Or make it executable:

```bash
chmod +x mc_tui.py
./mc_tui.py
```

## Controls

- **Tab**: Auto-complete commands
- **Enter**: Send command to server
- **q**: Quit the application
- **Ctrl+C**: Quit the application

## Interface Layout

The TUI is divided into several panels:

1. **Players Panel** (Left): Shows online players and player count
2. **TPS Panel** (Right): Displays server TPS and performance status
3. **Logs Panel** (Bottom): Shows real-time server logs
4. **Command Input** (Bottom): Input field for sending commands with auto-completion

## Command Auto-completion

The TUI supports auto-completion for common Minecraft commands. Press **Tab** while typing a command to see available completions.

Supported commands include:
- Player management: `/op`, `/deop`, `/ban`, `/kick`, `/whitelist`
- Gameplay: `/gamemode`, `/give`, `/tp`, `/time`, `/weather`
- Server management: `/stop`, `/save-all`, `/reload`
- And many more...

## TPS Indicators

- **20.0+ (Green)**: Excellent performance
- **19.5-20.0 (Green)**: Very good performance
- **18.0-19.5 (Yellow)**: Good performance
- **15.0-18.0 (Orange)**: Fair performance
- **Below 15.0 (Red)**: Poor performance - consider investigation

## Troubleshooting

### Connection Failed

If you see "Failed to connect to RCON server":
- Verify RCON is enabled in `server.properties`
- Check that the RCON password matches in `config.yaml`
- Ensure the server is running
- Check firewall settings for the RCON port

### TPS Not Showing

TPS monitoring requires server plugins or specific server types:
- Paper/Spigot servers: Use the `/tps` command
- Forge servers: May require additional plugins
- Vanilla servers: TPS monitoring may not be available

### Logs Not Streaming

If logs aren't appearing:
- Verify the `log_path` in `config.yaml` is correct
- Ensure the log file exists and is readable
- Check file permissions

## Advanced Usage

### Running as a Service

You can run the TUI as a systemd service for automatic startup:

Create `/etc/systemd/system/minecraft-tui.service`:

```ini
[Unit]
Description=Minecraft Server TUI
After=network.target

[Service]
Type=simple
User=your_username
WorkingDirectory=/home/opc/Website/MinecraftStuff
ExecStart=/usr/bin/python3 /home/opc/Website/MinecraftStuff/mc_tui.py
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl enable minecraft-tui
sudo systemctl start minecraft-tui
```

## Security Notes

- Keep your RCON password secure and don't commit it to version control
- Use strong, unique passwords for RCON
- Consider using a firewall to restrict RCON access to trusted IPs
- The TUI sends commands with the same permissions as the RCON user

## License

This tool is provided as-is for managing Minecraft servers.

## Contributing

Feel free to submit issues, feature requests, or pull requests to improve this tool.
