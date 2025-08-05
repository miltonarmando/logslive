#!/bin/bash

# ACT Sentinel Log Reader - Python Setup Script
echo "=== ACT Sentinel Log Reader - Python Setup ==="
echo

# Check Python version
echo "1. Checking Python installation..."
if command -v python3 &> /dev/null; then
    PYTHON_VERSION=$(python3 --version)
    echo "✅ Python found: $PYTHON_VERSION"
    
    # Check if version is 3.8+
    PYTHON_MAJOR=$(python3 -c "import sys; print(sys.version_info.major)")
    PYTHON_MINOR=$(python3 -c "import sys; print(sys.version_info.minor)")
    
    if [ "$PYTHON_MAJOR" -eq 3 ] && [ "$PYTHON_MINOR" -ge 8 ]; then
        echo "✅ Python version is compatible (3.8+)"
    else
        echo "⚠️  Python 3.8+ recommended for best compatibility"
    fi
else
    echo "❌ Python3 not found"
    echo "Install Python 3.8+ and try again"
    exit 1
fi

echo

# Check pip
echo "2. Checking pip installation..."
if command -v pip3 &> /dev/null; then
    echo "✅ pip3 found"
    PIP_CMD="pip3"
elif command -v pip &> /dev/null; then
    echo "✅ pip found"
    PIP_CMD="pip"
else
    echo "❌ pip not found"
    echo "Install pip and try again"
    exit 1
fi

echo

# Install dependencies
echo "3. Installing Python dependencies..."
if [ -f "requirements.txt" ]; then
    echo "Installing from requirements.txt..."
    $PIP_CMD install -r requirements.txt
    
    if [ $? -eq 0 ]; then
        echo "✅ Dependencies installed successfully"
    else
        echo "❌ Failed to install dependencies"
        echo "Try: $PIP_CMD install --user -r requirements.txt"
        exit 1
    fi
else
    echo "❌ requirements.txt not found"
    echo "Make sure you're in the python/ directory"
    exit 1
fi

echo

# Test SMB connectivity
echo "4. Testing SMB connectivity..."
echo "Running SMB diagnostic tool..."
echo
python3 test_smb.py

echo
echo "=== Setup Complete ==="
echo
echo "To start the application:"
echo "  python3 app.py"
echo
echo "Then open your browser to:"
echo "  http://localhost:8000"
echo
echo "For troubleshooting, run:"
echo "  python3 test_smb.py"
echo
