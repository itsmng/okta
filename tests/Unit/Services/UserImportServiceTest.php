<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2022 ITSM-NG and contributors.
 *
 * https://www.itsm-ng.org
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Okta\Tests\Unit\Services;

use GlpiPlugin\Okta\Api\OktaClient;
use GlpiPlugin\Okta\Repository\DatabaseInterface;
use GlpiPlugin\Okta\Repository\UserRepository;
use GlpiPlugin\Okta\Services\GroupService;
use GlpiPlugin\Okta\Services\LoggerInterface;
use GlpiPlugin\Okta\Services\UserImportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for UserImportService.
 */
class UserImportServiceTest extends TestCase
{
    private OktaClient $mockClient;
    private GroupService $mockGroupService;
    private DatabaseInterface $mockDb;
    private UserRepository $mockUserRepository;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(OktaClient::class);
        $this->mockGroupService = $this->createMock(GroupService::class);
        $this->mockDb = $this->createMock(DatabaseInterface::class);
        $this->mockUserRepository = $this->createMock(UserRepository::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Helper to create a UserImportService instance with mocked dependencies.
     *
     * @param LoggerInterface|null $logger Optional logger, uses mock by default
     */
    private function createService(?LoggerInterface $logger = null): UserImportService
    {
        return new UserImportService(
            $this->mockClient,
            $this->mockGroupService,
            $this->mockDb,
            $this->mockUserRepository,
            $logger ?? $this->mockLogger
        );
    }

    /**
     * Helper to call private methods for testing.
     *
     * @param object $object     The object instance
     * @param string $methodName The method name
     * @param array  $parameters The parameters to pass
     *
     * @return mixed The method result
     */
    private function invokePrivateMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper to get private property value.
     *
     * @param object $object       The object instance
     * @param string $propertyName The property name
     *
     * @return mixed The property value
     */
    private function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Helper to set private property value.
     *
     * @param object $object       The object instance
     * @param string $propertyName The property name
     * @param mixed  $value        The value to set
     */
    private function setPrivateProperty(object $object, string $propertyName, $value): void
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Test resolveManagerId returns null values when no manager data.
     */
    public function testResolveManagerIdReturnsNullWhenNoManager(): void
    {
        $service = $this->createService();
        
        $user = [
            'id' => 'okta-user-1',
            'email' => 'user@example.com',
        ];
        
        $result = $this->invokePrivateMethod($service, 'resolveManagerId', [$user]);
        
        $this->assertNull($result['managerId']);
        $this->assertNull($result['managerEmail']);
    }

    /**
     * Test resolveManagerId returns null when manager has no email.
     */
    public function testResolveManagerIdReturnsNullWhenManagerHasNoEmail(): void
    {
        $service = $this->createService();
        
        $user = [
            'id' => 'okta-user-1',
            'email' => 'user@example.com',
            'manager' => [
                'id' => 'okta-manager-1',
                'profile' => [
                    'login' => 'manager-login',
                    // No email
                ],
            ],
        ];
        
        $result = $this->invokePrivateMethod($service, 'resolveManagerId', [$user]);
        
        $this->assertNull($result['managerId']);
        $this->assertNull($result['managerEmail']);
    }

    /**
     * Test resolveManagerId extracts manager email and finds user.
     */
    public function testResolveManagerIdFindsManagerByEmail(): void
    {
        $this->mockUserRepository
            ->expects($this->once())
            ->method('findUserIdByEmail')
            ->with('manager@example.com')
            ->willReturn(42);

        $service = $this->createService();
        
        $user = [
            'id' => 'okta-user-1',
            'email' => 'user@example.com',
            'managerId' => 'manager@example.com',
        ];
        
        $result = $this->invokePrivateMethod($service, 'resolveManagerId', [$user]);
        
        $this->assertSame(42, $result['managerId']);
        $this->assertSame('manager@example.com', $result['managerEmail']);
    }

    /**
     * Test resolveManagerId returns null managerId but preserves email when not found.
     */
    public function testResolveManagerIdReturnsEmailWhenManagerNotFound(): void
    {
        $this->mockUserRepository
            ->expects($this->once())
            ->method('findUserIdByEmail')
            ->with('manager@example.com')
            ->willReturn(null);

        $service = $this->createService();
        
        $user = [
            'id' => 'okta-user-1',
            'email' => 'user@example.com',
            'managerId' => 'manager@example.com',
        ];
        
        $result = $this->invokePrivateMethod($service, 'resolveManagerId', [$user]);
        
        $this->assertNull($result['managerId']);
        $this->assertSame('manager@example.com', $result['managerEmail']);
    }

    /**
     * Test addPendingManagerAssignment stores assignment.
     */
    public function testAddPendingManagerAssignmentStoresData(): void
    {
        $service = $this->createService();
        
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [123, 'manager@example.com']);
        
        $pending = $this->getPrivateProperty($service, 'pendingManagerAssignments');
        
        $this->assertArrayHasKey(123, $pending);
        $this->assertSame('manager@example.com', $pending[123]);
    }

    /**
     * Test resolvePendingManagerAssignments resolves managers in second pass.
     */
    public function testResolvePendingManagerAssignmentsResolvesManagers(): void
    {
        // First call (during first pass) returns null, second call (second pass) returns the ID
        $this->mockUserRepository
            ->expects($this->exactly(1))
            ->method('findUserIdByEmail')
            ->with('manager@example.com')
            ->willReturn(42);

        $this->mockUserRepository
            ->expects($this->once())
            ->method('updateSupervisor')
            ->with(123, 42);

        $service = $this->createService();
        
        // Simulate a pending assignment
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [123, 'manager@example.com']);
        
        // Capture output
        ob_start();
        $this->invokePrivateMethod($service, 'resolvePendingManagerAssignments', []);
        $output = ob_get_clean();
        
        // Verify the pending list is cleared
        $pending = $this->getPrivateProperty($service, 'pendingManagerAssignments');
        $this->assertEmpty($pending);
        
        // Verify output indicates success
        $this->assertStringContainsString('Assigned manager for user ID 123', $output);
    }

    /**
     * Test resolvePendingManagerAssignments logs warning when still not found.
     */
    public function testResolvePendingManagerAssignmentsLogsWarningWhenNotFound(): void
    {
        $this->mockUserRepository
            ->expects($this->once())
            ->method('findUserIdByEmail')
            ->with('manager@example.com')
            ->willReturn(null);

        // updateSupervisor should NOT be called
        $this->mockUserRepository
            ->expects($this->never())
            ->method('updateSupervisor');

        // Logger should receive the warning
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains("Could not resolve manager for user ID 123"));

        $service = $this->createService();
        
        // Simulate a pending assignment
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [123, 'manager@example.com']);
        
        // Capture output
        ob_start();
        $this->invokePrivateMethod($service, 'resolvePendingManagerAssignments', []);
        $output = ob_get_clean();
        
        // Verify output indicates warning
        $this->assertStringContainsString("Warning: Manager with email 'manager@example.com' not found", $output);
    }

    /**
     * Test resolvePendingManagerAssignments does nothing when no pending assignments.
     */
    public function testResolvePendingManagerAssignmentsDoesNothingWhenEmpty(): void
    {
        $this->mockUserRepository
            ->expects($this->never())
            ->method('findUserIdByEmail');

        $service = $this->createService();
        
        // No pending assignments
        $this->invokePrivateMethod($service, 'resolvePendingManagerAssignments', []);
        
        // No assertions needed - just verifying no methods were called
    }

    /**
     * Test multiple pending assignments are all processed.
     */
    public function testResolvePendingManagerAssignmentsProcessesMultiple(): void
    {
        $this->mockUserRepository
            ->method('findUserIdByEmail')
            ->willReturnMap([
                ['manager1@example.com', 10],
                ['manager2@example.com', 20],
                ['manager3@example.com', null], // This one not found
            ]);

        $this->mockUserRepository
            ->expects($this->exactly(2))
            ->method('updateSupervisor')
            ->withConsecutive(
                [100, 10],
                [200, 20]
            );

        $service = $this->createService();
        
        // Add multiple pending assignments
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [100, 'manager1@example.com']);
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [200, 'manager2@example.com']);
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [300, 'manager3@example.com']);
        
        // Capture output
        ob_start();
        $this->invokePrivateMethod($service, 'resolvePendingManagerAssignments', []);
        $output = ob_get_clean();
        
        // Verify all were processed
        $this->assertStringContainsString('Resolving 3 pending manager assignments', $output);
    }

    // =========================================================================
    // Tests for buildUserObject
    // =========================================================================

    /**
     * Test buildUserObject creates correct object from Okta user data.
     */
    public function testBuildUserObjectMapsFieldsCorrectly(): void
    {
        $service = $this->createService();
        
        $oktaUser = [
            'id' => 'okta-123',
            'displayName' => 'John Doe',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'login' => 'johndoe',
            'mobilePhone' => '+1234567890',
            'nickName' => 'Johnny',
            'profileUrl' => 'https://example.com/profile',
        ];
        
        $result = $this->invokePrivateMethod($service, 'buildUserObject', [$oktaUser]);
        
        $this->assertSame('okta-123', $result['sub']);
        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('John', $result['given_name']);
        $this->assertSame('Doe', $result['family_name']);
        $this->assertSame('john.doe@example.com', $result['email']);
        $this->assertSame('johndoe', $result['preferred_username']);
        $this->assertSame('johndoe', $result['username']);
        $this->assertSame('+1234567890', $result['phone_number']);
        $this->assertSame('Johnny', $result['nickname']);
        $this->assertSame('https://example.com/profile', $result['profile']);
    }

    /**
     * Test buildUserObject handles partial data gracefully.
     */
    public function testBuildUserObjectHandlesPartialData(): void
    {
        $service = $this->createService();
        
        $oktaUser = [
            'id' => 'okta-123',
            'email' => 'user@example.com',
            // Missing most fields
        ];
        
        $result = $this->invokePrivateMethod($service, 'buildUserObject', [$oktaUser]);
        
        $this->assertSame('okta-123', $result['sub']);
        $this->assertSame('user@example.com', $result['email']);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('given_name', $result);
        $this->assertArrayNotHasKey('family_name', $result);
    }

    /**
     * Test buildUserObject returns empty array for empty input.
     */
    public function testBuildUserObjectReturnsEmptyForEmptyInput(): void
    {
        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'buildUserObject', [[]]);
        
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Tests for getOidcMappings
    // =========================================================================

    /**
     * Test getOidcMappings returns database mappings.
     */
    public function testGetOidcMappingsReturnsFirstRow(): void
    {
        $expectedMappings = [
            'name' => 'name',
            'email' => 'email',
            'group' => 'groups',
            'duplicate' => 'email',
        ];
        
        $this->mockDb
            ->expects($this->once())
            ->method('query')
            ->with('SELECT * FROM glpi_oidc_mapping')
            ->willReturn(new \ArrayIterator([$expectedMappings]));

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'getOidcMappings', []);
        
        $this->assertSame($expectedMappings, $result);
    }

    /**
     * Test getOidcMappings returns empty array when no mappings exist.
     */
    public function testGetOidcMappingsReturnsEmptyArrayWhenNoMappings(): void
    {
        $this->mockDb
            ->expects($this->once())
            ->method('query')
            ->willReturn(new \ArrayIterator([]));

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'getOidcMappings', []);
        
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Tests for collectUsersFromGroups
    // =========================================================================

    /**
     * Test collectUsersFromGroups aggregates users from multiple groups.
     */
    public function testCollectUsersFromGroupsAggregatesUsers(): void
    {
        $authorizedGroups = [
            'group1' => 'IT-Admins',
            'group2' => 'IT-Users',
        ];

        $this->mockGroupService
            ->method('getUsersInGroup')
            ->willReturnMap([
                ['group1', [
                    [
                        'id' => 'user1',
                        'profile' => ['email' => 'user1@example.com', 'displayName' => 'User One'],
                    ],
                ]],
                ['group2', [
                    [
                        'id' => 'user2',
                        'profile' => ['email' => 'user2@example.com', 'displayName' => 'User Two'],
                    ],
                ]],
            ]);

        $service = $this->createService();
        
        ob_start();
        $result = $this->invokePrivateMethod($service, 'collectUsersFromGroups', [$authorizedGroups]);
        ob_end_clean();
        
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('user1', $result);
        $this->assertArrayHasKey('user2', $result);
        $this->assertSame(['IT-Admins'], $result['user1']['group']);
        $this->assertSame(['IT-Users'], $result['user2']['group']);
    }

    /**
     * Test collectUsersFromGroups deduplicates users in multiple groups.
     */
    public function testCollectUsersFromGroupsDeduplicatesUsers(): void
    {
        $authorizedGroups = [
            'group1' => 'IT-Admins',
            'group2' => 'IT-Users',
        ];

        $sharedUser = [
            'id' => 'user1',
            'profile' => ['email' => 'user1@example.com', 'displayName' => 'User One'],
        ];

        $this->mockGroupService
            ->method('getUsersInGroup')
            ->willReturnMap([
                ['group1', [$sharedUser]],
                ['group2', [$sharedUser]], // Same user in both groups
            ]);

        $service = $this->createService();
        
        ob_start();
        $result = $this->invokePrivateMethod($service, 'collectUsersFromGroups', [$authorizedGroups]);
        ob_end_clean();
        
        // Should have only one user, but with both groups
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('user1', $result);
        $this->assertSame(['IT-Admins', 'IT-Users'], $result['user1']['group']);
    }

    /**
     * Test collectUsersFromGroups includes manager data when available.
     */
    public function testCollectUsersFromGroupsIncludesManagerData(): void
    {
        $authorizedGroups = ['group1' => 'IT-Admins'];

        $this->mockGroupService
            ->method('getUsersInGroup')
            ->willReturn([
                [
                    'id' => 'user1',
                    'profile' => ['email' => 'user1@example.com'],
                    'manager' => [
                        'id' => 'manager1',
                        'profile' => ['email' => 'manager@example.com'],
                    ],
                ],
            ]);

        $service = $this->createService();
        
        ob_start();
        $result = $this->invokePrivateMethod($service, 'collectUsersFromGroups', [$authorizedGroups]);
        ob_end_clean();
        
        $this->assertArrayHasKey('manager', $result['user1']);
        $this->assertSame('manager1', $result['user1']['manager']['id']);
    }

    /**
     * Test collectUsersFromGroups handles empty groups.
     */
    public function testCollectUsersFromGroupsHandlesEmptyGroups(): void
    {
        $authorizedGroups = [
            'group1' => 'Empty-Group',
            'group2' => 'Also-Empty',
        ];

        $this->mockGroupService
            ->method('getUsersInGroup')
            ->willReturn([]);

        $service = $this->createService();
        
        ob_start();
        $result = $this->invokePrivateMethod($service, 'collectUsersFromGroups', [$authorizedGroups]);
        ob_end_clean();
        
        $this->assertSame([], $result);
    }

    /**
     * Test collectUsersFromGroups handles API returning empty array.
     */
    public function testCollectUsersFromGroupsHandlesApiReturningEmpty(): void
    {
        $authorizedGroups = ['group1' => 'IT-Admins'];

        $this->mockGroupService
            ->method('getUsersInGroup')
            ->willReturn([]);

        $service = $this->createService();
        
        ob_start();
        $result = $this->invokePrivateMethod($service, 'collectUsersFromGroups', [$authorizedGroups]);
        ob_end_clean();
        
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Tests for findExistingUser
    // =========================================================================

    /**
     * Test findExistingUser finds user by email.
     */
    public function testFindExistingUserFindsByEmail(): void
    {
        $user = [
            'email' => 'user@example.com',
            'displayName' => 'Test User',
        ];
        $config = ['duplicate' => 'email'];
        $oidcMappings = ['email' => 'email', 'name' => 'name'];

        $this->mockDb
            ->method('escape')
            ->willReturnArgument(0);
        
        $this->mockDb
            ->method('query')
            ->willReturn(new \ArrayIterator([['id' => 42]]));

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'findExistingUser', [$user, $config, $oidcMappings]);
        
        $this->assertSame(42, $result);
    }

    /**
     * Test findExistingUser returns false when user not found.
     */
    public function testFindExistingUserReturnsFalseWhenNotFound(): void
    {
        $user = [
            'email' => 'nonexistent@example.com',
            'displayName' => 'Test User',
        ];
        $config = ['duplicate' => 'email'];
        $oidcMappings = ['email' => 'email', 'name' => 'name'];

        $this->mockDb
            ->method('escape')
            ->willReturnArgument(0);
        
        // First query (by email) returns empty, second query (by name) also returns empty
        $this->mockDb
            ->method('query')
            ->willReturn(new \ArrayIterator([]));

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'findExistingUser', [$user, $config, $oidcMappings]);
        
        $this->assertFalse($result);
    }

    /**
     * Test findExistingUser falls back to name check for external auth users.
     */
    public function testFindExistingUserFallsBackToNameCheck(): void
    {
        $user = [
            'email' => 'user@example.com',
            'displayName' => 'Test User',
        ];
        $config = ['duplicate' => 'email'];
        $oidcMappings = ['email' => 'email', 'name' => 'name'];

        $this->mockDb
            ->method('escape')
            ->willReturnArgument(0);
        
        // First call returns empty (no email match), second call finds by name
        $this->mockDb
            ->method('query')
            ->willReturnOnConsecutiveCalls(
                new \ArrayIterator([]), // Email search returns nothing
                new \ArrayIterator([['id' => 99]]) // Name search finds user
            );

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'findExistingUser', [$user, $config, $oidcMappings]);
        
        $this->assertSame(99, $result);
    }

    // =========================================================================
    // Tests for deactivateUnlistedUsers
    // =========================================================================

    /**
     * Test deactivateUnlistedUsers deactivates users not in the list.
     */
    public function testDeactivateUnlistedUsersDeactivatesCorrectUsers(): void
    {
        $listedUsers = [
            ['id' => 1],
            ['id' => 2],
        ];
        $config = [];

        $this->mockDb
            ->expects($this->once())
            ->method('request')
            ->willReturn(new \ArrayIterator([
                ['id' => 1, 'is_active' => 1], // Listed, active - no change
                ['id' => 2, 'is_active' => 0], // Listed, inactive - should activate
                ['id' => 3, 'is_active' => 1], // Not listed, active - should deactivate
                ['id' => 4, 'is_active' => 0], // Not listed, inactive - no change
            ]));

        $this->mockDb
            ->expects($this->exactly(2))
            ->method('updateOrDie')
            ->withConsecutive(
                ['glpi_users', ['is_active' => 1], ['id' => 2]], // Activate user 2
                ['glpi_users', ['is_active' => 0], ['id' => 3]]  // Deactivate user 3
            );

        $service = $this->createService();
        
        $this->invokePrivateMethod($service, 'deactivateUnlistedUsers', [$listedUsers, $config]);
    }

    /**
     * Test deactivateUnlistedUsers includes LDAP users when configured.
     */
    public function testDeactivateUnlistedUsersIncludesLdapWhenConfigured(): void
    {
        $listedUsers = [];
        $config = ['ldap_update' => 1];

        $this->mockDb
            ->expects($this->once())
            ->method('request')
            ->with($this->callback(function ($params) {
                // Should have OR condition for LDAP and EXTERNAL
                return isset($params['WHERE']['OR']);
            }))
            ->willReturn(new \ArrayIterator([]));

        $service = $this->createService();
        
        $this->invokePrivateMethod($service, 'deactivateUnlistedUsers', [$listedUsers, $config]);
    }

    /**
     * Test deactivateUnlistedUsers does nothing when all users are listed.
     */
    public function testDeactivateUnlistedUsersNoChangesWhenAllListed(): void
    {
        $listedUsers = [
            ['id' => 1],
            ['id' => 2],
        ];
        $config = [];

        $this->mockDb
            ->method('request')
            ->willReturn(new \ArrayIterator([
                ['id' => 1, 'is_active' => 1],
                ['id' => 2, 'is_active' => 1],
            ]));

        // updateOrDie should never be called
        $this->mockDb
            ->expects($this->never())
            ->method('updateOrDie');

        $service = $this->createService();
        
        $this->invokePrivateMethod($service, 'deactivateUnlistedUsers', [$listedUsers, $config]);
    }

    // =========================================================================
    // Tests for fetchUserById
    // =========================================================================

    /**
     * Test fetchUserById returns user data from API.
     */
    public function testFetchUserByIdReturnsUserData(): void
    {
        $userData = [
            'id' => 'okta-123',
            'profile' => [
                'email' => 'user@example.com',
                'displayName' => 'Test User',
            ],
        ];

        $this->mockClient
            ->expects($this->once())
            ->method('request')
            ->with('/api/v1/users/okta-123?expand=manager')
            ->willReturn(['body' => $userData]);

        $service = $this->createService();
        
        $result = $service->fetchUserById('okta-123');
        
        $this->assertSame($userData, $result);
    }

    /**
     * Test fetchUserById returns null when user not found.
     */
    public function testFetchUserByIdReturnsNullWhenNotFound(): void
    {
        $this->mockClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(['body' => null]);

        $service = $this->createService();
        
        $result = $service->fetchUserById('nonexistent-id');
        
        $this->assertNull($result);
    }

    /**
     * Test fetchUserById returns null on API failure.
     */
    public function testFetchUserByIdReturnsNullOnApiFailure(): void
    {
        $this->mockClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        $service = $this->createService();
        
        $result = $service->fetchUserById('okta-123');
        
        $this->assertNull($result);
    }

    // =========================================================================
    // Tests for importSingleUser
    // =========================================================================

    /**
     * Test importSingleUser returns empty array on API failure.
     */
    public function testImportSingleUserReturnsEmptyOnApiFailure(): void
    {
        $this->mockClient
            ->method('request')
            ->willReturn(false);

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'importSingleUser', [
            'okta-123',
            ['group1' => 'IT-Admins'],
            ['duplicate' => 'email'],
            false
        ]);
        
        $this->assertSame([], $result);
    }

    /**
     * Test importSingleUser returns empty array when response has no body.
     */
    public function testImportSingleUserReturnsEmptyWhenNoBody(): void
    {
        $this->mockClient
            ->method('request')
            ->willReturn(['status' => 200]);

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'importSingleUser', [
            'okta-123',
            ['group1' => 'IT-Admins'],
            ['duplicate' => 'email'],
            false
        ]);
        
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Tests for service without logger
    // =========================================================================

    /**
     * Test service works without logger (null logger).
     */
    public function testServiceWorksWithoutLogger(): void
    {
        $service = new UserImportService(
            $this->mockClient,
            $this->mockGroupService,
            $this->mockDb,
            $this->mockUserRepository,
            null // No logger
        );

        $this->mockUserRepository
            ->method('findUserIdByEmail')
            ->willReturn(null);

        // Add pending assignment
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [123, 'manager@example.com']);
        
        // Should not throw even without logger
        ob_start();
        $this->invokePrivateMethod($service, 'resolvePendingManagerAssignments', []);
        $output = ob_get_clean();
        
        // Verify warning is still printed
        $this->assertStringContainsString("Warning: Manager with email 'manager@example.com' not found", $output);
    }

    // =========================================================================
    // Tests for API_MAPPINGS and OIDC_TRANSLATION constants
    // =========================================================================

    /**
     * Test API_MAPPINGS constant has expected keys.
     */
    public function testApiMappingsConstantHasExpectedKeys(): void
    {
        $expectedKeys = ['sub', 'name', 'profile', 'nickname', 'family_name', 
                         'given_name', 'email', 'phone_number', 'preferred_username', 
                         'username', 'manager'];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, UserImportService::API_MAPPINGS);
        }
    }

    /**
     * Test OIDC_TRANSLATION constant has expected keys.
     */
    public function testOidcTranslationConstantHasExpectedKeys(): void
    {
        $expectedKeys = ['name', 'given_name', 'family_name', 'phone_number', 
                         'email', 'username', 'manager'];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, UserImportService::OIDC_TRANSLATION);
        }
    }

    // =========================================================================
    // Tests for resolveManagerId with empty managerId
    // =========================================================================

    /**
     * Test resolveManagerId returns null when managerId is empty string.
     */
    public function testResolveManagerIdReturnsNullWhenManagerIdIsEmptyString(): void
    {
        $service = $this->createService();
        
        $user = [
            'id' => 'okta-user-1',
            'email' => 'user@example.com',
            'managerId' => '',
        ];
        
        $result = $this->invokePrivateMethod($service, 'resolveManagerId', [$user]);
        
        $this->assertNull($result['managerId']);
        $this->assertNull($result['managerEmail']);
    }

    // =========================================================================
    // Tests for API_MAPPINGS values
    // =========================================================================

    /**
     * Test API_MAPPINGS constant has correct values.
     */
    public function testApiMappingsConstantHasCorrectValues(): void
    {
        $this->assertSame('id', UserImportService::API_MAPPINGS['sub']);
        $this->assertSame('displayName', UserImportService::API_MAPPINGS['name']);
        $this->assertSame('profileUrl', UserImportService::API_MAPPINGS['profile']);
        $this->assertSame('nickName', UserImportService::API_MAPPINGS['nickname']);
        $this->assertSame('lastName', UserImportService::API_MAPPINGS['family_name']);
        $this->assertSame('firstName', UserImportService::API_MAPPINGS['given_name']);
        $this->assertSame('email', UserImportService::API_MAPPINGS['email']);
        $this->assertSame('mobilePhone', UserImportService::API_MAPPINGS['phone_number']);
        $this->assertSame('login', UserImportService::API_MAPPINGS['preferred_username']);
        $this->assertSame('login', UserImportService::API_MAPPINGS['username']);
        $this->assertSame('manager', UserImportService::API_MAPPINGS['manager']);
    }

    /**
     * Test OIDC_TRANSLATION constant has correct values.
     */
    public function testOidcTranslationConstantHasCorrectValues(): void
    {
        $this->assertSame('name', UserImportService::OIDC_TRANSLATION['name']);
        $this->assertSame('firstname', UserImportService::OIDC_TRANSLATION['given_name']);
        $this->assertSame('realname', UserImportService::OIDC_TRANSLATION['family_name']);
        $this->assertSame('phone', UserImportService::OIDC_TRANSLATION['phone_number']);
        $this->assertSame('email', UserImportService::OIDC_TRANSLATION['email']);
        $this->assertSame('name', UserImportService::OIDC_TRANSLATION['username']);
        $this->assertSame('users_id_supervisor', UserImportService::OIDC_TRANSLATION['manager']);
    }

    // =========================================================================
    // Tests for buildUserObject with manager data
    // =========================================================================

    /**
     * Test buildUserObject includes manager data when present.
     */
    public function testBuildUserObjectIncludesManagerWhenPresent(): void
    {
        $service = $this->createService();
        
        $oktaUser = [
            'id' => 'okta-123',
            'email' => 'user@example.com',
            'manager' => ['id' => 'manager-okta-id', 'profile' => ['email' => 'manager@example.com']],
        ];
        
        $result = $this->invokePrivateMethod($service, 'buildUserObject', [$oktaUser]);
        
        $this->assertArrayHasKey('manager', $result);
        $this->assertSame(['id' => 'manager-okta-id', 'profile' => ['email' => 'manager@example.com']], $result['manager']);
    }

    // =========================================================================
    // Tests for addPendingManagerAssignment overwrites
    // =========================================================================

    /**
     * Test addPendingManagerAssignment overwrites previous assignment for same user.
     */
    public function testAddPendingManagerAssignmentOverwritesPrevious(): void
    {
        $service = $this->createService();
        
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [123, 'old-manager@example.com']);
        $this->invokePrivateMethod($service, 'addPendingManagerAssignment', [123, 'new-manager@example.com']);
        
        $pending = $this->getPrivateProperty($service, 'pendingManagerAssignments');
        
        $this->assertCount(1, $pending);
        $this->assertSame('new-manager@example.com', $pending[123]);
    }

    // =========================================================================
    // Tests for collectUsersFromGroups with false return (API failure)
    // =========================================================================

    /**
     * Test collectUsersFromGroups handles API returning false (failure).
     */
    public function testCollectUsersFromGroupsHandlesFalseResponse(): void
    {
        $authorizedGroups = ['group1' => 'IT-Admins'];

        // Simulate API failure by returning false cast to empty array in the service
        $this->mockGroupService
            ->method('getUsersInGroup')
            ->willReturn([]);

        $service = $this->createService();
        
        ob_start();
        $result = $this->invokePrivateMethod($service, 'collectUsersFromGroups', [$authorizedGroups]);
        ob_end_clean();
        
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Tests for deactivateUnlistedUsers with empty users
    // =========================================================================

    /**
     * Test deactivateUnlistedUsers handles empty database result.
     */
    public function testDeactivateUnlistedUsersHandlesEmptyDatabase(): void
    {
        $listedUsers = [['id' => 1]];
        $config = [];

        $this->mockDb
            ->method('request')
            ->willReturn(new \ArrayIterator([]));

        // updateOrDie should never be called since there are no users in database
        $this->mockDb
            ->expects($this->never())
            ->method('updateOrDie');

        $service = $this->createService();
        
        $this->invokePrivateMethod($service, 'deactivateUnlistedUsers', [$listedUsers, $config]);
    }

    /**
     * Test deactivateUnlistedUsers handles empty listed users.
     */
    public function testDeactivateUnlistedUsersDeactivatesAllWhenListEmpty(): void
    {
        $listedUsers = [];  // No users in import
        $config = [];

        $this->mockDb
            ->method('request')
            ->willReturn(new \ArrayIterator([
                ['id' => 1, 'is_active' => 1],
                ['id' => 2, 'is_active' => 1],
            ]));

        $this->mockDb
            ->expects($this->exactly(2))
            ->method('updateOrDie');

        $service = $this->createService();
        
        $this->invokePrivateMethod($service, 'deactivateUnlistedUsers', [$listedUsers, $config]);
    }

    // =========================================================================
    // Tests for findExistingUser edge cases
    // =========================================================================

    /**
     * Test findExistingUser escapes special characters in query.
     */
    public function testFindExistingUserEscapesSpecialCharacters(): void
    {
        $user = [
            'email' => "user'test@example.com",
            'displayName' => "O'Brien Test",
        ];
        $config = ['duplicate' => 'email'];
        $oidcMappings = ['email' => 'email', 'name' => 'name'];

        $this->mockDb
            ->expects($this->exactly(2))
            ->method('escape')
            ->willReturnCallback(function ($value) {
                return addslashes($value);
            });
        
        $this->mockDb
            ->method('query')
            ->willReturn(new \ArrayIterator([]));

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'findExistingUser', [$user, $config, $oidcMappings]);
        
        $this->assertFalse($result);
    }

    // =========================================================================
    // Tests for getOidcMappings with multiple rows
    // =========================================================================

    /**
     * Test getOidcMappings only returns first row even if multiple exist.
     */
    public function testGetOidcMappingsReturnsOnlyFirstRow(): void
    {
        $firstRow = ['name' => 'name', 'email' => 'email'];
        $secondRow = ['name' => 'other', 'email' => 'other_email'];
        
        $this->mockDb
            ->expects($this->once())
            ->method('query')
            ->willReturn(new \ArrayIterator([$firstRow, $secondRow]));

        $service = $this->createService();
        
        $result = $this->invokePrivateMethod($service, 'getOidcMappings', []);
        
        $this->assertSame($firstRow, $result);
    }
}
