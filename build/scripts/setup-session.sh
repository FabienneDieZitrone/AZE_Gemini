#!/bin/bash
#
# Setup script for new deployment sessions
# This helps you securely set up credentials for deployment
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}AZE_Gemini Session Setup${NC}"
echo "========================"
echo

# Check if we're in the right directory
if [ ! -f "deploy-secure.sh" ]; then
    echo -e "${RED}Error: Not in the build directory${NC}"
    echo "Please run this from /app/build"
    exit 1
fi

# Check if .env.local already exists
if [ -f ".env.local" ]; then
    echo -e "${YELLOW}Warning: .env.local already exists${NC}"
    read -p "Overwrite? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Setup cancelled"
        exit 0
    fi
fi

# Create .env.local from template
cp .env.example .env.local
chmod 600 .env.local

echo -e "${GREEN}✓ Created .env.local from template${NC}"
echo

# Get credentials
echo "Please enter your FTP credentials:"
echo "(These will be stored in .env.local only)"
echo

read -p "FTP Username: " FTP_USER
read -s -p "FTP Password: " FTP_PASS
echo

# Update .env.local
sed -i "s/your-ftp-username/$FTP_USER/" .env.local
sed -i "s/your-ftp-password/$FTP_PASS/" .env.local

echo -e "\n${GREEN}✓ Credentials saved to .env.local${NC}"

# Verify setup
echo -e "\n${YELLOW}Verifying setup...${NC}"

if grep -q "your-ftp" .env.local; then
    echo -e "${RED}✗ Setup incomplete - please check .env.local${NC}"
else
    echo -e "${GREEN}✓ Setup complete!${NC}"
fi

echo -e "\n${YELLOW}Security reminders:${NC}"
echo "- Never commit .env.local to Git"
echo "- Delete .env.local after deployment if on shared system"
echo "- Use 'shred -u .env.local' for secure deletion"

echo -e "\n${GREEN}Ready to deploy! Run:${NC}"
echo "./deploy-secure.sh"