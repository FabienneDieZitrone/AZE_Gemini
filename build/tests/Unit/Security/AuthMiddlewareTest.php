<?php
/**
 * Unit Tests for Authorization Middleware
 * Tests the comprehensive role-based access control (RBAC) system
 * 
 * Test Coverage:
 * - Endpoint permissions matrix validation
 * - Role-based access control
 * - HTTP method-specific permissions
 * - Session validation and authorization
 * - Security logging and error handling
 */

use PHPUnit\Framework\TestCase;

class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear session and globals
        $_SESSION = [];
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        
        // Set up basic server environment
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SCRIPT_NAME'] = '/api/test-endpoint.php';
        
        // Mock database connection if needed
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $_SESSION = [];
        $_SERVER = [];
        
        parent::tearDown();
    }

    /**
     * Test the endpoint permissions matrix structure
     */
    public function testEndpointPermissionsMatrixStructure(): void
    {
        // Load the auth middleware to get the permissions matrix
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $this->assertTrue(defined('ENDPOINT_PERMISSIONS'), 'ENDPOINT_PERMISSIONS constant should be defined');
        
        $permissions = ENDPOINT_PERMISSIONS;
        $this->assertIsArray($permissions, 'Permissions should be an array');
        
        // Test that public endpoints are defined correctly
        $publicEndpoints = ['auth-status.php', 'login.php', 'auth-callback.php', 'auth-logout.php', 'csrf-protection.php'];
        foreach ($publicEndpoints as $endpoint) {
            $this->assertArrayHasKey($endpoint, $permissions, "Public endpoint $endpoint should be defined");
            $this->assertNull($permissions[$endpoint], "Public endpoint $endpoint should have null permissions");
        }
        
        // Test that protected endpoints have proper role definitions
        $protectedEndpoints = ['users.php', 'time-entries.php', 'approvals.php', 'masterdata.php', 'settings.php'];
        foreach ($protectedEndpoints as $endpoint) {
            $this->assertArrayHasKey($endpoint, $permissions, "Protected endpoint $endpoint should be defined");
            $this->assertNotNull($permissions[$endpoint], "Protected endpoint $endpoint should have role permissions");
        }
    }

    /**
     * Test role hierarchy and permissions
     */
    public function testRolePermissions(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $permissions = ENDPOINT_PERMISSIONS;
        $validRoles = ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'];
        
        // Test users.php permissions structure
        $usersPermissions = $permissions['users.php'];
        $this->assertIsArray($usersPermissions, 'Users permissions should be an array');
        $this->assertArrayHasKey('GET', $usersPermissions, 'Users should have GET permissions');
        $this->assertArrayHasKey('PATCH', $usersPermissions, 'Users should have PATCH permissions');
        
        // Test that only Admin can PATCH users
        $this->assertEquals(['Admin'], $usersPermissions['PATCH'], 'Only Admin should be able to PATCH users');
        
        // Test that all roles can GET users
        $getUserRoles = $usersPermissions['GET'];
        foreach ($validRoles as $role) {
            $this->assertContains($role, $getUserRoles, "Role $role should be able to GET users");
        }
        
        // Test settings endpoint - only Admin should modify
        $settingsPermissions = $permissions['settings.php'];
        $this->assertEquals(['Admin'], $settingsPermissions['PUT'], 'Only Admin should be able to modify settings');
    }

    /**
     * Test checkEndpointPermission function with various scenarios
     */
    public function testCheckEndpointPermissionFunction(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        // Test Admin user accessing users endpoint via GET
        $adminUser = ['role' => 'Admin', 'id' => 1, 'username' => 'admin'];
        $result = checkEndpointPermission($adminUser, '/api/users.php', 'GET');
        $this->assertTrue($result, 'Admin should be able to GET users');
        
        // Test Admin user accessing users endpoint via PATCH
        $result = checkEndpointPermission($adminUser, '/api/users.php', 'PATCH');
        $this->assertTrue($result, 'Admin should be able to PATCH users');
        
        // Test Mitarbeiter user accessing users endpoint via PATCH
        $mitarbeiterUser = ['role' => 'Mitarbeiter', 'id' => 2, 'username' => 'mitarbeiter'];
        $result = checkEndpointPermission($mitarbeiterUser, '/api/users.php', 'PATCH');
        $this->assertFalse($result, 'Mitarbeiter should NOT be able to PATCH users');
        
        // Test Mitarbeiter user accessing users endpoint via GET
        $result = checkEndpointPermission($mitarbeiterUser, '/api/users.php', 'GET');
        $this->assertTrue($result, 'Mitarbeiter should be able to GET users');
        
        // Test access to public endpoint
        $result = checkEndpointPermission($mitarbeiterUser, '/api/auth-status.php', 'GET');
        $this->assertTrue($result, 'Any user should be able to access public endpoints');
        
        // Test access to unknown endpoint (whitelist approach)
        $result = checkEndpointPermission($adminUser, '/api/unknown-endpoint.php', 'GET');
        $this->assertFalse($result, 'Unknown endpoints should be denied by default');
    }

    /**
     * Test method-specific permissions
     */
    public function testMethodSpecificPermissions(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $permissions = ENDPOINT_PERMISSIONS;
        
        // Test time-entries endpoint has different permissions for different methods
        $timeEntriesPermissions = $permissions['time-entries.php'];
        $this->assertArrayHasKey('GET', $timeEntriesPermissions);
        $this->assertArrayHasKey('POST', $timeEntriesPermissions);
        $this->assertArrayHasKey('PUT', $timeEntriesPermissions);
        $this->assertArrayHasKey('DELETE', $timeEntriesPermissions);
        
        // Test that DELETE is more restricted than GET/POST
        $deleteRoles = $timeEntriesPermissions['DELETE'];
        $getRoles = $timeEntriesPermissions['GET'];
        
        $this->assertNotContains('Mitarbeiter', $deleteRoles, 'Mitarbeiter should not be able to DELETE time entries');
        $this->assertNotContains('Honorarkraft', $deleteRoles, 'Honorarkraft should not be able to DELETE time entries');
        $this->assertContains('Admin', $deleteRoles, 'Admin should be able to DELETE time entries');
        $this->assertContains('Standortleiter', $deleteRoles, 'Standortleiter should be able to DELETE time entries');
    }

    /**
     * Test user without role
     */
    public function testUserWithoutRole(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $userWithoutRole = ['id' => 1, 'username' => 'user_without_role'];
        $result = checkEndpointPermission($userWithoutRole, '/api/users.php', 'GET');
        
        $this->assertFalse($result, 'User without role should be denied access');
    }

    /**
     * Test role helper functions
     */
    public function testRoleHelperFunctions(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $adminUser = ['role' => 'Admin'];
        $standortleiterUser = ['role' => 'Standortleiter'];
        $mitarbeiterUser = ['role' => 'Mitarbeiter'];
        $honorarkraftUser = ['role' => 'Honorarkraft'];
        
        // Test userIsAdmin
        $this->assertTrue(userIsAdmin($adminUser), 'Admin user should be identified as admin');
        $this->assertFalse(userIsAdmin($standortleiterUser), 'Non-admin user should not be identified as admin');
        
        // Test userHasRole
        $this->assertTrue(userHasRole($adminUser, 'Admin'), 'Admin user should have Admin role');
        $this->assertFalse(userHasRole($adminUser, 'Mitarbeiter'), 'Admin user should not have Mitarbeiter role');
        
        // Test userHasAnyRole
        $allowedRoles = ['Admin', 'Bereichsleiter'];
        $this->assertTrue(userHasAnyRole($adminUser, $allowedRoles), 'Admin should have any of the allowed roles');
        $this->assertFalse(userHasAnyRole($mitarbeiterUser, $allowedRoles), 'Mitarbeiter should not have any of the allowed roles');
        
        // Test userIsAtLeastStandortleiter
        $this->assertTrue(userIsAtLeastStandortleiter($adminUser), 'Admin should be at least Standortleiter');
        $this->assertTrue(userIsAtLeastStandortleiter($standortleiterUser), 'Standortleiter should be at least Standortleiter');
        $this->assertFalse(userIsAtLeastStandortleiter($mitarbeiterUser), 'Mitarbeiter should not be at least Standortleiter');
        $this->assertFalse(userIsAtLeastStandortleiter($honorarkraftUser), 'Honorarkraft should not be at least Standortleiter');
    }

    /**
     * Test endpoint path normalization
     */
    public function testEndpointPathNormalization(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $user = ['role' => 'Admin'];
        
        // Test that full paths are normalized to filename
        $result1 = checkEndpointPermission($user, '/api/users.php', 'GET');
        $result2 = checkEndpointPermission($user, '/full/path/to/api/users.php', 'GET');
        $result3 = checkEndpointPermission($user, 'users.php', 'GET');
        
        $this->assertTrue($result1, 'Standard path should work');
        $this->assertTrue($result2, 'Full path should be normalized to filename');
        $this->assertTrue($result3, 'Filename only should work');
    }

    /**
     * Test security logging scenarios
     */
    public function testSecurityLogging(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        // Capture error logs
        $originalErrorLog = ini_get('error_log');
        $testLogFile = '/tmp/test_error.log';
        ini_set('error_log', $testLogFile);
        
        // Test unknown endpoint access (should be logged)
        $user = ['role' => 'Admin'];
        $result = checkEndpointPermission($user, 'unknown-endpoint.php', 'GET');
        $this->assertFalse($result, 'Unknown endpoint should be denied');
        
        // Test unauthorized method access (should be logged)
        $user = ['role' => 'Mitarbeiter'];
        $result = checkEndpointPermission($user, 'users.php', 'PATCH');
        $this->assertFalse($result, 'Unauthorized method should be denied');
        
        // Restore original error log
        ini_set('error_log', $originalErrorLog);
        
        // Clean up test log file
        if (file_exists($testLogFile)) {
            unlink($testLogFile);
        }
    }

    /**
     * Test complex role scenarios for approvals endpoint
     */
    public function testApprovalsEndpointRoles(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $permissions = ENDPOINT_PERMISSIONS;
        $approvalsPermissions = $permissions['approvals.php'];
        
        // Test that all roles can GET approvals
        $getRoles = $approvalsPermissions['GET'];
        $allRoles = ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'];
        foreach ($allRoles as $role) {
            $this->assertContains($role, $getRoles, "Role $role should be able to GET approvals");
        }
        
        // Test that only management roles can POST/PUT approvals
        $postRoles = $approvalsPermissions['POST'];
        $putRoles = $approvalsPermissions['PUT'];
        $managementRoles = ['Admin', 'Bereichsleiter', 'Standortleiter'];
        
        $this->assertEquals($managementRoles, $postRoles, 'Only management roles should POST approvals');
        $this->assertEquals($managementRoles, $putRoles, 'Only management roles should PUT approvals');
        
        // Test individual role permissions
        $mitarbeiterUser = ['role' => 'Mitarbeiter'];
        $standortleiterUser = ['role' => 'Standortleiter'];
        
        $this->assertTrue(checkEndpointPermission($mitarbeiterUser, 'approvals.php', 'GET'));
        $this->assertFalse(checkEndpointPermission($mitarbeiterUser, 'approvals.php', 'POST'));
        $this->assertTrue(checkEndpointPermission($standortleiterUser, 'approvals.php', 'POST'));
    }

    /**
     * Test logs endpoint special permissions
     */
    public function testLogsEndpointPermissions(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $permissions = ENDPOINT_PERMISSIONS;
        $logsPermissions = $permissions['logs.php'];
        
        // Only Admin can GET logs
        $this->assertEquals(['Admin'], $logsPermissions['GET'], 'Only Admin should read logs');
        
        // All authenticated users can POST logs (for error reporting)
        $postRoles = $logsPermissions['POST'];
        $allRoles = ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'];
        $this->assertEquals($allRoles, $postRoles, 'All users should be able to POST logs');
        
        // Test actual permissions
        $adminUser = ['role' => 'Admin'];
        $mitarbeiterUser = ['role' => 'Mitarbeiter'];
        
        $this->assertTrue(checkEndpointPermission($adminUser, 'logs.php', 'GET'));
        $this->assertFalse(checkEndpointPermission($mitarbeiterUser, 'logs.php', 'GET'));
        $this->assertTrue(checkEndpointPermission($mitarbeiterUser, 'logs.php', 'POST'));
    }

    /**
     * Test MFA endpoint permissions
     */
    public function testMFAEndpointPermissions(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $permissions = ENDPOINT_PERMISSIONS;
        
        // Test MFA setup permissions
        $mfaSetupRoles = $permissions['mfa/setup.php'];
        $mfaVerifyRoles = $permissions['mfa/verify.php'];
        $allRoles = ['Admin', 'Bereichsleiter', 'Standortleiter', 'Mitarbeiter', 'Honorarkraft'];
        
        $this->assertEquals($allRoles, $mfaSetupRoles, 'All authenticated users should access MFA setup');
        $this->assertEquals($allRoles, $mfaVerifyRoles, 'All authenticated users should access MFA verify');
        
        // Test actual permissions
        $honorarkraftUser = ['role' => 'Honorarkraft'];
        $this->assertTrue(checkEndpointPermission($honorarkraftUser, 'mfa/setup.php', 'POST'));
        $this->assertTrue(checkEndpointPermission($honorarkraftUser, 'mfa/verify.php', 'POST'));
    }

    /**
     * Test masterdata endpoint permissions
     */
    public function testMasterdataEndpointPermissions(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $permissions = ENDPOINT_PERMISSIONS;
        $masterdataPermissions = $permissions['masterdata.php'];
        
        // Test GET permissions (Admin, Bereichsleiter, Standortleiter)
        $getRoles = $masterdataPermissions['GET'];
        $expectedGetRoles = ['Admin', 'Bereichsleiter', 'Standortleiter'];
        $this->assertEquals($expectedGetRoles, $getRoles, 'Only management roles should GET masterdata');
        
        // Test PUT permissions (Admin, Bereichsleiter)
        $putRoles = $masterdataPermissions['PUT'];
        $expectedPutRoles = ['Admin', 'Bereichsleiter'];
        $this->assertEquals($expectedPutRoles, $putRoles, 'Only Admin and Bereichsleiter should PUT masterdata');
        
        // Test individual permissions
        $mitarbeiterUser = ['role' => 'Mitarbeiter'];
        $standortleiterUser = ['role' => 'Standortleiter'];
        $bereichsleiterUser = ['role' => 'Bereichsleiter'];
        
        $this->assertFalse(checkEndpointPermission($mitarbeiterUser, 'masterdata.php', 'GET'));
        $this->assertTrue(checkEndpointPermission($standortleiterUser, 'masterdata.php', 'GET'));
        $this->assertFalse(checkEndpointPermission($standortleiterUser, 'masterdata.php', 'PUT'));
        $this->assertTrue(checkEndpointPermission($bereichsleiterUser, 'masterdata.php', 'PUT'));
    }

    /**
     * Performance test for permission checking
     */
    public function testPermissionCheckingPerformance(): void
    {
        require_once API_BASE_PATH . '/auth-middleware.php';
        
        $user = ['role' => 'Admin'];
        $startTime = microtime(true);
        
        // Run permission checks 1000 times
        for ($i = 0; $i < 1000; $i++) {
            checkEndpointPermission($user, 'users.php', 'GET');
            checkEndpointPermission($user, 'time-entries.php', 'POST');
            checkEndpointPermission($user, 'approvals.php', 'PUT');
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete 3000 checks in less than 0.1 seconds
        $this->assertLessThan(0.1, $executionTime, 'Permission checking should be fast');
    }
}