#!/bin/bash
"""
Wrapper script to run the extract_test_deployment.py with proper environment
"""

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Virtual environment path
VENV_PATH="$SCRIPT_DIR/venv"

# Check if virtual environment exists
if [ ! -d "$VENV_PATH" ]; then
    echo "❌ Virtual environment not found at: $VENV_PATH"
    echo "Please create a virtual environment first:"
    echo "python3 -m venv $VENV_PATH"
    echo "source $VENV_PATH/bin/activate"
    echo "pip install requests"
    exit 1
fi

# Activate virtual environment and run the script
echo "🚀 Starting Extract Test Deployment..."
echo "Using virtual environment: $VENV_PATH"
echo ""

source "$VENV_PATH/bin/activate"

# Check if requests is available
python3 -c "import requests" 2>/dev/null
if [ $? -ne 0 ]; then
    echo "❌ requests module not found in virtual environment"
    echo "Installing requests..."
    pip install requests
fi

# Run the extraction script
python3 "$SCRIPT_DIR/extract_test_deployment.py" "$@"

# Capture exit code
EXIT_CODE=$?

# Deactivate virtual environment
deactivate

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ Extract Test Deployment completed successfully!"
else
    echo "❌ Extract Test Deployment failed with exit code: $EXIT_CODE"
fi

exit $EXIT_CODE