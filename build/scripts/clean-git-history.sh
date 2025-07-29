#!/bin/bash
# Script to clean sensitive data from Git history
# USE WITH EXTREME CAUTION - This rewrites history!

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}WARNING: This script will rewrite Git history!${NC}"
echo "This should only be used if credentials were accidentally committed."
echo ""
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

echo -e "${YELLOW}Creating backup...${NC}"
git branch backup-before-clean

# Install BFG Repo-Cleaner if not present
if ! command -v bfg &> /dev/null; then
    echo "Installing BFG Repo-Cleaner..."
    wget https://repo1.maven.org/maven2/com/madgag/bfg/1.14.0/bfg-1.14.0.jar
    alias bfg='java -jar bfg-1.14.0.jar'
fi

# Create file with sensitive strings to remove
cat > sensitive-strings.txt << EOF
ftp10454681
***REDACTED***
EOF

echo -e "${YELLOW}Removing sensitive data from history...${NC}"

# Remove sensitive strings
java -jar bfg-1.14.0.jar --replace-text sensitive-strings.txt

# Clean up
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Remove sensitive files
rm -f sensitive-strings.txt
rm -f bfg-1.14.0.jar

echo -e "${GREEN}Git history cleaned!${NC}"
echo ""
echo "Next steps:"
echo "1. Review the changes: git log --oneline"
echo "2. If satisfied, force push: git push --force"
echo "3. Tell all team members to re-clone the repository"
echo ""
echo -e "${YELLOW}Backup branch created: backup-before-clean${NC}"