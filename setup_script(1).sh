#!/bin/bash

# Minecraft Server Status Website Setup Script
# For Oracle Cloud Infrastructure Ubuntu Instance

set -e

echo "🚀 Starting Minecraft Server Status Website Setup..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root. Please run as a regular user with sudo privileges."
   exit 1
fi

# Update system packages
print_status "Updating system packages..."
sudo apt update && sudo apt upgrade -y

# Install required dependencies
print_status "Installing required dependencies..."
sudo apt install -y curl wget git nginx certbot python3-certbot-nginx ufw

# Install Node.js (using NodeSource repository for latest LTS)
print_status "Installing Node.js..."
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verify installations
print_status "Verifying installations..."
node_version=$(node --version)
npm_version=$(npm --version)
nginx_version=$(nginx -v 2>&1 | cut -d' ' -f3)

print_success "Node.js version: $node_version"
print_success "NPM version: $npm_version"
print_success "Nginx version: $nginx_version"

# Create application directory
APP_DIR="/home/$(whoami)/minecraft-status"
print_status "Creating application directory at $APP_DIR..."
mkdir -p $APP_DIR
cd $APP_DIR

# Clone or download the application files
print_status "Setting up application files..."

# Create the main server file
cat > server.js << 'EOF'
const express = require('express');
const path = require('path');
const cors = require('cors');
const mcping = require('mcping-js');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

// Server configurations
const servers = [
    {
        name: "Vanilla Server",
        host: "minecraft.danielmiulet.xyz",
        port: 25565,
        dynmap: "http://minecraft.danielmiulet.xyz:8123",
        whitelist: false,
        description: "Classic Minecraft with Dynmap"
    },
    {
        name: "GregTech New Horizons",
        host: "gtnh.danielmiulet.xyz", 
        port: 25565,
        dynmap: null,
        whitelist: true,
        description: "Modded Minecraft - GTNH"
    }
];

// Function to ping a Minecraft server
async function pingMinecraftServer(host, port) {
    return new Promise((resolve) => {
        const server = new mcping.MinecraftServer(host, port);
        
        const timeout = setTimeout(() => {
            resolve({
                online: false,
                error: 'Timeout',
                players: 0,
                maxPlayers: 0,
                playerList: [],
                version: 'Unknown',
                ping: 0
            });
        }, 5000);

        server.ping(5000, 47, (err, res) => {
            clearTimeout(timeout);
            
            if (err) {
                resolve({
                    online: false,
                    error: err.message,
                    players: 0,
                    maxPlayers: 0,
                    playerList: [],
                    version: 'Unknown',
                    ping: 0
                });
            } else {
                resolve({
                    online: true,
                    players: res.players.online,
                    maxPlayers: res.players.max,
                    playerList: res.players.sample ? res.players.sample.map(p => p.name) : [],
                    version: res.version.name,
                    ping: res.latency || 0,
                    motd: res.description.text || res.description
                });
            }
        });
    });
}

// API endpoint to get server status
app.get('/api/servers', async (req, res) => {
    try {
        const serverPromises = servers.map(async (server) => {
            const status = await pingMinecraftServer(server.host, server.port);
            return {
                ...server,
                ...status
            };
        });

        const results = await Promise.all(serverPromises);
        res.json(results);
    } catch (error) {
        console.error('Error fetching server status:', error);
        res.status(500).json({ error: 'Failed to fetch server status' });
    }
});

// Health check endpoint
app.get('/api/health', (req, res) => {
    res.json({ status: 'OK', timestamp: new Date().toISOString() });
});

// Serve the main HTML file
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

app.listen(PORT, () => {
    console.log(`🚀 Minecraft Status Server running on port ${PORT}`);
});

module.exports = app;
EOF

# Create package.json
cat > package.json << 'EOF'
{
  "name": "minecraft-server-status",
  "version": "1.0.0",
  "description": "Minecraft server status dashboard",
  "main": "server.js",
  "scripts": {
    "start": "node server.js",
    "dev": "nodemon server.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "cors": "^2.8.5",
    "mcping-js": "^1.0.3"
  },
  "devDependencies": {
    "nodemon": "^3.0.1"
  }
}
EOF

# Create public directory and HTML file
mkdir -p public
# Note: You'll need to copy the HTML content from the first artifact to public/index.html

# Install npm dependencies
print_status "Installing Node.js dependencies..."
npm install

# Install PM2 for process management
print_status "Installing PM2 for process management..."
sudo npm install -g pm2

# Create PM2 ecosystem file
cat > ecosystem.config.js << 'EOF'
module.exports = {
  apps: [{
    name: 'minecraft-status',
    script: 'server.js',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '1G',
    env: {
      NODE_ENV: 'production',
      PORT: 3000
    }
  }]
};
EOF

# Configure Nginx
print_status "Configuring Nginx..."
sudo tee /etc/nginx/sites-available/minecraft-status << EOF
server {
    listen 80;
    server_name status.danielmiulet.xyz;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
}
EOF

# Enable the site
sudo ln -sf /etc/nginx/sites-available/minecraft-status /etc/nginx/sites-enabled/
sudo nginx -t

# Configure firewall
print_status "Configuring firewall..."
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable

# Start and enable services
print_status "Starting services..."
sudo systemctl start nginx
sudo systemctl enable nginx

# Start the application with PM2
print_status "Starting the application..."
pm2 start ecosystem.config.js
pm2 save
pm2 startup | grep "sudo" | sh

print_success "🎉 Setup completed successfully!"
print_warning "⚠️  IMPORTANT NEXT STEPS:"
echo ""
echo "1. Copy the HTML content to public/index.html:"
echo "   You need to manually copy the HTML from the website artifact"
echo "   to $APP_DIR/public/index.html"
echo ""
echo "2. Point your domain status.danielmiulet.xyz to this server's IP"
echo ""
echo "3. Install SSL certificate (run after DNS is configured):"
echo "   sudo certbot --nginx -d status.danielmiulet.xyz"
echo ""
echo "4. Restart the application:"
echo "   pm2 restart minecraft-status"
echo ""
echo "🌐 Your website will be available at: https://status.danielmiulet.xyz"
echo "🔧 Application directory: $APP_DIR"
echo "📊 PM2 status: pm2 status"
echo "📝 PM2 logs: pm2 logs minecraft-status"
echo ""
print_success "Setup script completed!"

# Function to create the HTML file
create_html_file() {
    print_status "Creating the HTML file..."
    cat > public/index.html << 'HTMLEOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daniel's Minecraft Servers</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 0;
        }

        .header h1 {
            color: white;
            font-size: 3rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
        }

        .servers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .server-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .server-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .server-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .server-name {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .server-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-online {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .status-offline {
            background: linear-gradient(45deg, #f44336, #da190b);
            color: white;
            box-shadow: 0 4px 15px rgba(244, 67, 54, 0.3);
        }

        .server-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1rem;
            color: #2c3e50;
        }

        .players-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .players-header {
            font-weight: bold;
            color: #666;
            margin-bottom: 10px;
        }

        .player-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .player-tag {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .server-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #36d1dc, #5b86e5);
            color: white;
            box-shadow: 0 4px 15px rgba(54, 209, 220, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(54, 209, 220, 0.4);
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .footer {
            text-align: center;
            padding: 40px 0;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .footer p {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .servers-grid {
                grid-template-columns: 1fr;
            }
            
            .server-info {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .server-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎮 Daniel's Minecraft Servers</h1>
            <p>Real-time server status and information</p>
        </div>

        <div id="servers-container">
            <div class="loading">
                <div class="spinner"></div>
                <p>Loading server information...</p>
            </div>
        </div>

        <div class="footer">
            <p>Last updated: <span id="last-updated">Never</span></p>
        </div>
    </div>

    <script>
        async function fetchServerStatus() {
            const container = document.getElementById('servers-container');
            
            try {
                const response = await fetch('/api/servers');
                const data = await response.json();
                renderServers(data);
            } catch (error) {
                console.error('Error fetching server data:', error);
                container.innerHTML = `
                    <div class="loading">
                        <p style="color: #f44336;">❌ Failed to load server information</p>
                        <p style="color: #666; font-size: 0.9rem;">Retrying in 30 seconds...</p>
                    </div>
                `;
            }
        }

        function renderServers(serversData) {
            const container = document.getElementById('servers-container');
            
            container.innerHTML = `
                <div class="servers-grid">
                    ${serversData.map(server => `
                        <div class="server-card">
                            <div class="server-header">
                                <div class="server-name">${server.name}</div>
                                <div class="server-status ${server.online ? 'status-online' : 'status-offline'}">
                                    ${server.online ? 'Online' : 'Offline'}
                                </div>
                            </div>
                            
                            <div class="server-info">
                                <div class="info-item">
                                    <div class="info-label">Server Address</div>
                                    <div class="info-value">${server.host}:${server.port}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Version</div>
                                    <div class="info-value">${server.version || 'Unknown'}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Players</div>
                                    <div class="info-value">${server.online ? `${server.players}/${server.maxPlayers}` : 'N/A'}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Whitelist</div>
                                    <div class="info-value">${server.whitelist ? 'Required' : 'Open'}</div>
                                </div>
                                ${server.online && server.ping ? `
                                <div class="info-item">
                                    <div class="info-label">Ping</div>
                                    <div class="info-value">${server.ping}ms</div>
                                </div>
                                ` : ''}
                            </div>
                            
                            ${server.online && server.playerList && server.playerList.length > 0 ? `
                            <div class="players-list">
                                <div class="players-header">Online Players:</div>
                                <div class="player-tags">
                                    ${server.playerList.map(player => `<span class="player-tag">${player}</span>`).join('')}
                                </div>
                            </div>
                            ` : ''}
                            
                            <div class="server-actions">
                                <button class="action-btn btn-primary" onclick="copyToClipboard('${server.host}:${server.port}')">
                                    Copy IP
                                </button>
                                ${server.dynmap ? `
                                <a href="${server.dynmap}" target="_blank" class="action-btn btn-secondary">
                                    View Map
                                </a>
                                ` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            document.getElementById('last-updated').textContent = new Date().toLocaleString();
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #4CAF50;
                    color: white;
                    padding: 12px 24px;
                    border-radius: 25px;
                    z-index: 1000;
                    font-weight: bold;
                    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
                `;
                notification.textContent = 'IP copied to clipboard!';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            });
        }

        // Auto-refresh every 30 seconds
        setInterval(fetchServerStatus, 30000);
        
        // Initial load
        fetchServerStatus();
    </script>
</body>
</html>
HTMLEOF
}

# Call the function to create HTML file
create_html_file