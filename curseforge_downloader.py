#!/usr/bin/env python3
"""
CurseForge Mod and Modpack Downloader
A polished script for downloading Minecraft mods and modpacks from CurseForge API
"""

import requests
import json
import os
import sys
from pathlib import Path
from typing import Dict, List, Optional, Tuple
import argparse
from datetime import datetime

# ============================================================================
# CONFIGURATION - ADD YOUR API KEY HERE
# ============================================================================
# Get your free API key from: https://console.curseforge.com/
# Replace 'YOUR_API_KEY_HERE' with your actual API key
API_KEY = 'YOUR_API_KEY_HERE'

# ============================================================================
# CONSTANTS
# ============================================================================
BASE_URL = 'https://api.curseforge.com/v1'
MINECRAFT_GAME_ID = 432
HEADERS = {
    'Accept': 'application/json',
    'x-api-key': API_KEY
}

# Minecraft categories
CATEGORIES = {
    'mods': 6,
    'modpacks': 4471,
    'resource_packs': 12,
    'worlds': 17
}

class CurseForgeDownloader:
    def __init__(self):
        self.session = requests.Session()
        self.session.headers.update(HEADERS)
        self.download_dir = Path('./downloads')
        self.download_dir.mkdir(exist_ok=True)
        
    def validate_api_key(self) -> bool:
        """Validate that the API key is working"""
        if API_KEY == 'YOUR_API_KEY_HERE':
            print("❌ Error: Please set your API key in the script!")
            print("Get your free API key from: https://console.curseforge.com/")
            return False
            
        try:
            response = self.session.get(f'{BASE_URL}/games')
            return response.status_code == 200
        except Exception as e:
            print(f"❌ Error validating API key: {e}")
            return False
    
    def search_mods(self, query: str, category: str = 'mods', limit: int = 10) -> List[Dict]:
        """Search for mods/modpacks by name"""
        category_id = CATEGORIES.get(category, CATEGORIES['mods'])
        
        params = {
            'gameId': MINECRAFT_GAME_ID,
            'categoryId': category_id,
            'searchFilter': query,
            'sortField': 2,  # Popularity
            'sortOrder': 'desc',
            'pageSize': limit
        }
        
        try:
            response = self.session.get(f'{BASE_URL}/mods/search', params=params)
            response.raise_for_status()
            return response.json().get('data', [])
        except Exception as e:
            print(f"❌ Error searching for {category}: {e}")
            return []
    
    def get_mod_files(self, mod_id: int, minecraft_version: Optional[str] = None) -> List[Dict]:
        """Get available files for a mod"""
        try:
            response = self.session.get(f'{BASE_URL}/mods/{mod_id}/files')
            response.raise_for_status()
            files = response.json().get('data', [])
            
            # Filter by Minecraft version if specified
            if minecraft_version:
                filtered_files = []
                for file in files:
                    game_versions = file.get('gameVersions', [])
                    if minecraft_version in game_versions:
                        filtered_files.append(file)
                return filtered_files
            
            return files
        except Exception as e:
            print(f"❌ Error getting mod files: {e}")
            return []
    
    def download_file(self, mod_id: int, file_id: int, filename: str) -> bool:
        """Download a specific file"""
        try:
            # Get download URL
            response = self.session.get(f'{BASE_URL}/mods/{mod_id}/files/{file_id}/download-url')
            response.raise_for_status()
            download_url = response.json().get('data')
            
            if not download_url:
                print(f"❌ Could not get download URL for {filename}")
                return False
            
            # Download the file
            print(f"📥 Downloading {filename}...")
            file_response = requests.get(download_url, stream=True)
            file_response.raise_for_status()
            
            # Save to downloads directory
            file_path = self.download_dir / filename
            with open(file_path, 'wb') as f:
                for chunk in file_response.iter_content(chunk_size=8192):
                    f.write(chunk)
            
            print(f"✅ Downloaded: {file_path}")
            return True
            
        except Exception as e:
            print(f"❌ Error downloading {filename}: {e}")
            return False
    
    def display_search_results(self, results: List[Dict], category: str):
        """Display search results in a formatted way"""
        if not results:
            print(f"No {category} found.")
            return
        
        print(f"\n🔍 Found {len(results)} {category}:")
        print("-" * 80)
        
        for i, mod in enumerate(results, 1):
            name = mod.get('name', 'Unknown')
            author = mod.get('authors', [{}])[0].get('name', 'Unknown') if mod.get('authors') else 'Unknown'
            downloads = mod.get('downloadCount', 0)
            summary = mod.get('summary', 'No description available')
            
            # Truncate summary if too long
            if len(summary) > 100:
                summary = summary[:97] + "..."
            
            print(f"{i:2d}. {name}")
            print(f"    Author: {author} | Downloads: {downloads:,}")
            print(f"    {summary}")
            print(f"    ID: {mod.get('id')}")
            print()
    
    def interactive_download(self, results: List[Dict], minecraft_version: Optional[str] = None):
        """Interactive download process"""
        if not results:
            return
        
        try:
            choice = input(f"Enter the number of the mod to download (1-{len(results)}) or 'q' to quit: ").strip()
            
            if choice.lower() == 'q':
                return
            
            choice_num = int(choice)
            if 1 <= choice_num <= len(results):
                selected_mod = results[choice_num - 1]
                mod_id = selected_mod['id']
                mod_name = selected_mod['name']
                
                print(f"\n📦 Getting files for {mod_name}...")
                files = self.get_mod_files(mod_id, minecraft_version)
                
                if not files:
                    print("❌ No files available for this mod.")
                    return
                
                # Display available files
                print(f"\nAvailable files for {mod_name}:")
                print("-" * 60)
                
                for i, file in enumerate(files[:10], 1):  # Show max 10 files
                    filename = file.get('fileName', 'Unknown')
                    file_date = file.get('fileDate', '')
                    game_versions = ', '.join(file.get('gameVersions', [])[:3])  # Show first 3 versions
                    
                    if len(game_versions) > 30:
                        game_versions = game_versions[:27] + "..."
                    
                    print(f"{i:2d}. {filename}")
                    print(f"    Versions: {game_versions}")
                    print(f"    Date: {file_date[:10] if file_date else 'Unknown'}")
                    print()
                
                # Get file choice
                file_choice = input(f"Enter file number to download (1-{len(files[:10])}) or 'q' to quit: ").strip()
                
                if file_choice.lower() == 'q':
                    return
                
                file_num = int(file_choice)
                if 1 <= file_num <= len(files[:10]):
                    selected_file = files[file_num - 1]
                    file_id = selected_file['id']
                    filename = selected_file['fileName']
                    
                    success = self.download_file(mod_id, file_id, filename)
                    if success:
                        print(f"🎉 Successfully downloaded {filename}!")
                    
                else:
                    print("❌ Invalid file number.")
            else:
                print("❌ Invalid choice.")
                
        except ValueError:
            print("❌ Please enter a valid number.")
        except KeyboardInterrupt:
            print("\n👋 Download cancelled.")

def main():
    parser = argparse.ArgumentParser(description='Download Minecraft mods and modpacks from CurseForge')
    parser.add_argument('query', help='Search query for mods/modpacks')
    parser.add_argument('-c', '--category', choices=['mods', 'modpacks', 'resource_packs', 'worlds'], 
                       default='mods', help='Category to search in (default: mods)')
    parser.add_argument('-v', '--version', help='Minecraft version filter (e.g., 1.20.1)')
    parser.add_argument('-l', '--limit', type=int, default=10, help='Number of results to show (default: 10)')
    parser.add_argument('--direct', type=int, nargs=2, metavar=('MOD_ID', 'FILE_ID'), 
                       help='Direct download by mod ID and file ID')
    
    args = parser.parse_args()
    
    downloader = CurseForgeDownloader()
    
    # Validate API key
    if not downloader.validate_api_key():
        sys.exit(1)
    
    print("🚀 CurseForge Mod Downloader")
    print("=" * 40)
    
    # Direct download mode
    if args.direct:
        mod_id, file_id = args.direct
        print(f"📥 Direct download: Mod ID {mod_id}, File ID {file_id}")
        
        # Get file info first
        try:
            response = downloader.session.get(f'{BASE_URL}/mods/{mod_id}/files/{file_id}')
            response.raise_for_status()
            file_info = response.json().get('data', {})
            filename = file_info.get('fileName', f'mod_{mod_id}_file_{file_id}.jar')
            
            success = downloader.download_file(mod_id, file_id, filename)
            if success:
                print(f"🎉 Successfully downloaded {filename}!")
            else:
                print("❌ Download failed.")
        except Exception as e:
            print(f"❌ Error with direct download: {e}")
        
        return
    
    # Search and interactive download
    print(f"🔍 Searching for '{args.query}' in {args.category}...")
    if args.version:
        print(f"🎯 Filtering for Minecraft version: {args.version}")
    
    results = downloader.search_mods(args.query, args.category, args.limit)
    downloader.display_search_results(results, args.category)
    
    if results:
        downloader.interactive_download(results, args.version)

if __name__ == '__main__':
    main()
