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
        'id' => 'id',
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
                  ('use_norm_id', '0'),
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
        $url = $values['url'];
        $key = Toolbox::sodiumDecrypt($values['key']);


        curl_setopt($ch, CURLOPT_URL, $url . '/' . $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

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

        if (curl_errno($ch)) {
            Session::addMessageAfterRedirect(__('Error connecting to Okta API: ' . curl_error($ch)), 'error');
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        $jsonResponse = json_decode($response, true);

        if (!$response) {
            Session::addMessageAfterRedirect(__('Error connecting to Okta API'), 'error');
            return false;
        } else if (isset($jsonResponse['errorCode'])) {
            Session::addMessageAfterRedirect(__('Invalid API key'), 'error');
            return false;
        }

        return $jsonResponse;
    }

    static function getGroups() {
        $groupsObjects = self::request("/api/v1/groups");
        $groups = [];
        foreach ($groupsObjects as $group) {
            $groups[$group['id']] = addslashes($group['profile']['name']);
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
        return self::request("/api/v1/users/" . $id);
    }

    static function getUsersInGroup($group) {
        return self::request("/api/v1/groups/" . $group . "/users");
    }

    private static function createOrUpdateUser($user, $fullImport = false) {
        global $DB;

        $config = self::getConfigValues();

        $OidcMappings = iterator_to_array($DB->query("SELECT * FROM glpi_oidc_mapping"))[0];
        if (!isset($OidcMappings[$OidcMappings[$config['duplicate']]])) return false;

        foreach (self::$OIDC_TRANSLATION as $key => $value) {
            if ($config['use_norm_' . $key] == 1) {
                $inputName = self::$API_MAPPINGS[$OidcMappings[$value]];
                $user[$inputName] = preg_replace('/'.$config['norm_' . $key].'/', '', $user[$inputName]);
            }
        }

        $newUser = new User();
        $userObject = [];
        foreach (self::$API_MAPPINGS as $key => $value) {
            if (isset($user[$value])) {
                $userObject[$key] = $user[$value];
            }
        };

        $query = "SELECT glpi_users.id FROM glpi_users
            LEFT JOIN glpi_useremails ON glpi_users.id = glpi_useremails.users_id
            WHERE " . self::$OIDC_TRANSLATION[$config['duplicate']] . " = '" . $user[self::$API_MAPPINGS[$config['duplicate']]] . "'";
        $localUser = iterator_to_array($DB->query($query));
        $localUser = empty($localUser) ? false : $localUser[0];

        $ID = empty($localUser) ? false : $localUser['id'];
        if (!$ID || $fullImport ) {
            if (!$ID) {
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
            return $userObject;
        }
        return NULL;
    }

    static function importUser($authorizedGroups, $fullImport = false, $userId = NULL) {
        $importedUsers = [];
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
                $importedUser = self::createOrUpdateUser($user, $fullImport);
                if ($importedUser) {
                    $importedUsers[] = $importedUser;
                }
            }
        } else {
            $userObject = self::request("api/v1/users/{$userId}");
            $user = $userObject['profile'];
            $user['id'] = $userId;
            $user['group'] = $authorizedGroups;
            $importedUser = self::createOrUpdateUser($user, $authorizedGroups, $fullImport);
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

?>
<div class='first-bloc'>
        <form method="post" action="<?php echo $action ?>">
                <table class="tab_cadre">
                    <tbody>
                        <tr>
                            <th colspan="2">Okta API Configuration</th>
                        </tr>
                        <tr>
                            <td>API endpoint</td>
                            <td><input type="text" name="url" value="<?php echo $fields['url'] ?>"></td>
                        </tr>
                        <tr>
                            <td>API key</td>
                            <td><input type="text" name="key" value="<?php echo $key ?>"></td>
                        </tr>
                        <tr>
                            <td>Duplicate key</td>
                            <td>
                                <select name="duplicate">
<?php
        $OidcMappings = iterator_to_array($DB->query("SELECT * FROM glpi_oidc_mapping"))[0];
        $filteredMappings = [];
        foreach ($OidcMappings as $key => $value) {
            if (!in_array($key, ['picture', 'locale', 'group', 'date_mod'])) {
                $filteredMappings[$key] = $value;
                echo "<option value=\"$key\" ". (($key == $fields['duplicate']) ? "selected" : "") ." >$key (" . $value . ")</option>";
            }
        }
?>
                                </select>
                            </td>
                        <tr>
                            <th colspan="2">Text excluded from normalization</th>
                        </tr> 
                        <tr>
                            <td colspan="2">example: <i>(@.*)$</i> will remove trailing emails when importing users</td>
                        </tr>
<?php foreach ($filteredMappings as $key => $value) { ?>
                        <tr>
                            <td>Normalize <?php echo $key . " (" . $value . ")"; ?></td>
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
                        </tr>
<?php } ?>
                        </tr>
                            <td class="center" colspan="2">
                                <input type="submit" name="update" class="submit" value="Save">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf ?>">
            </form>
<?php if ($groups) { ?>
            <form method="post" action="<?php echo $action ?>">
                    <table class="tab_cadre">
                        <tbody>
                            <tr>
                                <th colspan="4">Import users</th>
                            </tr>
                            <tr>
                                <td>Use Regex</td>
                                <td>
                                    <input type="hidden" name="use_group_regex" value='0'>
                                    <input type="checkbox" name="use_group_regex" id="regex_group_checkbox" value='1' <?php echo $fields['use_group_regex'] ? 'checked' : '' ?>>
                                </td>
                                <td>Group</td>
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
                                <td>User</td>
                                <td colspan='3'>
                                    <select name="user" id="user">
                                        <option value="-1">-----</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>Update existing users</td>
                                <td colspan='3'>
                                    <input type="hidden" name="full_import" value='0' >
                                    <input type="checkbox" name="full_import" value='1' <?php echo $fields['full_import'] ? 'checked' : '' ?>>
                                </td>
                            </tr>
                            <tr>
                                <td class="center" colspan="4">
                                    <b>Please save before importing</b>
                                </td>
                            <tr>
                            <tr>
                                <td class="center" colspan="4">
                                    <input type="submit" name="import" class="submit" value="Import">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf ?>">
                </form>
            <?php if (isset($_SESSION['okta_imported_users'])) { ?>
                <table class="tab_cadre">
                    <tbody>
                        <tr>
                            <th colspan="3">Users imported</th>
                        </tr>
                        <?php foreach ($_SESSION['okta_imported_users'] as $user) { ?>
                            <tr>
                                <td><?php echo $user[$OidcMappings['name']] ?></td>
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
