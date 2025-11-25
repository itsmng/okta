<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2025 ITSM-NG and contributors.
 *
 * https://www.itsm-ng.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of ITSM-NG.
 *
 * ITSM-NG is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ITSM-NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ITSM-NG. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Okta\Tests\Unit\Repository;

use GlpiPlugin\Okta\Repository\DatabaseInterface;
use GlpiPlugin\Okta\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserRepository class.
 */
class UserRepositoryTest extends TestCase
{
    /**
     * Test that findUserIdByEmail returns null for empty email.
     */
    public function testFindUserIdByEmailReturnsNullForEmptyEmail(): void
    {
        $mockDb = $this->createMock(DatabaseInterface::class);
        // query should never be called for empty email
        $mockDb->expects($this->never())->method('query');

        $repository = new UserRepository($mockDb);
        
        $result = $repository->findUserIdByEmail('');
        
        $this->assertNull($result);
    }

    /**
     * Test that findUserIdByEmail returns user ID when found.
     */
    public function testFindUserIdByEmailReturnsUserIdWhenFound(): void
    {
        $mockDb = $this->createMock(DatabaseInterface::class);
        
        // Create a mock iterator that returns our test data
        $mockIterator = new \ArrayIterator([['id' => 42]]);
        
        $mockDb->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($query) {
                return strpos($query, "glpi_useremails.email = 'manager@example.com'") !== false
                    && strpos($query, 'INNER JOIN glpi_useremails') !== false
                    && strpos($query, 'glpi_users.authtype IN') !== false;
            }))
            ->willReturn($mockIterator);
        
        $mockDb->method('escape')
            ->willReturnCallback(function ($value) {
                return addslashes($value);
            });

        $repository = new UserRepository($mockDb);
        
        $result = $repository->findUserIdByEmail('manager@example.com');
        
        $this->assertSame(42, $result);
    }

    /**
     * Test that findUserIdByEmail returns null when user not found.
     */
    public function testFindUserIdByEmailReturnsNullWhenNotFound(): void
    {
        $mockDb = $this->createMock(DatabaseInterface::class);
        
        // Empty result set
        $mockIterator = new \ArrayIterator([]);
        
        $mockDb->expects($this->once())
            ->method('query')
            ->willReturn($mockIterator);
        
        $mockDb->method('escape')
            ->willReturnCallback(function ($value) {
                return addslashes($value);
            });

        $repository = new UserRepository($mockDb);
        
        $result = $repository->findUserIdByEmail('nonexistent@example.com');
        
        $this->assertNull($result);
    }

    /**
     * Test that findUserIdByEmail properly escapes email to prevent SQL injection.
     */
    public function testFindUserIdByEmailEscapesEmail(): void
    {
        $mockDb = $this->createMock(DatabaseInterface::class);
        
        $mockDb->expects($this->once())
            ->method('escape')
            ->with("test'injection@example.com")
            ->willReturn("test\\'injection@example.com");
        
        $mockIterator = new \ArrayIterator([]);
        $mockDb->method('query')->willReturn($mockIterator);

        $repository = new UserRepository($mockDb);
        
        $repository->findUserIdByEmail("test'injection@example.com");
    }

    /**
     * Test that updateSupervisor calls database update correctly.
     */
    public function testUpdateSupervisorCallsDatabase(): void
    {
        $mockDb = $this->createMock(DatabaseInterface::class);
        
        $mockDb->expects($this->once())
            ->method('updateOrDie')
            ->with(
                'glpi_users',
                ['users_id_supervisor' => 10],
                ['id' => 5]
            )
            ->willReturn(true);

        $repository = new UserRepository($mockDb);
        
        $result = $repository->updateSupervisor(5, 10);
        
        $this->assertTrue($result);
    }
}
