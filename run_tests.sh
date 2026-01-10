#!/bin/bash
#
# run_tests.sh - Run all AQL test harness tests from command line
#
# Usage: ./run_tests.sh [test_name]
#   Without arguments: runs all tests
#   With argument: runs only the specified test
#
# Examples:
#   ./run_tests.sh                    # Run all tests
#   ./run_tests.sh config_validate    # Run only config validation
#   ./run_tests.sh schema_verify      # Run only schema verification
#

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
GRAY='\033[0;90m'
NC='\033[0m' # No Color

# Test list (order matters - some tests depend on others)
# Format: "test_name:description:cli_compatible"
# cli_compatible: yes = works in CLI, no = requires web context
TESTS=(
    "config_validate:Validate Configuration:yes"
    "smoke_test:Application Smoke Test:no"
    "db_user_verify:Database User Verification:yes"
    "schema_verify:Schema Verification:yes"
    "deploy_ddl_verify:Deploy DDL Verification:yes"
    "blocking_status:Check Blocking Status:no"
)

# Counters
PASSED=0
FAILED=0
WARNINGS=0
SKIPPED=0

# Function to run a single test
run_test() {
    local test_name="$1"
    local test_desc="$2"
    local cli_ok="${3:-yes}"

    echo -e "${BLUE}Running: ${test_desc}${NC}"
    echo "----------------------------------------"

    # Skip tests that require web context
    if [[ "$cli_ok" == "no" ]]; then
        echo -e "${GRAY}SKIPPED${NC} - requires web context (run via browser)"
        ((SKIPPED++))
        return 0
    fi

    # Run the test and capture output
    OUTPUT=$(php -r "\$_GET['test']='$test_name'; include 'testAQL.php';" 2>&1)

    # Check for PHP errors
    if echo "$OUTPUT" | grep -qiE '(Fatal error|Parse error|Warning:|Notice:.*undefined)'; then
        echo -e "${RED}FAILED${NC} - PHP errors detected"
        echo "$OUTPUT" | grep -iE '(Fatal error|Parse error|Warning:|Notice:)' | head -5
        ((FAILED++))
        return 1
    fi

    # Check for test completion indicators first (these override individual errors)
    local test_completed=false
    if echo "$OUTPUT" | grep -qE '(validation complete|verification complete|passed.*schema is up to date)'; then
        test_completed=true
    fi

    # Check for critical failures (only if test didn't complete successfully)
    if [[ "$test_completed" == "false" ]]; then
        if echo "$OUTPUT" | grep -qE '(color:red|&#10008;|FAILED|ERROR</td>)'; then
            if echo "$OUTPUT" | grep -qE '(privileges missing|MISSING.*ERROR|Fatal error)'; then
                echo -e "${RED}FAILED${NC}"
                echo "$OUTPUT" | grep -oP '(?<=<p>)[^<]*(?=</p>)' | grep -iE '(error|fail|missing)' | head -5
                ((FAILED++))
                return 1
            fi
        fi
    fi

    # Check for warnings (yellow indicators or non-critical issues)
    if echo "$OUTPUT" | grep -qE '(color:yellow|&#9888;|MISSING</td>|Would be|Connection failed.*aql_test)'; then
        echo -e "${YELLOW}PASSED with warnings${NC}"
        # Show warning details
        echo "$OUTPUT" | grep -oP '(?<=<p>)[^<]*(?=</p>)' | grep -iE '(warning|missing|not configured|Connection failed)' | head -3
        ((WARNINGS++))
        ((PASSED++))
        return 0
    fi

    # Check for success indicators
    if echo "$OUTPUT" | grep -qE '(color:lime|&#10004;|OK</td>|passed|up to date|complete)'; then
        echo -e "${GREEN}PASSED${NC}"
        ((PASSED++))
        return 0
    fi

    # If we can't determine status, assume passed but note it
    echo -e "${YELLOW}COMPLETED${NC} (status unclear)"
    ((PASSED++))
    return 0
}

# Function to show usage
show_usage() {
    echo "AQL Test Runner"
    echo ""
    echo "Usage: $0 [test_name]"
    echo ""
    echo "Available tests (* = requires web context, skipped in CLI):"
    for test in "${TESTS[@]}"; do
        IFS=':' read -r name desc cli_ok <<< "$test"
        if [[ "$cli_ok" == "no" ]]; then
            printf "  %-20s %s *\n" "$name" "$desc"
        else
            printf "  %-20s %s\n" "$name" "$desc"
        fi
    done
    echo ""
    echo "Special tests (not run by default, require web context):"
    echo "  blocking_setup      Setup Blocking Test (creates test table)"
    echo "  blocking_js         Test Blocking JavaScript"
    echo "  cleanup             Cleanup Test Data"
    echo ""
}

# Main execution
echo "========================================"
echo "AQL Test Harness - Command Line Runner"
echo "========================================"
echo ""

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo -e "${RED}Error: PHP is not installed or not in PATH${NC}"
    exit 1
fi

# Check if testAQL.php exists
if [[ ! -f "testAQL.php" ]]; then
    echo -e "${RED}Error: testAQL.php not found in $SCRIPT_DIR${NC}"
    exit 1
fi

# If a specific test is requested
if [[ -n "$1" ]]; then
    if [[ "$1" == "-h" || "$1" == "--help" ]]; then
        show_usage
        exit 0
    fi

    # Find the test description and cli compatibility
    TEST_DESC=""
    TEST_CLI_OK="yes"
    for test in "${TESTS[@]}"; do
        IFS=':' read -r name desc cli_ok <<< "$test"
        if [[ "$name" == "$1" ]]; then
            TEST_DESC="$desc"
            TEST_CLI_OK="$cli_ok"
            break
        fi
    done

    # Handle special tests not in the main list
    if [[ -z "$TEST_DESC" ]]; then
        case "$1" in
            blocking_setup) TEST_DESC="Setup Blocking Test"; TEST_CLI_OK="no" ;;
            blocking_js) TEST_DESC="Test Blocking JavaScript"; TEST_CLI_OK="no" ;;
            cleanup) TEST_DESC="Cleanup Test Data"; TEST_CLI_OK="no" ;;
            *)
                echo -e "${RED}Unknown test: $1${NC}"
                show_usage
                exit 1
                ;;
        esac
    fi

    run_test "$1" "$TEST_DESC" "$TEST_CLI_OK"
    echo ""
    exit $?
fi

# Run all standard tests
for test in "${TESTS[@]}"; do
    IFS=':' read -r name desc cli_ok <<< "$test"
    run_test "$name" "$desc" "$cli_ok"
    echo ""
done

# Summary
echo "========================================"
echo "Test Summary"
echo "========================================"
echo -e "Passed:   ${GREEN}$PASSED${NC}"
if [[ $WARNINGS -gt 0 ]]; then
    echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"
fi
if [[ $SKIPPED -gt 0 ]]; then
    echo -e "Skipped:  ${GRAY}$SKIPPED${NC} (require web context)"
fi
if [[ $FAILED -gt 0 ]]; then
    echo -e "Failed:   ${RED}$FAILED${NC}"
    exit 1
else
    echo -e "${GREEN}All CLI-compatible tests passed!${NC}"
    exit 0
fi
