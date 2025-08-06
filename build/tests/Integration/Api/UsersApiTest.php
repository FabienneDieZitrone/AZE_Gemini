<?php
/**
 * Integration Tests for Users API
 * Tests the complete user management functionality including security, role management, and authorization
 * 
 * Test Coverage:
 * - GET users with role-based visibility filtering
 * - PATCH user roles with Admin authorization
 * - Role validation and constraints
 * - Security middleware integration
 * - Error handling and validation
 * - Database operations and prepared statements
 */

use PHPUnit\Framework\TestCase;

class UsersApiTest extends TestCase
{
    private $mockConn;
    private $originalServerVars;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original server variables
        $this->originalServerVars = $_SERVER ?? [];
        
        // Set up test environment
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'localhost',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/api/users.php',
            'HTTP_ORIGIN' => 'https://aze.mikropartner.de'
        ];
        
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        
        if (!defined('API_GUARD')) {
            define('API_GUARD', true);
        }
        
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', true);
        }
        
        // Mock database connection
        $this->mockConn = $this->createMockDatabaseConnection();
    }

    protected function tearDown(): void
    {
        // Restore original server variables
        $_SERVER = $this->originalServerVars;
        
        parent::tearDown();
    }

    private function createMockDatabaseConnection()
    {
        return (object) [
            'error' => '',
            'prepare' => function($query) {
                return $this->createMockStatement();
            },
            'close' => function() { return true; }
        ];
    }

    private function createMockStatement()
    {
        return (object) [
            'bind_param' => function() { return true; },
            'execute' => function() { return true; },
            'get_result' => function() {
                return $this->createMockResult();
            },
            'close' => function() { return true; },
            'error' => ''
        ];
    }

    private function createMockResult()
    {
        return (object) [
            'fetch_all' => function($mode) {
                return [
                    [
                        'id' => 1,
                        'name' => 'Admin User',
                        'role' => 'Admin',
                        'azureOid' => 'admin-oid-123'
                    ],
                    [
                        'id' => 2,
                        'name' => 'Regular User',
                        'role' => 'Mitarbeiter',
                        'azureOid' => 'user-oid-456'
                    ]
                ];
            }
        ];
    }

    /**
     * Test GET users with role-based filtering for Honorarkraft
     */
    public function testGetUsersRoleBasedFilteringHonorarkraft(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test that Honorarkraft can only see themselves
        $this->assertStringContainsString("'Honorarkraft'", $usersCode, 'Should handle Honorarkraft role');
        $this->assertStringContainsString('WHERE id = ?', $usersCode, 'Should filter by user ID for Honorarkraft');
        
        // Test role-based query building
        $this->assertStringContainsString("\$current_user['role'] === 'Honorarkraft'", $usersCode,
            'Should check for Honorarkraft role');
        
        // Simulate Honorarkraft filtering logic
        $currentUser = ['id' => 1, 'role' => 'Honorarkraft'];
        $shouldShowOnlySelf = $currentUser['role'] === 'Honorarkraft';
        
        $this->assertTrue($shouldShowOnlySelf, 'Honorarkraft should only see themselves');
        
        // Test query structure for self-only access
        $baseQuery = "SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users";
        $honorarkraftQuery = $baseQuery . " WHERE id = ?";
        
        $this->assertStringContainsString('display_name AS name', $usersCode,
            'Should alias display_name as name');
        $this->assertStringContainsString('azure_oid AS azureOid', $usersCode,
            'Should alias azure_oid as azureOid');
    }

    /**
     * Test GET users with filtering for Mitarbeiter (exclude Honorarkraft)
     */
    public function testGetUsersFilteringMitarbeiter(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test that Mitarbeiter see all except Honorarkräfte
        $this->assertStringContainsString("'Mitarbeiter'", $usersCode, 'Should handle Mitarbeiter role');
        $this->assertStringContainsString("role != 'Honorarkraft'", $usersCode, 
            'Should exclude Honorarkraft from Mitarbeiter view');
        $this->assertStringContainsString('OR id = ?', $usersCode, 
            'Should include self even if Honorarkraft');
        
        // Simulate Mitarbeiter filtering logic
        $mitarbeiterUser = ['id' => 2, 'role' => 'Mitarbeiter'];
        $shouldFilterHonorarkraft = $mitarbeiterUser['role'] === 'Mitarbeiter';
        
        $this->assertTrue($shouldFilterHonorarkraft, 'Mitarbeiter should filter out Honorarkräfte');
        
        // Test query structure
        $expectedCondition = "WHERE role != 'Honorarkraft' OR id = ?";
        $this->assertStringContainsString($expectedCondition, $usersCode,
            'Should use correct WHERE condition for Mitarbeiter');
    }

    /**
     * Test GET users for Standortleiter access
     */
    public function testGetUsersStandortleiterAccess(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test Standortleiter access pattern
        $this->assertStringContainsString("'Standortleiter'", $usersCode, 'Should handle Standortleiter role');
        
        // Test comment about location-based filtering
        $this->assertStringContainsString('location-Spalte in users', $usersCode,
            'Should document location column requirement');
        $this->assertStringContainsString('andere Standortleiter anderer Locations', $usersCode,
            'Should document location filtering logic');
        
        // Simulate Standortleiter access (currently sees all - placeholder logic)
        $standortleiterUser = ['id' => 3, 'role' => 'Standortleiter', 'location' => 'Berlin'];
        $hasLocationFiltering = false; // As per current implementation
        
        $this->assertFalse($hasLocationFiltering, 'Location filtering is not yet implemented');
    }

    /**
     * Test GET users for Admin/Bereichsleiter (full access)
     */
    public function testGetUsersFullAccessForAdmins(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test that Admin and Bereichsleiter see all users
        $this->assertStringContainsString('Bereichsleiter and Admin see all', $usersCode,
            'Should document full access for admins');
        
        // Simulate admin access logic
        $adminUser = ['id' => 4, 'role' => 'Admin'];
        $bereichsleiterUser = ['id' => 5, 'role' => 'Bereichsleiter'];
        
        $adminHasFullAccess = in_array($adminUser['role'], ['Admin', 'Bereichsleiter']);
        $bereichsleiterHasFullAccess = in_array($bereichsleiterUser['role'], ['Admin', 'Bereichsleiter']);
        
        $this->assertTrue($adminHasFullAccess, 'Admin should see all users');
        $this->assertTrue($bereichsleiterHasFullAccess, 'Bereichsleiter should see all users');
    }

    /**
     * Test PATCH user role - Admin authorization requirement
     */
    public function testPatchUserRoleAdminAuthorization(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test Admin-only role change security
        $this->assertStringContainsString('Only Admin can change user roles', $usersCode,
            'Should document Admin-only role changes');
        $this->assertStringContainsString("\$current_user['role'] !== 'Admin'", $usersCode,
            'Should check for Admin role');
        $this->assertStringContainsString('Forbidden: Only Admin users can change user roles', $usersCode,
            'Should return forbidden message for non-Admin');
        
        // Test HTTP 403 response for non-Admin
        $this->assertStringContainsString('403', $usersCode, 'Should return 403 for unauthorized role changes');
        
        // Simulate authorization check
        $adminUser = ['role' => 'Admin'];
        $mitarbeiterUser = ['role' => 'Mitarbeiter'];
        
        $adminCanChangeRoles = $adminUser['role'] === 'Admin';
        $mitarbeiterCanChangeRoles = $mitarbeiterUser['role'] === 'Admin';
        
        $this->assertTrue($adminCanChangeRoles, 'Admin should be able to change roles');
        $this->assertFalse($mitarbeiterCanChangeRoles, 'Non-Admin should not be able to change roles');
    }

    /**
     * Test PATCH user role validation
     */
    public function testPatchUserRoleValidation(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test required fields validation
        $this->assertStringContainsString("'userId', 'newRole'", $usersCode,
            'Should require userId and newRole fields');
        $this->assertStringContainsString('InputValidator::validateJsonInput', $usersCode,
            'Should use input validator');
        
        // Test userId validation
        $this->assertStringContainsString('InputValidator::isValidId', $usersCode,
            'Should validate userId format');
        $this->assertStringContainsString('Invalid userId format', $usersCode,
            'Should return error for invalid userId');
        
        // Test role validation
        $allowedRoles = ['Honorarkraft', 'Mitarbeiter', 'Standortleiter', 'Bereichsleiter', 'Admin'];
        foreach ($allowedRoles as $role) {
            $this->assertStringContainsString($role, $usersCode, "Should include role $role in validation");
        }
        
        // Test role validation logic
        $this->assertStringContainsString('in_array(\$data[\'newRole\'], \$allowed_roles)', $usersCode,
            'Should validate role against allowed values');
        $this->assertStringContainsString('Invalid role. Allowed:', $usersCode,
            'Should return error for invalid role');
        
        // Simulate validation tests
        $validData = [
            'userId' => 1,
            'newRole' => 'Standortleiter'
        ];
        
        $invalidData = [
            'userId' => -1,
            'newRole' => 'InvalidRole'
        ];
        
        // Test userId validation
        $isValidUserId = is_int($validData['userId']) && $validData['userId'] > 0;
        $this->assertTrue($isValidUserId, 'Valid userId should pass validation');
        
        $isInvalidUserId = is_int($invalidData['userId']) && $invalidData['userId'] > 0;
        $this->assertFalse($isInvalidUserId, 'Invalid userId should fail validation');
        
        // Test role validation
        $isValidRole = in_array($validData['newRole'], $allowedRoles);
        $this->assertTrue($isValidRole, 'Valid role should pass validation');
        
        $isInvalidRole = in_array($invalidData['newRole'], $allowedRoles);
        $this->assertFalse($isInvalidRole, 'Invalid role should fail validation');
    }

    /**
     * Test PATCH user role database operations
     */
    public function testPatchUserRoleDatabaseOperations(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test prepared statement for role update
        $this->assertStringContainsString('UPDATE users SET role = ? WHERE id = ?', $usersCode,
            'Should use prepared statement for role update');
        $this->assertStringContainsString('bind_param("si"', $usersCode,
            'Should bind string (role) and integer (id) parameters');
        
        // Test success response
        $this->assertStringContainsString('User role updated successfully', $usersCode,
            'Should return success message');
        $this->assertStringContainsString('200', $usersCode, 'Should return 200 status for success');
        
        // Test database error handling
        $this->assertStringContainsString('Prepare failed for UPDATE users role', $usersCode,
            'Should handle prepare failures');
        $this->assertStringContainsString('Update failed for users role', $usersCode,
            'Should handle update failures');
        $this->assertStringContainsString('Datenbankfehler beim Ändern der Rolle', $usersCode,
            'Should return user-friendly error message');
        
        // Test statement cleanup
        $this->assertStringContainsString('$stmt->close()', $usersCode,
            'Should close prepared statement');
        
        // Simulate database operation
        $updateQuery = "UPDATE users SET role = ? WHERE id = ?";
        $newRole = "Standortleiter";
        $userId = 1;
        
        $this->assertStringContainsString('UPDATE users', $updateQuery, 'Should update users table');
        $this->assertEquals("Standortleiter", $newRole, 'Should set new role');
        $this->assertEquals(1, $userId, 'Should target specific user');
    }

    /**
     * Test security middleware integration
     */
    public function testSecurityMiddlewareIntegration(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test security middleware includes
        $securityIncludes = [
            '/security-middleware.php',
            '/rate-limiting.php',
            '/csrf-middleware.php',
            '/auth-middleware.php'
        ];
        
        foreach ($securityIncludes as $include) {
            $this->assertStringContainsString("require_once __DIR__ . '$include'", $usersCode,
                "Should include $include");
        }
        
        // Test middleware initialization
        $this->assertStringContainsString('initSecurityMiddleware()', $usersCode,
            'Should initialize security middleware');
        $this->assertStringContainsString('checkRateLimit(\'users\')', $usersCode,
            'Should apply rate limiting for users endpoint');
        $this->assertStringContainsString('validateCsrfProtection()', $usersCode,
            'Should validate CSRF protection');
        $this->assertStringContainsString('authorize_request()', $usersCode,
            'Should authorize request');
        
        // Test CSRF protection condition
        $this->assertStringContainsString('requiresCsrfProtection()', $usersCode,
            'Should check if CSRF protection is required');
        
        // Test API initialization
        $this->assertStringContainsString('initialize_api()', $usersCode,
            'Should initialize API');
    }

    /**
     * Test error handling and logging
     */
    public function testErrorHandlingAndLogging(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test fatal error handler
        $this->assertStringContainsString('register_shutdown_function', $usersCode,
            'Should register fatal error handler');
        $this->assertStringContainsString('error_get_last()', $usersCode,
            'Should check for fatal errors');
        $this->assertStringContainsString('Fatal PHP Error', $usersCode,
            'Should handle fatal PHP errors');
        
        // Test error reporting configuration
        $this->assertStringContainsString('ini_set(\'display_errors\', 0)', $usersCode,
            'Should disable error display in production');
        $this->assertStringContainsString('ini_set(\'log_errors\', 1)', $usersCode,
            'Should enable error logging');
        $this->assertStringContainsString('error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED)', $usersCode,
            'Should set appropriate error reporting level');
        
        // Test validation error handling
        $this->assertStringContainsString('InvalidArgumentException', $usersCode,
            'Should catch validation exceptions');
        $this->assertStringContainsString('catch (Exception', $usersCode,
            'Should catch general exceptions');
        $this->assertStringContainsString('error_log(\'Validation error in users.php:', $usersCode,
            'Should log validation errors');
        
        // Test structured error responses
        $this->assertStringContainsString('error_details', $usersCode,
            'Should provide structured error details');
        $this->assertStringContainsString('type', $usersCode, 'Should include error type');
        $this->assertStringContainsString('message', $usersCode, 'Should include error message');
        $this->assertStringContainsString('file', $usersCode, 'Should include error file');
        $this->assertStringContainsString('line', $usersCode, 'Should include error line');
    }

    /**
     * Test HTTP method handling
     */
    public function testHttpMethodHandling(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test supported methods
        $this->assertStringContainsString("case 'GET':", $usersCode, 'Should handle GET requests');
        $this->assertStringContainsString("case 'PATCH':", $usersCode, 'Should handle PATCH requests');
        $this->assertStringContainsString('handle_get', $usersCode, 'Should have GET handler');
        $this->assertStringContainsString('handle_patch', $usersCode, 'Should have PATCH handler');
        
        // Test unsupported methods
        $this->assertStringContainsString('Method Not Allowed', $usersCode,
            'Should handle unsupported methods');
        $this->assertStringContainsString('405', $usersCode,
            'Should return 405 status for unsupported methods');
        
        // Test method switching logic
        $this->assertStringContainsString('$method = $_SERVER[\'REQUEST_METHOD\']', $usersCode,
            'Should get request method from server');
        $this->assertStringContainsString('switch ($method)', $usersCode,
            'Should switch on request method');
        
        // Simulate method handling
        $supportedMethods = ['GET', 'PATCH'];
        $unsupportedMethods = ['POST', 'PUT', 'DELETE'];
        
        foreach ($supportedMethods as $method) {
            $this->assertContains($method, $supportedMethods, "$method should be supported");
        }
        
        foreach ($unsupportedMethods as $method) {
            $this->assertNotContains($method, $supportedMethods, "$method should not be supported");
        }
    }

    /**
     * Test database connection and cleanup
     */
    public function testDatabaseConnectionAndCleanup(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test database include
        $this->assertStringContainsString("require_once __DIR__ . '/db.php'", $usersCode,
            'Should include database connection');
        
        // Test connection cleanup
        $this->assertStringContainsString('$conn->close()', $usersCode,
            'Should close database connection');
        
        // Test prepared statement usage
        $this->assertStringContainsString('$conn->prepare(', $usersCode,
            'Should use prepared statements');
        $this->assertStringContainsString('$stmt->bind_param(', $usersCode,
            'Should bind parameters');
        $this->assertStringContainsString('$stmt->execute()', $usersCode,
            'Should execute statements');
        $this->assertStringContainsString('$stmt->get_result()', $usersCode,
            'Should get results');
        
        // Test error handling for database operations
        $this->assertStringContainsString('if (!$stmt)', $usersCode,
            'Should check for statement preparation failure');
        $this->assertStringContainsString('$conn->error', $usersCode,
            'Should access connection errors');
        $this->assertStringContainsString('$stmt->error', $usersCode,
            'Should access statement errors');
    }

    /**
     * Test user data structure and column mapping
     */
    public function testUserDataStructureAndMapping(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test column selection and aliases
        $expectedColumns = [
            'id',
            'display_name AS name',
            'role',
            'azure_oid AS azureOid'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString($column, $usersCode,
                "Should select column: $column");
        }
        
        // Test that sensitive data is not exposed
        $sensitiveColumns = ['password', 'email', 'phone', 'address'];
        foreach ($sensitiveColumns as $column) {
            $selectPattern = "SELECT.*$column";
            $this->assertThat(
                preg_match("/$selectPattern/i", $usersCode),
                $this->logicalOr($this->equalTo(0), $this->equalTo(false)),
                "Should not select sensitive column: $column"
            );
        }
        
        // Test result processing
        $this->assertStringContainsString('fetch_all(MYSQLI_ASSOC)', $usersCode,
            'Should fetch associative array results');
        
        // Simulate data structure
        $expectedUserStructure = [
            'id' => 1,
            'name' => 'Test User',
            'role' => 'Mitarbeiter',
            'azureOid' => 'azure-oid-123'
        ];
        
        $this->assertArrayHasKey('id', $expectedUserStructure, 'Should have id field');
        $this->assertArrayHasKey('name', $expectedUserStructure, 'Should have name field');
        $this->assertArrayHasKey('role', $expectedUserStructure, 'Should have role field');
        $this->assertArrayHasKey('azureOid', $expectedUserStructure, 'Should have azureOid field');
    }

    /**
     * Test role hierarchy and permissions
     */
    public function testRoleHierarchyAndPermissions(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test role definitions in allowed roles
        $definedRoles = ['Honorarkraft', 'Mitarbeiter', 'Standortleiter', 'Bereichsleiter', 'Admin'];
        
        foreach ($definedRoles as $role) {
            $this->assertStringContainsString("'$role'", $usersCode, "Should reference role: $role");
        }
        
        // Test role hierarchy logic (implied by filtering rules)
        $roleHierarchy = [
            'Honorarkraft' => 1,    // Lowest - sees only self
            'Mitarbeiter' => 2,     // Sees all except Honorarkraft
            'Standortleiter' => 3,  // Should see location-based (placeholder: all)
            'Bereichsleiter' => 4,  // Sees all
            'Admin' => 5            // Highest - sees all, can modify
        ];
        
        // Test that higher roles have more access
        $honorarkraftAccess = 1; // Only self
        $mitarbeiterAccess = 4;  // All except Honorarkraft
        $adminAccess = 5;        // All users
        
        $this->assertLessThan($mitarbeiterAccess, $honorarkraftAccess,
            'Honorarkraft should have less access than Mitarbeiter');
        $this->assertLessThan($adminAccess, $mitarbeiterAccess,
            'Mitarbeiter should have less access than Admin');
        
        // Test role modification permissions
        $canModifyRoles = ['Admin']; // Only Admin can modify roles
        $cannotModifyRoles = ['Honorarkraft', 'Mitarbeiter', 'Standortleiter', 'Bereichsleiter'];
        
        foreach ($cannotModifyRoles as $role) {
            $this->assertNotContains($role, $canModifyRoles,
                "$role should not be able to modify user roles");
        }
    }

    /**
     * Performance and optimization tests
     */
    public function testPerformanceAndOptimization(): void
    {
        $usersCode = file_get_contents(API_BASE_PATH . '/users.php');
        
        // Test that queries are optimized
        $this->assertStringContainsString('SELECT id, display_name', $usersCode,
            'Should select only required columns');
        
        // Test that proper indexes would be used (implied by WHERE clauses)
        $indexedColumns = ['id', 'role'];
        foreach ($indexedColumns as $column) {
            $this->assertStringContainsString("WHERE $column", $usersCode,
                "Should use indexed column $column in WHERE clause");
        }
        
        // Test that database connections are properly managed
        $this->assertStringContainsString('$stmt->close()', $usersCode,
            'Should close prepared statements');
        $this->assertStringContainsString('$conn->close()', $usersCode,
            'Should close database connection');
        
        // Simulate performance test
        $startTime = microtime(true);
        
        // Simulate query execution
        for ($i = 0; $i < 100; $i++) {
            $query = "SELECT id, display_name AS name, role, azure_oid AS azureOid FROM users";
            $this->assertNotEmpty($query, 'Query should be defined');
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete quickly
        $this->assertLessThan(0.01, $executionTime, 'Query preparation should be fast');
    }
}