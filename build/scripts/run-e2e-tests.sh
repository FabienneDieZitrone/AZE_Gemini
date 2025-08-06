#!/bin/bash

# E2E Test Runner Script for AZE Gemini
# Comprehensive test execution with environment setup and reporting

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
TEST_ENV=${TEST_ENV:-"local"}
BROWSER=${BROWSER:-"chromium"}
HEADED=${HEADED:-false}
REAL_BACKEND=${REAL_BACKEND:-false}
BASE_URL=${BASE_URL:-"http://localhost:3000"}
REPORT_DIR="e2e-reports"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

echo -e "${BLUE}🚀 AZE Gemini E2E Test Suite${NC}"
echo -e "${BLUE}================================${NC}"
echo "Environment: $TEST_ENV"
echo "Browser: $BROWSER"
echo "Headed mode: $HEADED"
echo "Real backend: $REAL_BACKEND"
echo "Base URL: $BASE_URL"
echo ""

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    print_info "Checking prerequisites..."
    
    # Check if Node.js is installed
    if ! command -v node &> /dev/null; then
        print_error "Node.js is not installed. Please install Node.js 16 or higher."
        exit 1
    fi
    
    # Check Node.js version
    NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
    if [ "$NODE_VERSION" -lt 16 ]; then
        print_error "Node.js version $NODE_VERSION is too old. Please install Node.js 16 or higher."
        exit 1
    fi
    
    # Check if npm is installed
    if ! command -v npm &> /dev/null; then
        print_error "npm is not installed. Please install npm."
        exit 1
    fi
    
    # Check if package.json exists
    if [ ! -f "package.json" ]; then
        print_error "package.json not found. Please run this script from the project root."
        exit 1
    fi
    
    print_status "Prerequisites check passed"
}

# Install dependencies
install_dependencies() {
    print_info "Installing dependencies..."
    
    if [ ! -d "node_modules" ] || [ ! -f "node_modules/.installed" ]; then
        npm install
        touch node_modules/.installed
        print_status "Dependencies installed"
    else
        print_status "Dependencies already installed"
    fi
    
    # Install Playwright browsers if needed
    if [ ! -d "node_modules/@playwright/test" ]; then
        print_error "Playwright not found in dependencies. Please run 'npm install' first."
        exit 1
    fi
    
    # Install browsers
    npx playwright install
    print_status "Playwright browsers installed"
}

# Setup test environment
setup_environment() {
    print_info "Setting up test environment..."
    
    # Create reports directory
    mkdir -p "$REPORT_DIR/$TIMESTAMP"
    
    # Set environment variables
    export NODE_ENV=$TEST_ENV
    export E2E_REAL_BACKEND=$REAL_BACKEND
    export BASE_URL=$BASE_URL
    export PLAYWRIGHT_HTML_REPORT="$REPORT_DIR/$TIMESTAMP/html-report"
    export PLAYWRIGHT_JUNIT_OUTPUT_NAME="$REPORT_DIR/$TIMESTAMP/junit-results.xml"
    
    # Create test data if needed
    if [ "$TEST_ENV" = "test" ]; then
        print_info "Setting up test data..."
        # Add test data setup here if needed
    fi
    
    print_status "Test environment configured"
}

# Start application server if needed
start_server() {
    if [ "$REAL_BACKEND" = "false" ]; then
        print_info "Starting development server..."
        
        # Check if server is already running
        if curl -f -s "$BASE_URL" > /dev/null 2>&1; then
            print_status "Server already running at $BASE_URL"
            return
        fi
        
        # Start development server in background
        npm run dev > "$REPORT_DIR/$TIMESTAMP/server.log" 2>&1 &
        SERVER_PID=$!
        echo $SERVER_PID > "$REPORT_DIR/$TIMESTAMP/server.pid"
        
        # Wait for server to start
        print_info "Waiting for server to start..."
        RETRY_COUNT=0
        MAX_RETRIES=30
        
        while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
            if curl -f -s "$BASE_URL" > /dev/null 2>&1; then
                print_status "Server started successfully"
                return
            fi
            
            sleep 2
            RETRY_COUNT=$((RETRY_COUNT + 1))
            echo -n "."
        done
        
        print_error "Server failed to start within 60 seconds"
        print_error "Check server logs at $REPORT_DIR/$TIMESTAMP/server.log"
        exit 1
    else
        print_info "Using real backend at $BASE_URL"
        
        # Verify backend is accessible
        if ! curl -f -s "$BASE_URL" > /dev/null 2>&1; then
            print_error "Backend at $BASE_URL is not accessible"
            exit 1
        fi
        
        print_status "Backend verification successful"
    fi
}

# Stop application server
stop_server() {
    if [ -f "$REPORT_DIR/$TIMESTAMP/server.pid" ]; then
        SERVER_PID=$(cat "$REPORT_DIR/$TIMESTAMP/server.pid")
        print_info "Stopping server (PID: $SERVER_PID)..."
        
        if kill -0 $SERVER_PID 2>/dev/null; then
            kill $SERVER_PID
            sleep 2
            
            # Force kill if still running
            if kill -0 $SERVER_PID 2>/dev/null; then
                kill -9 $SERVER_PID
            fi
            
            print_status "Server stopped"
        fi
        
        rm -f "$REPORT_DIR/$TIMESTAMP/server.pid"
    fi
}

# Run specific test suite
run_test_suite() {
    local suite_name=$1
    local test_file=$2
    
    print_info "Running $suite_name tests..."
    
    local playwright_args="--project=$BROWSER --reporter=html,junit,json"
    
    if [ "$HEADED" = "true" ]; then
        playwright_args="$playwright_args --headed"
    fi
    
    if [ -n "$test_file" ]; then
        playwright_args="$playwright_args $test_file"
    fi
    
    # Create suite-specific report directory
    local suite_report_dir="$REPORT_DIR/$TIMESTAMP/$suite_name"
    mkdir -p "$suite_report_dir"
    
    export PLAYWRIGHT_HTML_REPORT="$suite_report_dir/html-report"
    export PLAYWRIGHT_JUNIT_OUTPUT_NAME="$suite_report_dir/junit-results.xml"
    
    if npx playwright test $playwright_args; then
        print_status "$suite_name tests passed"
        return 0
    else
        print_error "$suite_name tests failed"
        return 1
    fi
}

# Run all test suites
run_all_tests() {
    print_info "Running complete E2E test suite..."
    
    local failed_suites=()
    local total_suites=0
    
    # Core functionality tests
    total_suites=$((total_suites + 1))
    if ! run_test_suite "auth" "e2e/auth.spec.ts"; then
        failed_suites+=("Authentication")
    fi
    
    total_suites=$((total_suites + 1))
    if ! run_test_suite "time-tracking" "e2e/time-tracking.spec.ts"; then
        failed_suites+=("Time Tracking")
    fi
    
    total_suites=$((total_suites + 1))
    if ! run_test_suite "approval-workflow" "e2e/approval-workflow.spec.ts"; then
        failed_suites+=("Approval Workflow")
    fi
    
    # Security tests
    total_suites=$((total_suites + 1))
    if ! run_test_suite "security" "e2e/security.spec.ts"; then
        failed_suites+=("Security")
    fi
    
    # Role-based access control
    total_suites=$((total_suites + 1))
    if ! run_test_suite "rbac" "e2e/rbac.spec.ts"; then
        failed_suites+=("RBAC")
    fi
    
    # Cross-browser compatibility
    total_suites=$((total_suites + 1))
    if ! run_test_suite "cross-browser" "e2e/cross-browser.spec.ts"; then
        failed_suites+=("Cross-browser")
    fi
    
    # Data export
    total_suites=$((total_suites + 1))
    if ! run_test_suite "data-export" "e2e/data-export.spec.ts"; then
        failed_suites+=("Data Export")
    fi
    
    # API integration tests (only if real backend)
    if [ "$REAL_BACKEND" = "true" ]; then
        total_suites=$((total_suites + 1))
        if ! run_test_suite "api-integration" "e2e/api-integration.spec.ts"; then
            failed_suites+=("API Integration")
        fi
    fi
    
    # Print summary
    echo ""
    echo -e "${BLUE}Test Execution Summary${NC}"
    echo -e "${BLUE}======================${NC}"
    echo "Total suites: $total_suites"
    echo "Failed suites: ${#failed_suites[@]}"
    
    if [ ${#failed_suites[@]} -eq 0 ]; then
        print_status "All test suites passed!"
        return 0
    else
        print_error "Failed test suites:"
        for suite in "${failed_suites[@]}"; do
            echo -e "  ${RED}✗${NC} $suite"
        done
        return 1
    fi
}

# Generate consolidated report
generate_report() {
    print_info "Generating consolidated test report..."
    
    local report_file="$REPORT_DIR/$TIMESTAMP/consolidated-report.html"
    
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>AZE Gemini E2E Test Report - $TIMESTAMP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f5f5f5; padding: 20px; border-radius: 5px; }
        .suite { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .passed { border-left: 4px solid #4CAF50; }
        .failed { border-left: 4px solid #f44336; }
        .info { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>AZE Gemini E2E Test Report</h1>
        <p class="info">Generated: $TIMESTAMP</p>
        <p class="info">Environment: $TEST_ENV</p>
        <p class="info">Browser: $BROWSER</p>
        <p class="info">Base URL: $BASE_URL</p>
    </div>
EOF

    # Add links to individual suite reports
    for suite_dir in "$REPORT_DIR/$TIMESTAMP"/*/; do
        if [ -d "$suite_dir" ]; then
            suite_name=$(basename "$suite_dir")
            if [ -f "$suite_dir/html-report/index.html" ]; then
                echo "<div class=\"suite passed\">" >> "$report_file"
                echo "<h2>$suite_name</h2>" >> "$report_file"
                echo "<p><a href=\"$suite_name/html-report/index.html\">View detailed report</a></p>" >> "$report_file"
                echo "</div>" >> "$report_file"
            fi
        fi
    done
    
    echo "</body></html>" >> "$report_file"
    
    print_status "Consolidated report generated: $report_file"
}

# Cleanup function
cleanup() {
    print_info "Cleaning up..."
    stop_server
    
    # Clean up old reports (keep last 10)
    if [ -d "$REPORT_DIR" ]; then
        cd "$REPORT_DIR"
        ls -1t | tail -n +11 | xargs -r rm -rf
        cd - > /dev/null
    fi
}

# Signal handlers
trap cleanup EXIT
trap 'print_error "Test execution interrupted"; exit 1' INT TERM

# Main execution
main() {
    local test_suite=""
    local show_help=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --suite)
                test_suite="$2"
                shift 2
                ;;
            --browser)
                BROWSER="$2"
                shift 2
                ;;
            --headed)
                HEADED=true
                shift
                ;;
            --real-backend)
                REAL_BACKEND=true
                shift
                ;;
            --base-url)
                BASE_URL="$2"
                shift 2
                ;;
            --help|-h)
                show_help=true
                shift
                ;;
            *)
                print_error "Unknown option: $1"
                show_help=true
                shift
                ;;
        esac
    done
    
    if [ "$show_help" = true ]; then
        echo "Usage: $0 [OPTIONS]"
        echo ""
        echo "Options:"
        echo "  --suite SUITE       Run specific test suite (auth, time-tracking, approval-workflow, security, rbac, cross-browser, data-export, api-integration)"
        echo "  --browser BROWSER   Browser to use (chromium, firefox, webkit) [default: chromium]"
        echo "  --headed           Run tests in headed mode"
        echo "  --real-backend     Use real backend instead of mocked APIs"
        echo "  --base-url URL     Base URL for testing [default: http://localhost:3000]"
        echo "  --help, -h         Show this help message"
        echo ""
        echo "Environment variables:"
        echo "  TEST_ENV           Test environment (local, staging, production) [default: local]"
        echo "  BROWSER           Browser to use [default: chromium]"
        echo "  HEADED            Run in headed mode [default: false]"
        echo "  REAL_BACKEND      Use real backend [default: false]"
        echo "  BASE_URL          Base URL [default: http://localhost:3000]"
        exit 0
    fi
    
    # Execute test pipeline
    check_prerequisites
    install_dependencies
    setup_environment
    start_server
    
    local test_result=0
    
    if [ -n "$test_suite" ]; then
        case $test_suite in
            auth)
                run_test_suite "auth" "e2e/auth.spec.ts"
                test_result=$?
                ;;
            time-tracking)
                run_test_suite "time-tracking" "e2e/time-tracking.spec.ts"
                test_result=$?
                ;;
            approval-workflow)
                run_test_suite "approval-workflow" "e2e/approval-workflow.spec.ts"
                test_result=$?
                ;;
            security)
                run_test_suite "security" "e2e/security.spec.ts"
                test_result=$?
                ;;
            rbac)
                run_test_suite "rbac" "e2e/rbac.spec.ts"
                test_result=$?
                ;;
            cross-browser)
                run_test_suite "cross-browser" "e2e/cross-browser.spec.ts"
                test_result=$?
                ;;
            data-export)
                run_test_suite "data-export" "e2e/data-export.spec.ts"
                test_result=$?
                ;;
            api-integration)
                run_test_suite "api-integration" "e2e/api-integration.spec.ts"
                test_result=$?
                ;;
            *)
                print_error "Unknown test suite: $test_suite"
                exit 1
                ;;
        esac
    else
        run_all_tests
        test_result=$?
    fi
    
    generate_report
    
    echo ""
    if [ $test_result -eq 0 ]; then
        print_status "All tests completed successfully!"
        echo -e "📊 View reports at: ${BLUE}$REPORT_DIR/$TIMESTAMP/${NC}"
    else
        print_error "Some tests failed!"
        echo -e "📊 View reports at: ${BLUE}$REPORT_DIR/$TIMESTAMP/${NC}"
        exit 1
    fi
}

# Run main function with all arguments
main "$@"