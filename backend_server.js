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

// API endpoint to get status of a specific server
app.get('/api/servers/:host', async (req, res) => {
    try {
        const { host } = req.params;
        const server = servers.find(s => s.host === host);
        
        if (!server) {
            return res.status(404).json({ error: 'Server not found' });
        }

        const status = await pingMinecraftServer(server.host, server.port);
        res.json({
            ...server,
            ...status
        });
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

// Error handling middleware
app.use((err, req, res, next) => {
    console.error(err.stack);
    res.status(500).json({ error: 'Something went wrong!' });
});

// 404 handler
app.use((req, res) => {
    res.status(404).json({ error: 'Endpoint not found' });
});

app.listen(PORT, () => {
    console.log(`🚀 Minecraft Status Server running on port ${PORT}`);
    console.log(`📊 Dashboard available at: http://localhost:${PORT}`);
    console.log(`🔗 API endpoint: http://localhost:${PORT}/api/servers`);
});

module.exports = app;