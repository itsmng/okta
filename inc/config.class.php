<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2022 ITSM-NG and contributors.
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
class PluginOktaConfig extends CommonDBTM {

    static public $API_MAPPINGS = [
        'sub' => 'id',
        'name' => 'displayName',
        'profile' => 'profileUrl',
        'nickname' => 'nickName',
        'family_name' => 'lastName',
        'given_name' => 'firstName',
        'email' => 'email',
        'phone_number' => 'mobilePhone',
        'preferred_username' => 'login',
    ];
    static public $OIDC_TRANSLATION = [
        'name' => 'name',
        'given_name' => 'firstname',
        'family_name' => 'realname',
        'phone_number' => 'phone',
        'email' => 'email'
    ];

    static function install() {
        global $DB;

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = <<<SQL
              CREATE TABLE `$table` (
                  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'RELATION to glpi_profiles (id)' ,
                  `name` VARCHAR(255) collate utf8_unicode_ci NOT NULL,
                  `value` TEXT collate utf8_unicode_ci default NULL,
                  PRIMARY KEY (`id`)
              ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SQL;

            $DB->queryOrDie($query, $DB->error());

            $addquery = <<<SQL
              INSERT INTO `$table` (name, value)
              VALUES ('url', ''),
                  ('key', ''),
                  ('duplicate', 'id'),
                  ('use_group_regex', '0'),
                  ('group_regex', ''),
                  ('full_import', '0'),
                  ('deactivate', '0'),
                  ('use_norm_id', '0'),
                  ('use_norm_name', '0'),
                  ('use_norm_given_name', '0'),
                  ('use_norm_family_name', '0'),
                  ('use_norm_email', '0'),
                  ('use_norm_phone_number', '0'),
                  ('norm_id', ''),
                  ('norm_name', ''),
                  ('norm_given_name', ''),
                  ('norm_family_name', ''),
                  ('norm_phone_number', ''),
                  ('use_filter_id', '0'),
                  ('use_filter_name', '0'),
                  ('use_filter_given_name', '0'),
                  ('use_filter_family_name', '0'),
                  ('use_filter_email', '0'),
                  ('use_filter_phone_number', '0'),
                  ('filter_id', ''),
                  ('filter_name', ''),
                  ('filter_given_name', ''),
                  ('filter_family_name', ''),
                  ('filter_email', ''),
                  ('filter_phone_number', '')
SQL;

            $DB->queryOrDie($addquery, $DB->error());
        } else if (PLUGIN_OKTA_VERSION == "1.2.2") {
            $query = <<<SQL
              INSERT INTO `$table` (name, value)
              VALUES ('duplicate', 'id')
SQL;
            $DB->queryOrDie($query, $DB->error());
        } else if (PLUGIN_OKTA_VERSION == "1.3.3") {
            $query = <<<SQL
              INSERT INTO `$table` (name, value)
              VALUES ('full_import', '0'),
                  ('use_group_regex', '0'),
                  ('group_regex', '')
SQL;
            $DB->queryOrDie($query, $DB->error());
        } else if (PLUGIN_OKTA_VERSION == "1.4.3") {
            $query = <<<SQL
              INSERT INTO `$table` (name, value)
              VALUES ('use_norm_id', '0'),
                  ('use_norm_name', '0'),
                  ('use_norm_given_name', '0'),
                  ('use_norm_family_name', '0'),
                  ('use_norm_email', '0'),
                  ('use_norm_email', '0'),
                  ('use_norm_phone_number', '0'),
                  ('norm_id', ''),
                  ('norm_name', ''),
                  ('norm_given_name', ''),
                  ('norm_family_name', ''),
                  ('norm_phone_number', '')
SQL;
            $DB->queryOrDie($query, $DB->error());
        } else if (PLUGIN_OKTA_VERSION == "1.5.0") {
            $query = <<<SQL
              INSERT INTO `$table` (name, value)
              VALUES ('use_filter_id', '0'),
                  ('use_filter_name', '0'),
                  ('use_filter_given_name', '0'),
                  ('use_filter_family_name', '0'),
                  ('use_filter_email', '0'),
                  ('use_filter_phone_number', '0'),
                  ('filter_id', ''),
                  ('filter_name', ''),
                  ('filter_given_name', ''),
                  ('filter_family_name', ''),
                  ('filter_email', ''),
                  ('filter_phone_number', '')
SQL;
            $DB->queryOrDie($query, $DB->error());
        } else if (PLUGIN_OKTA_VERSION == "1.6.0") {
            $query = <<<SQL
              INSERT INTO `$table` (name, value)
              VALUES ('deactivate', '0')
SQL;
            $DB->queryOrDie($query, $DB->error());
        } else if (PLUGIN_OKTA_VERSION == "1.6.6") {
            $query = <<<SQL
              INSERT INTO `$table` (name, value)
              VALUES ('ldap_update', '0')
SQL;
            $DB->queryOrDie($query, $DB->error());
        }

        return true;
    }

    static function uninstall() {
        global $DB;

        $table = self::getTable();

        if ($DB->tableExists($table)) {
            $query = <<<SQL
              DROP TABLE `$table`
SQL;

            $DB->queryOrDie($query, $DB->error());
        }

        return true;
    }

    static private function getConfigValues() {
        global $DB;

        $table = self::getTable();

        $query = <<<SQL
          SELECT name, value from $table
SQL;

        $results = iterator_to_array($DB->query($query));

        foreach($results as $id => $result) {
            $results[$result['name']] = $result['value'];
            unset($results[$id]);
        }
        return $results;
    }

    static function updateConfigValues($values) {
        global $DB;

        $table = self::getTable();
        $fields = self::getConfigValues();

        foreach (array_keys($fields) as $key) {
            if (!isset($values[$key])) continue;
            if ($key == 'key') {
                $values[$key] = Toolbox::sodiumEncrypt($values[$key]);
            }
            $query = <<<SQL
              UPDATE $table
              SET value='{$values[$key]}'
              WHERE name='{$key}'
SQL;
            $DB->query($query);
        }
        return true;
    }

    static private function request($uri, $method = 'GET', $body = null) {
        $ch = curl_init();
        $values = self::getConfigValues();
        $key = Toolbox::sodiumDecrypt($values['key']);
        $responseHeader = [];
        $url = $values['url'];
        if (strpos($uri, $url) === false) {
            $url = $url . '/' . $uri;
        } else {
            $url = $uri;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeader) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) == 2) {
                $responseHeader[strtolower(trim($header[0]))][] = trim($header[1]);
            }
            return $len;
        });

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: SSWS ' . $key,
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseBody = substr($response, $headerSize);

        if (curl_errno($ch)) {
            Session::addMessageAfterRedirect(__('Error connecting to Okta API: ' . curl_error($ch)), 'error');
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $jsonResponse = json_decode($responseBody, true);

        if (!$response) {
            Session::addMessageAfterRedirect(__('Error connecting to Okta API'), 'error');
            return false;
        } else if (isset($jsonResponse['errorCode']) || !$jsonResponse) {
            Session::addMessageAfterRedirect(__('Invalid API key'), 'error');
            return false;
        }

        return ['header' => $responseHeader, 'body' => $jsonResponse];
    }

    static function getGroups() {
        $groupsObjects = self::request("/api/v1/groups")['body'];
        $groups = [];
        if ($groupsObjects) {
            foreach ($groupsObjects as $group) {
                $groups[$group['id']] = addslashes($group['profile']['name']);
            }
        }
        return $groups;
    }

    static function getGroupsByRegex($regex) {
        $groups = self::getGroups();
        $regex = stripslashes($regex);

        $filteredGroups = [];

        foreach ($groups as $key => $value) {
            try {
                $groupName = stripslashes($value);
                if (preg_match("/$regex/i", $groupName)) {
                    $filteredGroups[$key] = addslashes($groupName);
                }
            } catch (Exception $e) {
                return false;
            }
        }
        return $filteredGroups;
    }

    static function fetchUserById($id) {
        return self::request("/api/v1/users/" . $id)['body'];
    }

    static function parseLinkHeader($linkHeader) {
        $links = [];
        foreach ($linkHeader as $part) {
            if (preg_match('/<(.*?)>;\s*rel="(.*?)"/', $part, $matches)) {
                $matches[1] = html_entity_decode($matches[1]);
                $links[$matches[2]] = $matches[1];
            }
        }
        return $links;
    }

    static function getUsersInGroup($group) {
        $uri = "/api/v1/groups/" . $group . "/users";
        $response = [];
        while ($uri) {
            $currentList = self::request($uri);
            if (!isset($currentList['header']['link'])) {
                return $response;
            }
            $links = self::parseLinkHeader($currentList['header']['link']);
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

    private static function createOrUpdateUser($user, $config, $fullImport = false) {
        global $DB;

        $OidcMappings = iterator_to_array($DB->query("SELECT * FROM glpi_oidc_mapping"))[0];

        foreach (self::$OIDC_TRANSLATION as $key => $value) {
            $inputName = self::$API_MAPPINGS[$OidcMappings[$key]];
            if ($config['use_norm_' . $key] == 1) {
                $user[$inputName] = preg_replace('/'.$config['norm_' . $key].'/', '', $user[$inputName]);
                if ($user[$inputName] == '') return false;
            }
            if ($config['use_filter_' . $key] == 1) {
                if (!preg_match('/'.$config['filter_' . $key].'/', $user[$inputName])) {
                    return false;
                }
            }
        }

        $mappingName = self::$API_MAPPINGS[$OidcMappings[$config['duplicate']]];
        if (!isset($user[$mappingName])) return false;

        $newUser = new User();
        $userObject = [];
        foreach (self::$API_MAPPINGS as $key => $value) {
            if (isset($user[$value])) {
                $userObject[$key] = $user[$value];
            }
        };

        $query = "SELECT glpi_users.id FROM glpi_users
            LEFT JOIN glpi_useremails ON glpi_users.id = glpi_useremails.users_id
            WHERE " . self::$OIDC_TRANSLATION[$config['duplicate']] . " = '" . $user[$mappingName] . "'";
        $localUser = iterator_to_array($DB->query($query));
        $localUser = empty($localUser) ? false : $localUser[0];

        $ID = empty($localUser) ? false : $localUser['id'];
        if (!$ID) {
           $checkQuery = "SELECT glpi_users.id FROM glpi_users
               WHERE name = '" . $user[self::$API_MAPPINGS[$OidcMappings['name']]] . "' AND authtype = " . Auth::EXTERNAL;
           $isNameAlreadyTaken = iterator_to_array($DB->query($checkQuery));
           if (isset($isNameAlreadyTaken[0]['id'])) {
               $ID = $isNameAlreadyTaken[0]['id'];
           }
        }
        if (!$ID || $fullImport ) {
            if (!$ID) {
                $localUser = iterator_to_array($DB->query($query));
                $rule = new RuleRightCollection();
                $input = [
                    'authtype' => Auth::EXTERNAL,
                    'name' => $user[self::$API_MAPPINGS[$OidcMappings['name']]],
                    '_extauth' => 1,
                    'add' => 1
                ];
                $input = $rule->processAllRules([], Toolbox::stripslashes_deep($input), [
                    'type'   => Auth::EXTERNAL,
                    'email'  => $user["email"] ?? '',
                    'login'  => $user[self::$API_MAPPINGS[$OidcMappings['name']]],
                ]);
                $input['_ruleright_process'] = true;

                $ID = $newUser->add($input);
            }
            $userObject[$OidcMappings['group']] = $user['group'];
            Oidc::addUserData($userObject, $ID);
            $userObject['id'] = $ID;
            return [$userObject, $userObject];
        }
        $userObject['id'] = $ID;
        return [$userObject, NULL];
    }

    static function importUser($authorizedGroups, $fullImport = false, $userId = NULL) {
        global $DB;

        $importedUsers = [];
        $listedUsers = [];
        $config = self::getConfigValues();
        if (!$userId) {
            $userList = [];
            echo "Retrieving users...\n";
            foreach ($authorizedGroups as $key => $group) {
                $usersInGroup = self::getUsersInGroup($key);
                if (!$usersInGroup || empty($usersInGroup)) {
                    continue;
                }
                foreach ($usersInGroup as $user) {
                    if (!isset($userList[$user['id']])) {
                        $content = $user['profile'];
                        $content += ['id' => $user['id']];
                        $content['group'] = [$authorizedGroups[$key]];
                        $userList[$user['id']] = $content;
                    } else {
                        $userList[$user['id']]['group'][] = $authorizedGroups[$key];
                    }
                }
            }
            echo "Retrieved " . count($userList) . " users\n";
            echo "Importing users...\n";
            foreach ($userList as $user) {
                [$listedUser, $importedUser] = self::createOrUpdateUser($user, $config, $fullImport);
                if ($listedUser) {
                    $listedUsers[] = $listedUser;
                }
                if ($importedUser) {
                    $importedUsers[] = $importedUser;
                }
            }
            if ($config['deactivate'] == 1) {
                $where = ['authtype' => Auth::EXTERNAL];
                if ($config['ldap_update'] == 1) {
                    $where['OR'] = [
                        ['authtype' => Auth::LDAP],
                        ['authtype' => Auth::EXTERNAL]
                    ];
                    unset($where['authtype']);
                }
                $users = iterator_to_array($DB->request([
                    'SELECT' => ['id', 'is_active'],
                    'FROM'   => 'glpi_users',
                    'WHERE'  => $where,
                ]));
                $listedIds = array_map(function($user) {
                    return $user['id'];
                }, $listedUsers);
                foreach ($users as $user) {
                    if (!in_array($user['id'], $listedIds) && $user['is_active'] == 1) {
                        $DB->updateOrDie('glpi_users', ['is_active' => 0], ['id' => $user['id']]);
                    } else if (in_array($user['id'], $listedIds) && $user['is_active'] == 0) {
                        $DB->updateOrDie('glpi_users', ['is_active' => 1], ['id' => $user['id']]);
                    }
                }
            }
        } else {
            $userObject = self::request("api/v1/users/{$userId}")['body'];
            $user = $userObject['profile'];
            $user['id'] = $userId;
            $user['group'] = $authorizedGroups;
            $importedUser = self::createOrUpdateUser($user, $config, $fullImport);
            if ($importedUser) {
                $importedUsers[] = $importedUser;
            }
        }
        return $importedUsers;
    }

    static function cronImportOktaUsers() {
        $config = self::getConfigValues();
        if ($config['use_group_regex']) {
            $groups = self::getGroupsByRegex($config['group_regex']);
            if (!$groups) {
                return false;
            }
        } else {
            $groups = [$config['group']];
        }
        self::importUser($groups, $config['full_import']);
        return true;
    }

    /**
     * Displays the configuration page for the plugin
     *
     * @return void
     */
    public function showConfigForm() {
        global $DB;
        if (!Session::haveRight("plugin_okta_config",UPDATE)) {
            return false;
        }

        $fields = self::getConfigValues();
        $action = self::getFormURL();
        $csrf = Session::getNewCSRFToken();

        $groups = self::getGroups();

        $key = Toolbox::sodiumDecrypt($fields['key']);
        $cronTask = new CronTask();
        $cronTask->getFromDBByCrit(['name' => 'ImportOktaUsers']);
?>
<div class='first-bloc'>
        <form method="post" action="<?php echo $action ?>">
                <table class="tab_cadre">
                    <tbody>
                        <tr>
                            <th colspan="5"><?php echo __('Okta API Configuration', 'okta') ?></th>
                        </tr>
                        <tr>
                            <td colspan="2"><?php echo __('API endpoint', 'okta')?></td>
                            <td colspan="3"><input type="text" name="url" value="<?php echo $fields['url'] ?>"></td>
                        </tr>
                        <tr>
                            <td colspan="2">API key</td>
                            <td colspan="3"><input type="text" name="key" value="<?php echo $key ?>"></td>
                        </tr>
                        <tr>
                            <td colspan="2">Duplicate key</td>
                            <td colspan="3">
                                <select name="duplicate">
<?php
        $OidcMappings = iterator_to_array($DB->query("SELECT * FROM glpi_oidc_mapping"))[0];
        $filteredMappings = [];
        foreach ($OidcMappings as $key => $value) {
            if (!in_array($key, ['picture', 'locale', 'group', 'date_mod', 'id'])) {
                $filteredMappings[$key] = $value;
                echo "<option value=\"$key\" ". (($key == $fields['duplicate']) ? "selected" : "") ." >$key (" . $value . ")</option>";
            }
        }
?>
                                </select>
                            </td>
                        <tr>
                            <th colspan="5"><?php echo __("Text excluded from normalization", "okta") ?></th>
                        </tr> 
                        <tr>
                            <td colspan="5"><?php echo __("example: (@.*)$ will remove trailing emails when importing users", 'okta')?></td>
                        </tr>
<?php foreach ($filteredMappings as $key => $value) { ?>
                        <tr>
                            <td><?php echo $key . " (" . $value . ") "; ?></td>
                            <td> <?php echo __("Normalize", "okta"); ?></td>
                            <td>
                                <input type="hidden" name="use_norm_<?php echo $key; ?>" value="0">
                                <input type="checkbox" name="use_norm_<?php echo $key; ?>"
                                    value="1" <?php echo ($fields['use_norm_' . $key] == 1) ? "checked" : ""; ?>
                                    onclick="$('#normalize_<?php echo $key; ?>').prop('disabled', !this.checked);"
                                >
                                <input type="text" id="normalize_<?php echo $key; ?>" name="norm_<?php echo $key; ?>"
                                    value="<?php echo htmlspecialchars($fields['norm_'.$key] ?? ""); ?>"
                                    <?php echo ($fields['use_norm_'.$key] == 1) ? "" : "disabled"; ?>>
                            </td>
                            <td><?php echo __("Filter", "okta") ?></td>
                            <td>
                                <input type="hidden" name="use_filter_<?php echo $key; ?>" value="0">
                                <input type="checkbox" name="use_filter_<?php echo $key; ?>"
                                    value="1" <?php echo ($fields['use_filter_' . $key] == 1) ? "checked" : ""; ?>
                                    onclick="$('#filter_<?php echo $key; ?>').prop('disabled', !this.checked);"
                                >
                                <input type="text" id="filter_<?php echo $key; ?>" name="filter_<?php echo $key; ?>"
                                    value="<?php echo htmlspecialchars($fields['filter_'.$key] ?? ""); ?>"
                                    <?php echo ($fields['use_filter_'.$key] == 1) ? "" : "disabled"; ?>>
                            </td>
                        </tr>
<?php } ?>
<?php if ($groups) { ?>
                            <tr>
                                <th colspan="6"><?php echo __("Import users", "okta") ?></th>
                            </tr>
                            <tr>
                                <td colspan="2"><?php echo __("Use Regex", "okta") ?></td>
                                <td>
                                    <input type="hidden" name="use_group_regex" value='0'>
                                    <input type="checkbox" name="use_group_regex" id="regex_group_checkbox" value='1' <?php echo $fields['use_group_regex'] ? 'checked' : '' ?>>
                                </td>
                                <td><?php echo _n("Group", "Groups", 1) ?></td>
                                <td>
                                    <input type="text" name="group_regex" id="group_regex" value="<?php echo $fields['group_regex'] ?>" <?php echo !$fields['use_group_regex'] ? 'style="display: none" disabled' : '' ?>>
                                    <select name="group" id="group" <?php echo $fields['use_group_regex'] ? 'style="display: none" disabled' : '' ?>>
                                        <option value="">-----</option>
<?php
        foreach($groups as $key => $group) {
            echo "<option value='".htmlspecialchars(stripslashes($group))."' data-gid='".$key."'>".stripslashes($group)."</option>";
        }
?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2"><?php echo _n("User", "Users", 1) ?></td>
                                <td colspan='3'>
                                    <select name="user" id="user">
                                        <option value="-1">-----</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><?php echo __('Update existing users', 'okta') ?></td>
                                <td>
                                    <input type="hidden" name="full_import" value='0' >
                                    <input type="checkbox" name="full_import" value='1' <?php echo $fields['full_import'] ? 'checked' : '' ?>>
                                </td>
                                <td><?php echo __('Deactivate unlisted users', 'okta') ?></td>
                                <td>
                                    <input type="hidden" name="deactivate" value='0'>
                                    <input type="checkbox" name="deactivate" value='1' id="deactivate-checkbox" <?php echo $fields['deactivate'] ? 'checked' : '' ?>>
                                </td>
                                <td><?php echo __('Activate/Deactivate LDAP users', 'okta') ?></td>
                                <td>
                                    <input type="hidden" name="ldap_update" value='0'>
                                    <input type="checkbox" name="ldap_update" value='1' id="ldap-checkbox"
                                        <?php echo $fields['ldap_update'] ? 'checked' : '' ?>
                                        <?php echo (!isset($fields['deactivate']) || !$fields['deactivate']) ? 'disabled' : '' ?>
                                    >
                                </td>
                            </tr>
                            <tr class="center">
                                <td colspan="5">
                                    <a href="<?php echo $cronTask->getLinkURL() ?>"><?php echo CronTask::getTypeName() ?></a>
                            <tr>
<?php } ?>
                                <td class="center" colspan="3">
                                    <input type="submit" name="update" class="submit" value="<?php echo __('Save')?>">
                                </td>
                                <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf ?>">
<?php if ($groups) { ?>
                                <td class="center" colspan="2">
                                    <input type="submit" name="import" class="submit" value="<?php echo __('Import', 'okta')?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            <?php if (isset($_SESSION['okta_imported_users'])) { ?>
                <table class="tab_cadre">
                    <tbody>
                        <tr>
                        <th colspan="3"><?php __('Users imported', 'okta'); ?></th>
                        </tr>
                        <tr>
                        <th><?php echo __('Name'); ?></th>
                        <th><?php echo __('Complete name'); ?></th>
                        <th><?php echo _n('Group', 'Groups', 2); ?></th>
                        </tr>
                        <?php foreach ($_SESSION['okta_imported_users'] as $user) { ?>
                            <tr>
                                <td><a href="<?php echo User::getFormURLWithID($user['id']); ?>"><?php echo $user[$OidcMappings['name']] ?></a></td>
                                <td><?php echo $user[$OidcMappings['given_name']] . ' ' . $user[$OidcMappings['family_name']] ?></td>
                                <td><?php 
                                    foreach ($user[$OidcMappings['group']] as $group) {
                                        echo stripslashes($group) . ', ';
                                    }
                                ?></td>
                            </tr>
                        <?php } 
                           unset($_SESSION['okta_imported_users']);
                        ?>
                    </tbody>
                </table>
            <?php } ?>
        <script>
        $(function() {
            $('#user').select2({width: '100%'});
        })
        document.getElementById('deactivate-checkbox').addEventListener('change', function() {
            $('#ldap-checkbox').prop('disabled', !this.checked);
        });
        document.getElementById('group').addEventListener('change', function() {
            var group = this.querySelector('option:checked').getAttribute('data-gid');
            var user = document.getElementById('user');
            user.innerHTML = '';
            // add loading animation
            user.innerHTML = '<option value="">Please wait...</option>';
            fetch('<?php echo $action ?>?action=getUsers&group=' + group)
                .then(response => response.json())
                .then(data => {
                $("#user").html('');
                $("#user").append('<option value="-1">-----</option>');
                for (const [key, value] of Object.entries(data)) {
                    $("#user").append('<option value="' + key + '">' + value + '</option>');
                }
            });
        });
        document.getElementById('regex_group_checkbox').addEventListener('change', function() {
            const value = this.checked;
            const dropdown = document.getElementById('group');
            const regex = document.getElementById('group_regex');

            $(dropdown).prop('disabled', !value)
                $(regex).prop('disabled', value)
                if (value) {
                    $(dropdown).hide();
                    $(dropdown).prop('disabled', true)
                        $(regex).show();
                    $(regex).removeAttr('disabled')
                } else {
                    $(dropdown).show();
                    $(dropdown).removeAttr('disabled')
                        $(regex).hide();
                    $(regex).prop('disabled', true)
                }
        });
        </script>
</div>
<?php
                                    }
    }
}
