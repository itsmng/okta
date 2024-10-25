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
                     ('duplicate', 'id')
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

        if (!isset($values['use_group_regex'])) $values['use_group_regex'] = false;
        if (!isset($values['full_import'])) $values['full_import'] = false;

        foreach ($fields as $key => $value) {
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
                if (preg_match("/$regex/i", $value)) {
                    $filteredGroups[$key] = $value;
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

    static function getGroupsForUser($userId) {
        $groups = self::request("/api/v1/users/" . $userId . "/groups");

        $names= [];
        foreach($groups as $group) {
            $names[$group['id']] = addslashes($group['profile']['name']);
        }
        return $names;
    }

    private static function createOrUpdateUser($userId, $authorizedGroups = [], $fullImport = false) {
        global $DB;

        $apiMappings = [
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
        $OidcTranslation = [
            'id' => 'id',
            'name' => 'name',
            'given_name' => 'firstname',
            'family_name' => 'realname',
            'phone_number' => 'phone',
            'email' => 'email'
        ];

        $config = self::getConfigValues();

        $newUser = new User();
        $OidcMappings = iterator_to_array($DB->query("SELECT * FROM glpi_oidc_mapping"))[0];
        if (!isset($OidcMappings[$OidcMappings[$config['duplicate']]])) return false;

        $distantUser = self::fetchUserById($userId);
        if (!$distantUser) return false;
        $userObject = [];
        foreach ($apiMappings as $key => $value) {
            if (isset($distantUser['profile'][$value])) {
                $userObject[$key] = $distantUser['profile'][$value];
            }
        };
        $profile = $distantUser['profile'];
        $profile += ['id' => $distantUser['id']];

        $query = "SELECT glpi_users.id FROM glpi_users
            LEFT JOIN glpi_useremails ON glpi_users.id = glpi_useremails.users_id
            WHERE " . $OidcTranslation[$config['duplicate']] . " = '" . $profile[$apiMappings[$config['duplicate']]] . "'";
        $localUser = iterator_to_array($DB->query($query));
        $localUser = empty($localUser) ? false : $localUser[0];

        $ID = empty($localUser) ? false : $localUser['id'];
        if (!$ID || $fullImport ) {
            if (!$ID) {
                $rule = new RuleRightCollection();
                $input = [
                    'authtype' => Auth::EXTERNAL,
                    'name' => $profile[$apiMappings[$OidcMappings['name']]],
                    '_extauth' => 1,
                    'add' => 1
                ];
                $input = $rule->processAllRules([], Toolbox::stripslashes_deep($input), [
                    'type'   => Auth::EXTERNAL,
                    'email'  => $profile["email"] ?? '',
                    'login'  => $profile[$apiMappings[$OidcMappings['name']]],
                ]);
                $input['_ruleright_process'] = true;

                $ID = $newUser->add($input);
            }
            $userObject[$OidcMappings['group']] = self::getGroupsForUser($userId);
            foreach (array_keys($userObject[$OidcMappings['group']]) as $key) {
                if (!in_array($key, $authorizedGroups)) {
                    unset($userObject[$OidcMappings['group']][$key]);
                }
            }
            Oidc::addUserData($userObject, $ID);
        }
        return true;
    }

    static function importUser($authorizedGroups, $fullImport = false, $userId = NULL) {
        if (!$userId) {
            foreach ($authorizedGroups as $group) {
                $userList = self::getUsersInGroup($group);
                if (!$userList) {
                    return true;
                }
                foreach ($userList as $user) {
                    if (!self::createOrUpdateUser($user['id'], $authorizedGroups, $fullImport)) {
                        return false;
                    }
                }
            }
        } else {
            if (!self::createOrUpdateUser($userId, $authorizedGroups, $fullImport)) {
                return false;
            }
        }
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
        foreach ($OidcMappings as $key => $value) {
            if (in_array($key, ['picture', 'locale', 'group'])) continue;
            echo "<option value=\"$key\" ". (($key == $fields['duplicate']) ? "selected" : "") ." >$key</option>";
        }
?>
                                </select>
                            </td>
                        </tr>
                        <tr>
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
                                    <input type="checkbox" name="use_group_regex" id="regex_group_checkbox" <?php echo $fields['use_group_regex'] ? 'checked' : '' ?>>
                                </td>
                                <td>Group</td>
                                <td>
                                    <input type="text" name="group_regex" id="group_regex" value="<?php echo $fields['group_regex'] ?>" <?php echo !$fields['use_group_regex'] ? 'style="display: none" disabled' : '' ?>>
                                    <select name="group" id="group" <?php echo $fields['use_group_regex'] ? 'style="display: none" disabled' : '' ?>>
                                        <option value="">-----</option>
<?php
            foreach($groups as $key => $group) {
                echo "<option value='".addslashes($group)."' data-gid='".$key."'>".$group."</option>";
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
                                    <input type="checkbox" name="full_import" <?php echo $fields['full_import'] ? 'checked' : '' ?>>
                                </td>
                            </tr>
                            <tr>
                                <td class="center" colspan="4">
                                    <input type="submit" name="import" class="submit" value="Import">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="_glpi_csrf_token" value="<?php echo $csrf ?>">
                </form>
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
