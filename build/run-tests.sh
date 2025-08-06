#!/bin/bash

# AZE Gemini Test Suite Runner
# Comprehensive test execution script for achieving >80% code coverage

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
PHPUNIT_BIN="${PROJECT_ROOT}/vendor/bin/phpunit"
MIN_COVERAGE=80
TEST_RESULTS_DIR="${PROJECT_ROOT}/test-results"
COVERAGE_DIR="${PROJECT_ROOT}/coverage-php"

echo -e "${BLUE}=== AZE Gemini Test Suite Runner ===${NC}"
echo "Project Root: $PROJECT_ROOT"
echo "Target Coverage: ${MIN_COVERAGE}%"
echo ""

# Create necessary directories
mkdir -p "$TEST_RESULTS_DIR"
mkdir -p "$COVERAGE_DIR"

# Function to print section headers
print_section() {
    echo -e "\n${BLUE}=== $1 ===${NC}"
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print warning messages
print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Function to print error messages
print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Check prerequisites
print_section "Checking Prerequisites"

if ! command -v "$PHP_BIN" &> /dev/null; then
    print_error "PHP not found. Please install PHP 8.0 or higher."
    exit 1
fi

PHP_VERSION=$($PHP_BIN -v | head -n 1 | grep -oP '\d+\.\d+')
if [[ $(echo "$PHP_VERSION < 8.0" | bc -l) -eq 1 ]]; then
    print_error "PHP version $PHP_VERSION detected. PHP 8.0 or higher required."
    exit 1
fi

print_success "PHP $PHP_VERSION found"

# Check if Composer is available
if ! command -v "$COMPOSER_BIN" &> /dev/null; then
    print_warning "Composer not found. Attempting to install dependencies manually."
else
    print_success "Composer found"
    
    # Install dependencies
    print_section "Installing Dependencies"
    if [ -f "$PROJECT_ROOT/composer.json" ]; then
        cd "$PROJECT_ROOT"
        $COMPOSER_BIN install --no-interaction --optimize-autoloader
        print_success "Composer dependencies installed"
    else
        print_warning "composer.json not found"
    fi
fi

# Check PHPUnit availability
if [ ! -f "$PHPUNIT_BIN" ]; then
    print_warning "PHPUnit not found at $PHPUNIT_BIN"
    # Try to find PHPUnit globally
    if command -v phpunit &> /dev/null; then
        PHPUNIT_BIN="phpunit"
        print_success "Using global PHPUnit installation"
    else
        print_error "PHPUnit not available. Please install via Composer."
        exit 1
    fi
else
    print_success "PHPUnit found at $PHPUNIT_BIN"
fi

# Set up environment for testing
print_section "Setting Up Test Environment"

export TEST_ENVIRONMENT=testing
export API_BASE_PATH="$PROJECT_ROOT/api"

# Ensure test bootstrap can find the API files
if [ ! -d "$API_BASE_PATH" ]; then
    print_error "API directory not found at $API_BASE_PATH"
    exit 1
fi

print_success "Test environment configured"

# Function to run specific test suite
run_test_suite() {
    local suite_name="$1"
    local description="$2"
    
    echo -e "\n${YELLOW}Running $description...${NC}"
    
    if $PHPUNIT_BIN --testsuite="$suite_name" --no-coverage; then
        print_success "$description completed successfully"
        return 0
    else
        print_error "$description failed"
        return 1
    fi
}

# Run test suites
print_section "Running Test Suites"

# Track test results
FAILED_SUITES=()

# Run Security Tests (most critical)
if ! run_test_suite "Security" "Security Tests (Auth, Rate Limiting, CSRF)"; then
    FAILED_SUITES+=("Security")
fi

# Run Utility Tests
if ! run_test_suite "Utils" "Utility Tests (Validation, Helpers)"; then
    FAILED_SUITES+=("Utils")
fi

# Run API Integration Tests
if ! run_test_suite "API" "API Integration Tests (Time Entries, Users)"; then
    FAILED_SUITES+=("API")
fi

# Run all remaining unit tests
if ! run_test_suite "Unit" "Unit Tests"; then
    FAILED_SUITES+=("Unit")
fi

# Run all remaining integration tests
if ! run_test_suite "Integration" "Integration Tests"; then
    FAILED_SUITES+=("Integration")
fi

# Generate Coverage Report
print_section "Generating Coverage Report"

echo "Running full test suite with coverage analysis..."

if $PHPUNIT_BIN --coverage-html "$COVERAGE_DIR" --coverage-clover "$COVERAGE_DIR/clover.xml" --coverage-text="$COVERAGE_DIR/coverage.txt" --log-junit "$TEST_RESULTS_DIR/junit.xml"; then
    print_success "Coverage report generated"
    
    # Try to extract coverage percentage
    if [ -f "$COVERAGE_DIR/coverage.txt" ]; then
        COVERAGE_PERCENT=$(grep -E "Lines:\s+[0-9.]+%" "$COVERAGE_DIR/coverage.txt" | grep -oE "[0-9.]+" | head -1)
        
        if [ ! -z "$COVERAGE_PERCENT" ]; then
            echo -e "\n${BLUE}Coverage Summary:${NC}"
            echo "Lines: ${COVERAGE_PERCENT}%"
            
            # Check if we met the target
            if (( $(echo "$COVERAGE_PERCENT >= $MIN_COVERAGE" | bc -l) )); then
                print_success "Coverage target achieved: ${COVERAGE_PERCENT}% >= ${MIN_COVERAGE}%"
                COVERAGE_TARGET_MET=true
            else
                print_warning "Coverage target not met: ${COVERAGE_PERCENT}% < ${MIN_COVERAGE}%"
                COVERAGE_TARGET_MET=false
            fi
        else
            print_warning "Could not extract coverage percentage"
            COVERAGE_TARGET_MET=false
        fi
    else
        print_warning "Coverage text report not found"
        COVERAGE_TARGET_MET=false
    fi
    
    # Display coverage report locations
    echo -e "\n${BLUE}Coverage Reports Generated:${NC}"
    echo "HTML Report: file://$COVERAGE_DIR/index.html"
    echo "Text Report: $COVERAGE_DIR/coverage.txt"
    echo "Clover XML: $COVERAGE_DIR/clover.xml"
    
else
    print_error "Coverage report generation failed"
    COVERAGE_TARGET_MET=false
fi

# Generate Test Documentation
print_section "Generating Test Documentation"

if [ -f "$TEST_RESULTS_DIR/testdox.html" ]; then
    print_success "Test documentation generated: $TEST_RESULTS_DIR/testdox.html"
fi

# Summary
print_section "Test Execution Summary"

echo "Test Results:"
if [ ${#FAILED_SUITES[@]} -eq 0 ]; then
    print_success "All test suites passed"
    TESTS_PASSED=true
else
    print_error "Failed test suites: ${FAILED_SUITES[*]}"
    TESTS_PASSED=false
fi

if [ "$COVERAGE_TARGET_MET" = true ]; then
    print_success "Coverage target met"
else
    print_warning "Coverage target not met"
fi

echo -e "\n${BLUE}Generated Reports:${NC}"
echo "Test Results: $TEST_RESULTS_DIR/"
echo "Coverage Reports: $COVERAGE_DIR/"

# Final exit status
if [ "$TESTS_PASSED" = true ] && [ "$COVERAGE_TARGET_MET" = true ]; then
    echo -e "\n${GREEN}🎉 All tests passed and coverage target achieved!${NC}"
    exit 0
elif [ "$TESTS_PASSED" = true ]; then
    echo -e "\n${YELLOW}⚠ Tests passed but coverage target not met${NC}"
    exit 1
else
    echo -e "\n${RED}❌ Tests failed${NC}"
    exit 1
fi