#!/bin/bash

# Complete Deployment Script for Minecraft Server Status Dashboard
# This script sets up everything and pushes to GitHub

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Configuration
APP_NAME="minecraft-server-status"
DOMAIN="status.danielmiulet.xyz"
APP_DIR="/home/$(whoami)/$APP_NAME"
EMAIL="your-email@example.com" # Change this to your email

print_status "🚀 Starting complete deployment of Minecraft Server Status Dashboard..."

# Check if Git is installed
if ! command -v git &> /dev/null; then
    print_status "Installing Git..."
    sudo apt update
    sudo apt install -y git
fi

# Create project directory
print_status "Creating project directory..."
mkdir -p "$APP_DIR"
cd "$APP_DIR"

# Initialize Git repository
print_status "Initializing Git repository..."
git init

# Create .gitignore
cat > .gitignore << 'EOF'
node_modules/
npm-debug.log*
yarn-debug.log*
yarn-error.log*
.env
.env.local
.env.development.local
.env.test.local
.env.production.local
logs/
*.log
.DS_Store
.vscode/
.idea/
*.swp
*.swo
*~
EOF

# Create all necessary files
print_status "Creating application files..."

# Create package.json
cat > package.json << 'EOF'
{
  "name": "minecraft-server-status",
  "version": "1.0.0",
  "description": "Minecraft server status dashboard for Daniel's servers",
  "main": "server.js",
  "scripts": {
    "start": "node server.js",
    "dev": "nodemon server.js",
    "test": "echo \"Error: no test specified\" && exit 1"
  },
  "keywords": [
    "minecraft",
    "server",
    "status",
    "dashboard",
    "nodejs"
  ],
  "author": "Daniel Miulet",
  "license": "MIT",
  "dependencies": {
    "express": "^4.18.2",
    "cors": "^2.8.5",
    "mcping-js": "^1.0.3"
  },
  "devDependencies": {
    "nodemon": "^3.0.1"
  },
  "engines": {
    "node": ">=16.0.0"
  }
}
EOF

# Create server.js
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
        host: "gtnh.