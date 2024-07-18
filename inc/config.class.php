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
                     ('group', '')
          SQL;

          $DB->queryOrDie($addquery, $DB->error());
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
   static private function request($url, $key, $method = 'GET', $body = null) {
        $opts = [
           'http' => [
               'method'  => $method,
               'header'  => "Accept: application/json\r\n" .
                            "Content-Type: application/json\r\n" .
                            "Authorization: SSWS " . $key,
           ]
        ];
        if ($body) {
            $opts['http']['content'] = $body;
        }
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        $jsonResponse = json_decode($response, true);
        if (!$response || count($jsonResponse) == 0) {
            Session::addMessageAfterRedirect(__('Error connecting to Okta API'), 'error');
            return false;
        } else if (isset($jsonResponse['errorCode'])) {
            Session::addMessageAfterRedirect(__('invalid API key'), 'error');
            return false;
        }
        return $jsonResponse;
   }

   static function testConnection() {
        $values = self::getConfigValues();
        $url = $values['url'];
        $key = Toolbox::sodiumDecrypt($values['key']);

        return self::request($url . "/api/v1/groups", $key);
   }

   static function fetchUserById($id) {
       $values = self::getConfigValues();
       $url = $values['url'];
       $key = Toolbox::sodiumDecrypt($values['key']);

       return self::request($url . "/api/v1/users/" . $id, $key);
   }

   static function getUsersInGroup($group) {
       $values = self::getConfigValues();
       $url = $values['url'];
       $key = Toolbox::sodiumDecrypt($values['key']);

       return self::request($url . "/api/v1/groups/" . $group . "/users", $key);
   }

   static function importUser($userId) {
      global $DB;

      $OidcMappings = iterator_to_array($DB->query("SELECT * FROM glpi_oidc_mapping"))[0];
      $localUsers = iterator_to_array($DB->query("SELECT * FROM glpi_users"));
      $localNames = array_combine(array_column($localUsers, 'id'), array_column($localUsers, 'name'));
      $newUser = new User();
      $distantUser = self::fetchUserById($userId);
      if (!$distantUser) return false;

      $userObject = [];
      $userName = $distantUser['profile'][$OidcMappings['name']];
      $ID = array_search($userName, $localNames);
  
      foreach ($OidcMappings as $key => $value) {
          $userObject[$key] = $distantUser['profile'][$value] ?? null;
      };
  
      // get user id from local by name
  
      if (!$ID) {
         $rule = new RuleRightCollection();
         $input = [
              'authtype' => Auth::EXTERNAL,
              'name' => $userObject['name'],
              '_extauth' => 1,
              'add' => 1
           ];
         $input = $rule->processAllRules([], Toolbox::stripslashes_deep($input), [
            'type'   => Auth::EXTERNAL,
            'email'  => $userObject["email"] ?? '',
            'login'  => $input["name"]
         ]);
         $input['_ruleright_process'] = true;

         $ID = $newUser->add($input);
      }
      Oidc::addUserData($distantUser['profile'], $ID);
      return true;
   }

    /**
     * Displays the configuration page for the plugin
     *
     * @return void
     */
    public function showConfigForm() {
       if (!Session::haveRight("plugin_okta_config",UPDATE)) {
           return false;
       }

       $fields = self::getConfigValues();
       $action = self::getFormURL();
       $csrf = Session::getNewCSRFToken();

       $groups = self::testConnection();

       $key = Toolbox::sodiumDecrypt($fields['key']);

       echo "<div class='first-bloc'>";
       echo <<<HTML
            <form method="post" action="{$action}">
                <table class="tab_cadre">
                    <tbody>
                        <tr>
                            <th colspan="2">Okta API Configuration</th>
                        </tr>
                        <tr>
                            <td>API endpoint</td>
                            <td><input type="text" name="url" value="{$fields['url']}"></td>
                        </tr>
                        <tr>
                            <td>API key</td>
                            <td><input type="text" name="key" value="{$key}"></td>
                        </tr>
                        <tr>
                            <td class="center" colspan="2">
                                <input type="submit" name="update" class="submit" value="Save">
                            </td>
                        </tr>
                    </tbody>
                </table>
                <input type="hidden" name="_glpi_csrf_token" value="$csrf">
            </form>
        HTML;
        if ($groups) {
            $keys = array_column($groups, 'id');
            $values = [];
            foreach ($groups as $group) {
                $values[$group['id']] = $group['profile']['name'];
            }
            $options = array_combine($keys, $values);
            echo <<<HTML
                <form method="post" action="{$action}">
                    <table class="tab_cadre">
                        <tbody>
                            <tr>
                                <th colspan="2">Import users</th>
                            </tr>
                            <tr>
                                <td>Group</td>
                                <td>
                                    <select name="group" id="group">
                                        <option value="">-----</option>
            HTML;
            echo implode('', array_map(function($key, $value) use ($options) {
                return "<option value=\"$key\">$options[$key]</option>";
            }, array_keys($options), array_values($options)));
            echo <<<HTML
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>User</td>
                                <td>
                                    <select name="user" id="user">
                                        <option value="">-----</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="center" colspan="2">
                                    <input type="submit" name="import" class="submit" value="Import">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="_glpi_csrf_token" value="$csrf">
                </form>
                <script>
                    $(document).ready(function() {
                        $('#user').select2({width: '100%'});
                    });
                    document.getElementById('group').addEventListener('change', function() {
                        var group = this.value, user = document.getElementById('user');
                        user.innerHTML = '';
                        fetch('{$action}?action=getUsers&group=' + group)
                            .then(response => response.json())
                            .then(data => {
                                $("#user").html('');
                                $("#user").append('<option value="">-----</option>');
                                for (const [key, value] of Object.entries(data)) {
                                    $("#user").append('<option value="' + key + '">' + value + '</option>');
                                }
                            });
                    });
                </script>
            HTML;
        } else {
            echo <<<HTML
                                        <p>Error connecting to Okta API</p>
            HTML;
        }
        echo "</div>";
    }
}
