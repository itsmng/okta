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

use Auth;
use Oidc;
use RuleRightCollection;
use Toolbox;
use User;
use GlpiPlugin\Okta\Api\OktaClient;
use GlpiPlugin\Okta\PluginOktaConfig;
use GlpiPlugin\Okta\Repository\DatabaseInterface;
use GlpiPlugin\Okta\Repository\UserRepository;

/**
 * UserImportService handles importing users from Okta into ITSM-NG.
 */
class UserImportService
{
    /**
     * Mapping from OIDC claims to Okta API field names.
     */
    public const API_MAPPINGS = [
        'sub' => 'id',
        'name' => 'displayName',
        'profile' => 'profileUrl',
        'nickname' => 'nickName',
        'family_name' => 'lastName',
        'given_name' => 'firstName',
        'email' => 'email',
        'phone_number' => 'mobilePhone',
        'preferred_username' => 'login',
        'username' => 'login',
        'manager' => 'manager',
    ];

    /**
     * Translation from OIDC claims to GLPI user fields.
     */
    public const OIDC_TRANSLATION = [
        'name' => 'name',
        'given_name' => 'firstname',
        'family_name' => 'realname',
        'phone_number' => 'phone',
        'email' => 'email',
        'username' => 'name',
        'manager' => 'users_id_supervisor'
    ];

    private OktaClient $client;
    private GroupService $groupService;
    private DatabaseInterface $db;
    private UserRepository $userRepository;
    private ?LoggerInterface $logger;

    /**
     * Pending manager assignments for deferred resolution.
     * Key: GLPI user ID, Value: Manager email
     * @var array<int, string>
     */
    private array $pendingManagerAssignments = [];

    /**
     * @param OktaClient         $client         The Okta API client
     * @param GroupService       $groupService   The group service
     * @param DatabaseInterface  $db             Database connection
     * @param UserRepository     $userRepository User repository for lookups
     * @param LoggerInterface|null $logger       Logger for warnings (optional)
     */
    public function __construct(
        OktaClient $client,
        GroupService $groupService,
        DatabaseInterface $db,
        UserRepository $userRepository,
        ?LoggerInterface $logger = null
    ) {
        $this->client = $client;
        $this->groupService = $groupService;
        $this->db = $db;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    /**
     * Import users from authorized groups.
     *
     * @param array       $authorizedGroups Groups to import from (id => name)
     * @param bool        $fullImport       Whether to update existing users
     * @param string|null $userId           Specific user ID to import (optional)
     *
     * @return array List of imported user objects
     */
    public function importUsers(array $authorizedGroups, bool $fullImport = false, ?string $userId = null): array
    {
        $importedUsers = [];
        $listedUsers = [];
        $config = PluginOktaConfig::getConfigValues();

        // Reset pending manager assignments for this import batch
        $this->pendingManagerAssignments = [];

        if (!$userId) {
            $userList = $this->collectUsersFromGroups($authorizedGroups);
            
            foreach ($userList as $user) {
                $result = $this->createOrUpdateUser($user, $config, $fullImport);
                if ($result) {
                    [$listedUser, $importedUser] = $result;
                    if ($listedUser) {
                        $listedUsers[] = $listedUser;
                    }
                    if ($importedUser) {
                        $importedUsers[] = $importedUser;
                    }
                }
            }
            
            // Second pass: resolve deferred manager assignments
            $this->resolvePendingManagerAssignments();
            
            if ($config['deactivate'] == 1) {
                $this->deactivateUnlistedUsers($listedUsers, $config);
            }
        } else {
            $importedUsers = $this->importSingleUser($userId, $authorizedGroups, $config, $fullImport);
            
            // Resolve any pending managers for single user import
            $this->resolvePendingManagerAssignments();
        }
        
        return $importedUsers;
    }

    /**
     * Collect all users from the specified groups.
     *
     * @param array $authorizedGroups Groups to collect from
     *
     * @return array Deduplicated list of users with their groups
     */
    private function collectUsersFromGroups(array $authorizedGroups): array
    {
        $userList = [];
        
        foreach ($authorizedGroups as $key => $group) {
            $usersInGroup = $this->groupService->getUsersInGroup($key);
            
            if (!$usersInGroup || empty($usersInGroup)) {
                continue;
            }
            
            foreach ($usersInGroup as $user) {
                if (!isset($userList[$user['id']])) {
                    $content = $user['profile'];
                    $content['id'] = $user['id'];
                    $content['group'] = [$authorizedGroups[$key]];
                    
                    // Include manager data if available
                    if (isset($user['manager'])) {
                        $content['manager'] = $user['manager'];
                    }
                    
                    $userList[$user['id']] = $content;
                } else {
                    $userList[$user['id']]['group'][] = $authorizedGroups[$key];
                }
            }
        }
        
        return $userList;
    }

    /**
     * Import a single user by their Okta ID.
     *
     * @param string $userId           The Okta user ID
     * @param array  $authorizedGroups Groups the user belongs to
     * @param array  $config           Configuration values
     * @param bool   $fullImport       Whether to update existing user
     *
     * @return array List with the imported user
     */
    private function importSingleUser(string $userId, array $authorizedGroups, array $config, bool $fullImport): array
    {
        $response = $this->client->request("api/v1/users/{$userId}");
        
        if (!$response || !isset($response['body'])) {
            return [];
        }
        
        $userObject = $response['body'];
        $user = $userObject['profile'];
        $user['id'] = $userId;
        $user['group'] = $authorizedGroups;
        
        $result = $this->createOrUpdateUser($user, $config, $fullImport);
        
        if ($result && $result[1]) {
            return [$result[1]];
        }
        
        return [];
    }

    /**
     * Create or update a user in GLPI based on Okta data.
     *
     * @param array $user       The Okta user data
     * @param array $config     Configuration values
     * @param bool  $fullImport Whether to update existing users
     *
     * @return array|false [listedUser, importedUser] or false if filtered out
     */
    public function createOrUpdateUser(array $user, array $config, bool $fullImport = false)
    {
        $oidcMappings = $this->getOidcMappings();

        // Apply normalization and filtering
        foreach (self::OIDC_TRANSLATION as $key => $value) {
            if (!isset($oidcMappings[$key]) || !isset(self::API_MAPPINGS[$oidcMappings[$key]])) {
                continue;
            }
            
            $inputName = self::API_MAPPINGS[$oidcMappings[$key]];
            
            if (isset($config['use_norm_' . $key]) && $config['use_norm_' . $key] == 1) {
                $user[$inputName] = preg_replace('/' . $config['norm_' . $key] . '/', '', $user[$inputName] ?? '');
                if ($user[$inputName] == '') {
                    return false;
                }
            }
            
            if (isset($config['use_filter_' . $key]) && $config['use_filter_' . $key] == 1) {
                if (!preg_match('/' . $config['filter_' . $key] . '/', $user[$inputName] ?? '')) {
                    return false;
                }
            }
        }

        // Process manager - get both manager ID (if found) and email (for deferred resolution)
        $managerResult = $this->resolveManagerId($user);
        $managerId = $managerResult['managerId'];
        $managerEmail = $managerResult['managerEmail'];

        // Get mapping name for duplicate check
        $duplicateKey = $config['duplicate'] ?? 'email';
        $oidcMapping = $oidcMappings[$duplicateKey] ?? $duplicateKey;
        $mappingName = self::API_MAPPINGS[$oidcMapping] ?? null;
        
        if ($mappingName === null || !isset($user[$mappingName])) {
            return false;
        }

        $userObject = $this->buildUserObject($user);
        $existingId = $this->findExistingUser($user, $config, $oidcMappings);

        if (!$existingId || $fullImport) {
            return $this->handleUserCreationOrUpdate(
                $existingId,
                $user,
                $userObject,
                $oidcMappings,
                $managerId,
                $managerEmail,
                $fullImport
            );
        }

        // For existing users not doing a full import, handle manager separately
        if ($managerId !== null) {
            $this->userRepository->updateSupervisor($existingId, $managerId);
        } elseif ($managerEmail !== null) {
            // Manager not found yet - defer for second pass
            $this->addPendingManagerAssignment($existingId, $managerEmail);
        }

        $userObject['id'] = $existingId;
        return [$userObject, null];
    }

    /**
     * Get OIDC mappings from database.
     *
     * @return array The OIDC mappings
     */
    private function getOidcMappings(): array
    {
        $result = iterator_to_array($this->db->query("SELECT * FROM glpi_oidc_mapping"));
        return $result[0] ?? [];
    }

    /**
     * Resolve the GLPI user ID for the user's manager.
     *
     * Looks up the manager by their email address in glpi_useremails.
     * If the manager is not found, returns null and the manager email
     * should be stored for deferred resolution.
     *
     * @param array $user The Okta user data
     *
     * @return array{managerId: int|null, managerEmail: string|null} Manager resolution result
     */
    private function resolveManagerId(array $user): array
    {
        $managerEmail = $user['managerId'] ?? '';
        
        if (empty($managerEmail)) {
            return ['managerId' => null, 'managerEmail' => null];
        }

        $managerId = $this->userRepository->findUserIdByEmail($managerEmail);
        
        return ['managerId' => $managerId, 'managerEmail' => $managerEmail];
    }

    /**
     * Resolve pending manager assignments after all users have been imported.
     *
     * This is called in a second pass to handle cases where the manager
     * user hadn't been imported yet during the first pass.
     */
    private function resolvePendingManagerAssignments(): void
    {
        if (empty($this->pendingManagerAssignments)) {
            return;
        }

        foreach ($this->pendingManagerAssignments as $userId => $managerEmail) {
            $managerId = $this->userRepository->findUserIdByEmail($managerEmail);
            
            if ($managerId !== null) {
                $this->userRepository->updateSupervisor($userId, $managerId);
            } else {
                // Log warning - manager still not found after second pass
                $message = "Okta sync: Could not resolve manager for user ID {$userId}. " .
                    "Manager email '{$managerEmail}' not found in GLPI.";
                if ($this->logger !== null) {
                    $this->logger->warning($message);
                }
            }
        }

        // Clear pending assignments
        $this->pendingManagerAssignments = [];
    }

    /**
     * Add a pending manager assignment for deferred resolution.
     *
     * @param int    $userId       The GLPI user ID
     * @param string $managerEmail The manager's email address
     */
    private function addPendingManagerAssignment(int $userId, string $managerEmail): void
    {
        $this->pendingManagerAssignments[$userId] = $managerEmail;
    }

    /**
     * Build user object from Okta data.
     *
     * @param array $user The Okta user data
     *
     * @return array The user object for GLPI
     */
    private function buildUserObject(array $user): array
    {
        $userObject = [];
        
        foreach (self::API_MAPPINGS as $key => $value) {
            if (isset($user[$value])) {
                $userObject[$key] = $user[$value];
            }
        }
        
        return $userObject;
    }

    /**
     * Find existing user in GLPI database.
     *
     * @param array $user         The Okta user data
     * @param array $config       Configuration values
     * @param array $oidcMappings OIDC field mappings
     *
     * @return int|false The existing user ID or false
     */
    private function findExistingUser(array $user, array $config, array $oidcMappings)
    {
        $mappingName = self::API_MAPPINGS[$oidcMappings[$config['duplicate']]];
        
        $query = "SELECT glpi_users.id FROM glpi_users
            LEFT JOIN glpi_useremails ON glpi_users.id = glpi_useremails.users_id
            WHERE " . self::OIDC_TRANSLATION[$config['duplicate']] . " = '" . $this->db->escape($user[$mappingName]) . "'";
        
        $localUser = iterator_to_array($this->db->query($query));
        $localUser = empty($localUser) ? false : $localUser[0];

        $ID = empty($localUser) ? false : $localUser['id'];
        
        if (!$ID) {
            $checkQuery = "SELECT glpi_users.id FROM glpi_users
                WHERE name = '" . $this->db->escape($user[self::API_MAPPINGS[$oidcMappings['name']]]) . "' 
                AND authtype IN (" . Auth::EXTERNAL . ", " . Auth::LDAP . ")";
            $isNameAlreadyTaken = iterator_to_array($this->db->query($checkQuery));
            
            if (isset($isNameAlreadyTaken[0]['id'])) {
                $ID = $isNameAlreadyTaken[0]['id'];
            }
        }
        
        return $ID;
    }

    /**
     * Handle user creation or full update.
     *
     * @param int|false   $existingId   Existing user ID or false
     * @param array       $user         Okta user data
     * @param array       $userObject   Built user object
     * @param array       $oidcMappings OIDC mappings
     * @param int|null    $managerId    Manager's GLPI ID (if found)
     * @param string|null $managerEmail Manager's email (for deferred resolution)
     * @param bool        $fullImport   Whether this is a full import
     *
     * @return array [listedUser, importedUser]
     */
    private function handleUserCreationOrUpdate(
        $existingId,
        array $user,
        array $userObject,
        array $oidcMappings,
        ?int $managerId,
        ?string $managerEmail,
        bool $fullImport
    ): array {
        $newUser = new User();
        $ID = $existingId;
        
        if (!$ID) {
            $rule = new RuleRightCollection();
            $input = [
                'authtype' => Auth::EXTERNAL,
                'name' => $user[self::API_MAPPINGS[$oidcMappings['name']]],
                '_extauth' => 1,
                'add' => 1
            ];
            $input = $rule->processAllRules([], Toolbox::stripslashes_deep($input), [
                'type'   => Auth::EXTERNAL,
                'email'  => $user['email'] ?? '',
                'login'  => $user[self::API_MAPPINGS[$oidcMappings['name']]],
            ]);
            $input['_ruleright_process'] = true;

            $ID = $newUser->add($input);
            
            if (!$ID) {
                return [null, null];
            }
        }
        
        // Only add group to userObject if there's a valid group mapping configured
        if (!empty($oidcMappings['group'])) {
            $userObject[$oidcMappings['group']] = $user['group'];
        }
    
        if ($managerId !== null) {
            $userObject['users_id_supervisor'] = $managerId;
        }
    
        $userDataForOidc = array_filter($userObject, function($value) {
            return !is_array($value);
        });
        if (!empty($oidcMappings['group']) && isset($userObject[$oidcMappings['group']])) {
            $userDataForOidc[$oidcMappings['group']] = $userObject[$oidcMappings['group']];
        }
        Oidc::addUserData($userDataForOidc, $ID);
        
        if ($managerId !== null) {
            $this->userRepository->updateSupervisor($ID, $managerId);
        } elseif ($managerEmail !== null) {
            // Manager not found yet, defer for second pass
            $this->addPendingManagerAssignment($ID, $managerEmail);
        }
    
        $userObject['id'] = $ID;
        return [$userObject, $userObject];
    }

    /**
     * Deactivate users not in the listed users.
     *
     * @param array $listedUsers The users that should remain active
     * @param array $config      Configuration values
     */
    private function deactivateUnlistedUsers(array $listedUsers, array $config): void
    {
        $where = ['authtype' => Auth::EXTERNAL];
        
        if (isset($config['ldap_update']) && $config['ldap_update'] == 1) {
            $where = [
                'OR' => [
                    ['authtype' => Auth::LDAP],
                    ['authtype' => Auth::EXTERNAL]
                ]
            ];
        }
        
        $users = iterator_to_array($this->db->request([
            'SELECT' => ['id', 'is_active'],
            'FROM'   => 'glpi_users',
            'WHERE'  => $where,
        ]));
        
        $listedIds = array_map(function ($user) {
            return $user['id'];
        }, $listedUsers);
        
        foreach ($users as $user) {
            if (!in_array($user['id'], $listedIds) && $user['is_active'] == 1) {
                $this->db->updateOrDie('glpi_users', ['is_active' => 0], ['id' => $user['id']]);
            } else if (in_array($user['id'], $listedIds) && $user['is_active'] == 0) {
                $this->db->updateOrDie('glpi_users', ['is_active' => 1], ['id' => $user['id']]);
            }
        }
    }

    /**
     * Fetch a user by their Okta ID.
     *
     * @param string $id The Okta user ID
     *
     * @return array|null The user data or null
     */
    public function fetchUserById(string $id): ?array
    {
        $response = $this->client->request("/api/v1/users/{$id}?expand=manager");
        return $response['body'] ?? null;
    }
}
