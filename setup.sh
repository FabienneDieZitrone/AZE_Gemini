#!/bin/bash
# ---
# title: Codex Start Setup
# description: Installs .NET 8 SDK on container start
# ---
set -e

# Check if .NET 8 SDK already installed
if dotnet --list-sdks 2>/dev/null | grep -q '^8\.0'; then
    echo ".NET 8 SDK already installed"
else
    echo "Installing .NET 8 SDK..."
    sudo apt-get update
    sudo apt-get install -y dotnet-sdk-8.0
fi

echo "dotnet version: $(dotnet --version)"
