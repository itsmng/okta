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

namespace GlpiPlugin\Okta\Services;

use GlpiPlugin\Okta\Api\OktaClient;

/**
 * GroupService handles Okta group retrieval and filtering.
 */
class GroupService
{
    private OktaClient $client;

    /**
     * @param OktaClient $client The Okta API client
     */
    public function __construct(OktaClient $client)
    {
        $this->client = $client;
    }

    /**
     * Retrieve all groups from Okta.
     *
     * @return array<string, string> Associative array of group ID => group name
     */
    public function getGroups(): array
    {
        $response = $this->client->request('/api/v1/groups');
        
        if (!$response || !isset($response['body'])) {
            return [];
        }

        $groups = [];
        foreach ($response['body'] as $group) {
            $groups[$group['id']] = addslashes($group['profile']['name']);
        }
        
        return $groups;
    }

    /**
     * Get groups filtered by a regex pattern.
     *
     * @param string $regex The regex pattern to match group names
     *
     * @return array<string, string>|false Filtered groups or false on regex error
     */
    public function getGroupsByRegex(string $regex)
    {
        $groups = $this->getGroups();
        $regex = stripslashes($regex);

        $filteredGroups = [];

        foreach ($groups as $key => $value) {
            try {
                $groupName = stripslashes($value);
                if (@preg_match("/$regex/i", $groupName)) {
                    $filteredGroups[$key] = addslashes($groupName);
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        
        return $filteredGroups;
    }

    /**
     * Get all users in a specific group with pagination support.
     *
     * @param string $groupId The Okta group ID
     *
     * @return array List of user objects
     */
    public function getUsersInGroup(string $groupId): array
    {
        $uri = "/api/v1/groups/{$groupId}/users?expand=manager";
        $response = [];
        
        while ($uri) {
            $currentList = $this->client->request($uri);
            
            if (!isset($currentList['header']['link'])) {
                return $response;
            }
            
            $links = $this->client->parseLinkHeader($currentList['header']['link']);
            
            if (!isset($currentList['body'])) {
                return $response;
            }
            
            $response = array_merge($response, $currentList['body']);
            
            if (isset($links['next'])) {
                $uri = $links['next'];
            } else {
                return $response;
            }
        }
        
        return $response;
    }
}
