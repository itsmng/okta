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
use GlpiPlugin\Okta\Services\GroupService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GroupService class.
 */
class GroupServiceTest extends TestCase
{
    public function testGetGroupsByRegexFiltersCorrectly(): void
    {
        // Create a mock OktaClient that returns predefined groups
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->with('/api/v1/groups')
            ->willReturn([
                'body' => [
                    ['id' => 'group1', 'profile' => ['name' => 'IT-Admins']],
                    ['id' => 'group2', 'profile' => ['name' => 'IT-Users']],
                    ['id' => 'group3', 'profile' => ['name' => 'HR-Team']],
                    ['id' => 'group4', 'profile' => ['name' => 'Sales-Team']],
                ]
            ]);

        $groupService = new GroupService($mockClient);
        
        // Test filtering with "IT-" prefix
        $result = $groupService->getGroupsByRegex('^IT-');
        
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('group1', $result);
        $this->assertArrayHasKey('group2', $result);
        $this->assertArrayNotHasKey('group3', $result);
        $this->assertArrayNotHasKey('group4', $result);
    }

    public function testGetGroupsByRegexIsCaseInsensitive(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'body' => [
                    ['id' => 'group1', 'profile' => ['name' => 'IT-Admins']],
                    ['id' => 'group2', 'profile' => ['name' => 'it-users']],
                ]
            ]);

        $groupService = new GroupService($mockClient);
        
        // Should match both regardless of case
        $result = $groupService->getGroupsByRegex('^IT-');
        
        $this->assertCount(2, $result);
    }

    public function testGetGroupsReturnsEmptyArrayOnApiFailure(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn(false);

        $groupService = new GroupService($mockClient);
        
        $result = $groupService->getGroups();
        
        $this->assertSame([], $result);
    }

    public function testGetGroupsReturnsEmptyArrayOnMissingBody(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn(['header' => []]);

        $groupService = new GroupService($mockClient);
        
        $result = $groupService->getGroups();
        
        $this->assertSame([], $result);
    }

    public function testGetUsersInGroupHandlesPagination(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        
        // First page
        $mockClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [
                    'header' => [
                        'link' => ['<https://example.okta.com/api/v1/groups/123/users?after=abc>; rel="next"']
                    ],
                    'body' => [
                        ['id' => 'user1', 'profile' => ['firstName' => 'John']],
                    ]
                ],
                [
                    'header' => [
                        'link' => ['<https://example.okta.com/api/v1/groups/123/users>; rel="self"']
                    ],
                    'body' => [
                        ['id' => 'user2', 'profile' => ['firstName' => 'Jane']],
                    ]
                ]
            );

        $mockClient->method('parseLinkHeader')
            ->willReturnOnConsecutiveCalls(
                ['next' => 'https://example.okta.com/api/v1/groups/123/users?after=abc'],
                ['self' => 'https://example.okta.com/api/v1/groups/123/users']
            );

        $groupService = new GroupService($mockClient);
        
        $result = $groupService->getUsersInGroup('123');
        
        $this->assertCount(2, $result);
        $this->assertSame('user1', $result[0]['id']);
        $this->assertSame('user2', $result[1]['id']);
    }

    public function testGetUsersInGroupReturnsEmptyOnMissingLinkHeader(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'body' => [
                    ['id' => 'user1', 'profile' => ['firstName' => 'John']],
                ]
                // No 'header' key
            ]);

        $groupService = new GroupService($mockClient);
        
        $result = $groupService->getUsersInGroup('123');
        
        $this->assertSame([], $result);
    }

    public function testGetUsersInGroupReturnsEmptyOnMissingBody(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'header' => [
                    'link' => ['<https://example.okta.com/api/v1/groups/123/users>; rel="self"']
                ]
                // No 'body' key
            ]);

        $mockClient->method('parseLinkHeader')
            ->willReturn(['self' => 'https://example.okta.com/api/v1/groups/123/users']);

        $groupService = new GroupService($mockClient);
        
        $result = $groupService->getUsersInGroup('123');
        
        $this->assertSame([], $result);
    }

    public function testGetGroupsByRegexReturnsAllGroupsForMatchAllPattern(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'body' => [
                    ['id' => 'group1', 'profile' => ['name' => 'IT-Admins']],
                    ['id' => 'group2', 'profile' => ['name' => 'HR-Team']],
                    ['id' => 'group3', 'profile' => ['name' => 'Sales']],
                ]
            ]);

        $groupService = new GroupService($mockClient);
        
        // .* matches everything
        $result = $groupService->getGroupsByRegex('.*');
        
        $this->assertCount(3, $result);
    }

    public function testGetGroupsByRegexReturnsEmptyForNoMatches(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'body' => [
                    ['id' => 'group1', 'profile' => ['name' => 'IT-Admins']],
                    ['id' => 'group2', 'profile' => ['name' => 'HR-Team']],
                ]
            ]);

        $groupService = new GroupService($mockClient);
        
        // Pattern that doesn't match any group
        $result = $groupService->getGroupsByRegex('^Finance-');
        
        $this->assertSame([], $result);
    }

    public function testGetGroupsReturnsCorrectMapping(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'body' => [
                    ['id' => 'okta-group-123', 'profile' => ['name' => 'Developers']],
                    ['id' => 'okta-group-456', 'profile' => ['name' => 'Designers']],
                ]
            ]);

        $groupService = new GroupService($mockClient);
        
        $result = $groupService->getGroups();
        
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('okta-group-123', $result);
        $this->assertArrayHasKey('okta-group-456', $result);
        $this->assertSame('Developers', $result['okta-group-123']);
        $this->assertSame('Designers', $result['okta-group-456']);
    }

    public function testGetGroupsEscapesGroupNames(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'body' => [
                    ['id' => 'group1', 'profile' => ['name' => "O'Reilly Team"]],
                ]
            ]);

        $groupService = new GroupService($mockClient);
        
        $result = $groupService->getGroups();
        
        // addslashes should escape the apostrophe
        $this->assertSame("O\'Reilly Team", $result['group1']);
    }

    public function testGetGroupsByRegexHandlesSpecialCharacters(): void
    {
        $mockClient = $this->createMock(OktaClient::class);
        $mockClient->method('request')
            ->willReturn([
                'body' => [
                    ['id' => 'group1', 'profile' => ['name' => 'IT-Admins']],
                    ['id' => 'group2', 'profile' => ['name' => 'IT Dev Team']],
                    ['id' => 'group3', 'profile' => ['name' => 'IT-Support']],
                ]
            ]);

        $groupService = new GroupService($mockClient);
        
        // Regex with word boundary to match 'IT-'
        $result = $groupService->getGroupsByRegex('^IT-');
        
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('group1', $result);
        $this->assertArrayHasKey('group3', $result);
    }
}
