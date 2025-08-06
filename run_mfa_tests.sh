#!/bin/bash

# MFA Test Suite Runner Script
# Comprehensive testing of MFA implementation

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print header
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    MFA Test Suite Runner                     ║${NC}"
echo -e "${BLUE}║              Comprehensive MFA Implementation Tests          ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Check if we're in the right directory
if [ ! -f "tests/run_mfa_tests.py" ]; then
    echo -e "${RED}❌ Error: MFA test suite not found${NC}"
    echo "Please run this script from the project root directory"
    echo "Expected: tests/run_mfa_tests.py"
    exit 1
fi

# Check Python availability
if ! command -v python3 &> /dev/null; then
    echo -e "${RED}❌ Error: Python 3 is required but not installed${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Environment check passed${NC}"
echo ""

# Display test information
echo -e "${BLUE}📋 Test Information:${NC}"
echo "🌐 Test Environment: https://aze.mikropartner.de/aze-test/"
echo "📅 Test Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "🐍 Python Version: $(python3 --version)"
echo "📍 Working Directory: $(pwd)"
echo ""

# Check if tests directory exists and has required files
echo -e "${BLUE}📁 Checking test files...${NC}"

required_files=(
    "tests/mfa_comprehensive_test_suite.py"
    "tests/mfa_database_test.py"
    "tests/mfa_user_flow_test.py"
    "tests/run_mfa_tests.py"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo -e "  ✅ $file"
    else
        echo -e "  ${RED}❌ Missing: $file${NC}"
        exit 1
    fi
done

echo ""

# Install required Python packages if needed
echo -e "${BLUE}📦 Checking Python dependencies...${NC}"

# Check if requests is available
if ! python3 -c "import requests" 2>/dev/null; then
    echo -e "${YELLOW}⚠️  Installing required packages...${NC}"
    pip3 install requests --quiet 2>/dev/null || {
        echo -e "${RED}❌ Failed to install requests package${NC}"
        echo "Please install manually: pip3 install requests"
        exit 1
    }
    echo -e "  ✅ requests package installed"
else
    echo -e "  ✅ requests package available"
fi

echo ""

# Create logs directory if it doesn't exist
mkdir -p logs

# Run the tests
echo -e "${BLUE}🚀 Starting MFA Test Suite...${NC}"
echo "This may take several minutes to complete."
echo ""

# Run tests and capture output
START_TIME=$(date +%s)

if python3 tests/run_mfa_tests.py 2>&1 | tee "logs/mfa_test_run_$(date +%Y%m%d_%H%M%S).log"; then
    EXIT_CODE=0
else
    EXIT_CODE=$?
fi

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                      Test Suite Complete                     ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"

# Display results summary
echo ""
echo -e "${BLUE}📊 Test Results Summary:${NC}"
echo "⏱️  Duration: ${DURATION} seconds"

case $EXIT_CODE in
    0)
        echo -e "🎯 Status: ${GREEN}ALL TESTS PASSED ✅${NC}"
        echo -e "💡 Assessment: ${GREEN}MFA implementation is ready for production${NC}"
        ;;
    1)
        echo -e "🎯 Status: ${YELLOW}MINOR ISSUES DETECTED ⚠️${NC}"
        echo -e "💡 Assessment: ${YELLOW}Review and fix issues before deployment${NC}"
        ;;
    *)
        echo -e "🎯 Status: ${RED}SIGNIFICANT ISSUES ❌${NC}"
        echo -e "💡 Assessment: ${RED}Major fixes required before deployment${NC}"
        ;;
esac

echo ""

# List generated reports
echo -e "${BLUE}📄 Generated Reports:${NC}"
reports=(
    "MFA_MASTER_TEST_REPORT.md"
    "MFA_COMPREHENSIVE_TEST_REPORT.md"
    "MFA_DATABASE_TEST_REPORT.md"
    "MFA_USER_FLOW_TEST_REPORT.md"
)

for report in "${reports[@]}"; do
    if [ -f "$report" ]; then
        echo -e "  📋 $report"
    fi
done

echo ""

# Display next steps based on results
echo -e "${BLUE}📋 Next Steps:${NC}"
case $EXIT_CODE in
    0)
        echo "1. 🎉 Review the master test report for details"
        echo "2. 👥 Proceed with user acceptance testing"
        echo "3. 🔗 Test frontend integration"
        echo "4. 📱 Verify TOTP app compatibility"
        echo "5. 🚀 Plan production deployment"
        ;;
    1)
        echo "1. 📖 Review individual test reports for failing cases"
        echo "2. 🔧 Fix identified issues"
        echo "3. 🔄 Re-run test suite"
        echo "4. ✅ Verify all issues are resolved"
        echo "5. 📋 Plan staged deployment"
        ;;
    *)
        echo "1. 🚨 Review all test reports immediately"
        echo "2. 🔍 Check server logs for errors"
        echo "3. 🗄️  Verify database schema installation"
        echo "4. ⚙️  Check API endpoint configuration"
        echo "5. 🛠️  Fix critical issues before retesting"
        echo "6. ❌ DO NOT DEPLOY until issues are resolved"
        ;;
esac

echo ""
echo -e "${BLUE}🔗 Test Environment: https://aze.mikropartner.de/aze-test/${NC}"
echo -e "${BLUE}📧 For support: Review generated reports and server logs${NC}"

echo ""

exit $EXIT_CODE