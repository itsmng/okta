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

namespace GlpiPlugin\Okta\Repository;

/**
 * UserRepository handles database operations for user lookups.
 * 
 * This class isolates database access to enable unit testing without
 * requiring a real database connection.
 */
class UserRepository
{
    private DatabaseInterface $db;

    /**
     * Auth types to filter by when searching for users.
     * Defaults to GLPI's Auth::EXTERNAL (4) and Auth::LDAP (3).
     * @var array<int>
     */
    private array $authTypes;

    /**
     * @param DatabaseInterface $db        Database connection
     * @param array<int>|null   $authTypes Auth types to filter by (defaults to [3, 4] for LDAP and EXTERNAL)
     */
    public function __construct(DatabaseInterface $db, ?array $authTypes = null)
    {
        $this->db = $db;
        // Default auth types: Auth::LDAP = 3, Auth::EXTERNAL = 4
        $this->authTypes = $authTypes ?? [3, 4];
    }

    /**
     * Find a GLPI user ID by their email address.
     *
     * Searches in glpi_useremails table (which can have multiple emails per user)
     * and returns the ID of a matching user that was created via external auth
     * (LDAP or External).
     *
     * @param string $email The email address to search for
     *
     * @return int|null The user's GLPI ID or null if not found
     */
    public function findUserIdByEmail(string $email): ?int
    {
        if (empty($email)) {
            return null;
        }

        $authTypesList = implode(', ', $this->authTypes);
        $query = "SELECT glpi_users.id 
                  FROM glpi_users 
                  INNER JOIN glpi_useremails ON glpi_users.id = glpi_useremails.users_id
                  WHERE glpi_useremails.email = '" . $this->db->escape($email) . "'
                  AND glpi_users.authtype IN ({$authTypesList})
                  LIMIT 1";
        
        $result = iterator_to_array($this->db->query($query));
        
        if (!empty($result)) {
            return (int) $result[0]['id'];
        }

        return null;
    }

    /**
     * Update a user's supervisor (manager).
     *
     * @param int $userId       The user's GLPI ID
     * @param int $supervisorId The supervisor's GLPI ID
     *
     * @return bool True on success
     */
    public function updateSupervisor(int $userId, int $supervisorId): bool
    {
        return $this->db->updateOrDie(
            'glpi_users',
            ['users_id_supervisor' => $supervisorId],
            ['id' => $userId]
        );
    }
}
