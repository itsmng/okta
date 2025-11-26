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

namespace GlpiPlugin\Okta;

use CommonDBTM;
use CronTask;
use Html;
use Plugin;
use Session;
use Toolbox;
use User;
use GlpiPlugin\Okta\Api\OktaClient;
use GlpiPlugin\Okta\Repository\GlpiDatabaseAdapter;
use GlpiPlugin\Okta\Repository\UserRepository;
use GlpiPlugin\Okta\Services\GlpiLoggerAdapter;
use GlpiPlugin\Okta\Services\GroupService;
use GlpiPlugin\Okta\Services\UserImportService;

/**
 * PluginOktaConfig handles plugin configuration storage and the configuration UI.
 */
class PluginOktaConfig extends CommonDBTM
{
    /**
     * @var string Table name
     */
    public static $table = 'glpi_plugin_okta_configs';

    public static function getTable($classname = null): string
    {
        return self::$table;
    }

    /**
     * Install the plugin database table.
     *
     * @return bool
     */
    public static function install(): bool
    {
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
                  ('filter_phone_number', ''),
                  ('ldap_update', '0')
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

    /**
     * Uninstall the plugin database table.
     *
     * @return bool
     */
    public static function uninstall(): bool
    {
        global $DB;

        $table = self::getTable();

        if ($DB->tableExists($table)) {
            $query = "DROP TABLE `$table`";
            $DB->queryOrDie($query, $DB->error());
        }

        return true;
    }

    /**
     * Get all configuration values from the database.
     *
     * @return array<string, string> Configuration key-value pairs
     */
    public static function getConfigValues(): array
    {
        global $DB;

        $table = self::getTable();

        $query = "SELECT name, value FROM $table";
        $results = iterator_to_array($DB->query($query));

        $config = [];
        foreach ($results as $result) {
            $config[$result['name']] = $result['value'];
        }
        
        return $config;
    }

    /**
     * Update configuration values in the database.
     *
     * @param array $values Key-value pairs to update
     *
     * @return bool
     */
    public static function updateConfigValues(array $values): bool
    {
        global $DB;

        $table = self::getTable();
        $fields = self::getConfigValues();

        foreach (array_keys($fields) as $key) {
            if (!isset($values[$key])) {
                continue;
            }
            
            $value = $values[$key];
            if ($key == 'key') {
                $value = Toolbox::sodiumEncrypt($value);
            }
            
            $query = "UPDATE $table SET value='" . $DB->escape($value) . "' WHERE name='" . $DB->escape($key) . "'";
            $DB->query($query);
        }
        
        return true;
    }

    /**
     * Get groups from Okta API.
     *
     * @return array<string, string> Group ID => name pairs
     */
    public static function getGroups(): array
    {
        $client = OktaClient::fromConfig();
        if (!$client) {
            return [];
        }
        
        $groupService = new GroupService($client);
        return $groupService->getGroups();
    }

    /**
     * Get groups filtered by regex.
     *
     * @param string $regex The regex pattern
     *
     * @return array|false Filtered groups or false on error
     */
    public static function getGroupsByRegex(string $regex)
    {
        $client = OktaClient::fromConfig();
        if (!$client) {
            return false;
        }
        
        $groupService = new GroupService($client);
        return $groupService->getGroupsByRegex($regex);
    }

    /**
     * Get users in a specific group.
     *
     * @param string $groupId The Okta group ID
     *
     * @return array List of users
     */
    public static function getUsersInGroup(string $groupId): array
    {
        $client = OktaClient::fromConfig();
        if (!$client) {
            return [];
        }
        
        $groupService = new GroupService($client);
        return $groupService->getUsersInGroup($groupId);
    }

    /**
     * Import users from Okta.
     *
     * @param array       $authorizedGroups Groups to import from
     * @param bool        $fullImport       Whether to update existing users
     * @param string|null $userId           Specific user ID to import
     *
     * @return array List of imported users
     */
    public static function importUser(array $authorizedGroups, bool $fullImport = false, ?string $userId = null): array
    {
        global $DB;
        
        $client = OktaClient::fromConfig();
        if (!$client) {
            return [];
        }
        
        $dbAdapter = new GlpiDatabaseAdapter($DB);
        $userRepository = new UserRepository($dbAdapter);
        $logger = new GlpiLoggerAdapter();
        $groupService = new GroupService($client);
        $importService = new UserImportService($client, $groupService, $dbAdapter, $userRepository, $logger);
        
        return $importService->importUsers($authorizedGroups, $fullImport, $userId);
    }

    /**
     * Cron task to import users from Okta.
     *
     * @param CronTask|null $task The cron task instance (optional, for logging)
     *
     * @return int Number of actions performed (0 on failure, 1 on success)
     */
    public static function cronImportOktaUsers(?CronTask $task = null): int
    {
        $config = self::getConfigValues();
        
        if ($config['use_group_regex']) {
            $groupRegex = $config['group_regex'];
        } else {
            $groupRegex = '^' . preg_quote(stripslashes($config['group'] ?? ''), '/') . '$';
        }
        
        if (empty($groupRegex)) {
            return 0;
        }
        
        $groups = self::getGroupsByRegex($groupRegex);
        if (!$groups) {
            return 0;
        }
        
        $importedUsers = self::importUser($groups, (bool) $config['full_import']);
        
        if ($task) {
            $task->addVolume(count($importedUsers));
        }
        
        return 1;
    }

    /**
     * Display the configuration form.
     *
     * @return bool|void
     */
    public function showConfigForm()
    {
        global $DB;
        
        if (!Session::haveRight("plugin_okta_config", UPDATE)) {
            return false;
        }

        $fields = self::getConfigValues();
        $action = Plugin::getWebDir("okta") . "/front/config.form.php";
        $csrf = Session::getNewCSRFToken();

        $groups = self::getGroups();

        $key = Toolbox::sodiumDecrypt($fields['key']);
        $cronTask = new CronTask();
        $cronTask->getFromDBByCrit(['name' => 'ImportOktaUsers']);
        
        $oidcMappings = iterator_to_array($DB->query("SELECT * FROM glpi_oidc_mapping"))[0];
        $filteredMappings = [];
        foreach ($oidcMappings as $k => $value) {
            if (!in_array($k, ['picture', 'locale', 'group', 'date_mod', 'id'])) {
                $filteredMappings[$k] = $value;
            }
        }

        $this->renderConfigForm($fields, $action, $csrf, $groups, $key, $cronTask, $filteredMappings, $oidcMappings);
    }

    /**
     * Render the configuration form HTML.
     *
     * @param array    $fields           Configuration fields
     * @param string   $action           Form action URL
     * @param string   $csrf             CSRF token
     * @param array    $groups           Available Okta groups
     * @param string   $key              Decrypted API key
     * @param CronTask $cronTask         Cron task object
     * @param array    $filteredMappings OIDC mappings for normalization
     * @param array    $oidcMappings     Full OIDC mappings
     */
    private function renderConfigForm(
        array $fields,
        string $action,
        string $csrf,
        array $groups,
        string $key,
        CronTask $cronTask,
        array $filteredMappings,
        array $oidcMappings
    ): void {
        echo "<div class='first-bloc'>";
        echo "<form method='post' action='" . htmlspecialchars($action) . "'>";
        echo "<table class='tab_cadre'>";
        echo "<tbody>";
        
        // API Configuration header
        echo "<tr><th colspan='5'>" . __('Okta API Configuration', 'okta') . "</th></tr>";
        
        // API endpoint
        echo "<tr>";
        echo "<td colspan='2'>" . __('API endpoint', 'okta') . "</td>";
        echo "<td colspan='3'><input type='text' name='url' value='" . htmlspecialchars($fields['url']) . "'></td>";
        echo "</tr>";
        
        // API key
        echo "<tr>";
        echo "<td colspan='2'>API key</td>";
        echo "<td colspan='3'><input type='text' name='key' value='" . htmlspecialchars($key) . "'></td>";
        echo "</tr>";
        
        // Duplicate key
        echo "<tr>";
        echo "<td colspan='2'>Duplicate key</td>";
        echo "<td colspan='3'><select name='duplicate'>";
        foreach ($filteredMappings as $k => $value) {
            $selected = ($k == $fields['duplicate']) ? "selected" : "";
            echo "<option value='$k' $selected>$k (" . htmlspecialchars($value) . ")</option>";
        }
        echo "</select></td>";
        echo "</tr>";
        
        // Normalization header
        echo "<tr><th colspan='5'>" . __("Text excluded from normalization", "okta") . "</th></tr>";
        echo "<tr><td colspan='5'>" . __("example: (@.*)$ will remove trailing emails when importing users", 'okta') . "</td></tr>";
        
        // Normalization/Filter rows
        foreach ($filteredMappings as $k => $value) {
            $useNormChecked = (($fields['use_norm_' . $k] ?? '0') == '1') ? "checked" : "";
            $normDisabled = (($fields['use_norm_' . $k] ?? '0') == '1') ? "" : "disabled";
            $useFilterChecked = (($fields['use_filter_' . $k] ?? '0') == '1') ? "checked" : "";
            $filterDisabled = (($fields['use_filter_' . $k] ?? '0') == '1') ? "" : "disabled";
            
            echo "<tr>";
            echo "<td>" . $k . " (" . htmlspecialchars($value) . ")</td>";
            echo "<td>" . __("Normalize", "okta") . "</td>";
            echo "<td>";
            echo "<input type='hidden' name='use_norm_$k' value='0'>";
            echo "<input type='checkbox' name='use_norm_$k' value='1' $useNormChecked onclick=\"\$('#normalize_$k').prop('disabled', !this.checked);\">";
            echo "<input type='text' id='normalize_$k' name='norm_$k' value='" . htmlspecialchars($fields['norm_' . $k] ?? "") . "' $normDisabled>";
            echo "</td>";
            echo "<td>" . __("Filter", "okta") . "</td>";
            echo "<td>";
            echo "<input type='hidden' name='use_filter_$k' value='0'>";
            echo "<input type='checkbox' name='use_filter_$k' value='1' $useFilterChecked onclick=\"\$('#filter_$k').prop('disabled', !this.checked);\">";
            echo "<input type='text' id='filter_$k' name='filter_$k' value='" . htmlspecialchars($fields['filter_' . $k] ?? "") . "' $filterDisabled>";
            echo "</td>";
            echo "</tr>";
        }
        
        if ($groups) {
            // Import users header
            echo "<tr><th colspan='6'>" . __("Import users", "okta") . "</th></tr>";
            
            // Regex/Group selection
            $regexChecked = $fields['use_group_regex'] ? 'checked' : '';
            $regexStyle = !$fields['use_group_regex'] ? 'style="display: none" disabled' : '';
            $groupStyle = $fields['use_group_regex'] ? 'style="display: none" disabled' : '';
            
            echo "<tr>";
            echo "<td colspan='2'>" . __("Use Regex", "okta") . "</td>";
            echo "<td><input type='hidden' name='use_group_regex' value='0'><input type='checkbox' name='use_group_regex' id='regex_group_checkbox' value='1' $regexChecked></td>";
            echo "<td>" . _n("Group", "Groups", 1) . "</td>";
            echo "<td>";
            echo "<input type='text' name='group_regex' id='group_regex' value='" . htmlspecialchars($fields['group_regex']) . "' $regexStyle>";
            echo "<select name='group' id='group' $groupStyle><option value=''>-----</option>";
            foreach ($groups as $gid => $group) {
                echo "<option value='" . htmlspecialchars(stripslashes($group)) . "' data-gid='$gid'>" . htmlspecialchars(stripslashes($group)) . "</option>";
            }
            echo "</select>";
            echo "</td></tr>";
            
            // User selection
            echo "<tr>";
            echo "<td colspan='2'>" . _n("User", "Users", 1) . "</td>";
            echo "<td colspan='3'><select name='user' id='user'><option value='-1'>-----</option></select></td>";
            echo "</tr>";
            
            // Options row
            $fullImportChecked = $fields['full_import'] ? 'checked' : '';
            $deactivateChecked = $fields['deactivate'] ? 'checked' : '';
            $ldapUpdateChecked = ($fields['ldap_update'] ?? '0') ? 'checked' : '';
            $ldapDisabled = (!isset($fields['deactivate']) || !$fields['deactivate']) ? 'disabled' : '';
            
            echo "<tr>";
            echo "<td>" . __('Update existing users', 'okta') . "</td>";
            echo "<td><input type='hidden' name='full_import' value='0'><input type='checkbox' name='full_import' value='1' $fullImportChecked></td>";
            echo "<td>" . __('Deactivate unlisted users', 'okta') . "</td>";
            echo "<td><input type='hidden' name='deactivate' value='0'><input type='checkbox' name='deactivate' value='1' id='deactivate-checkbox' $deactivateChecked></td>";
            echo "<td>" . __('Activate/Deactivate LDAP users', 'okta') . "</td>";
            echo "<td><input type='hidden' name='ldap_update' value='0'><input type='checkbox' name='ldap_update' value='1' id='ldap-checkbox' $ldapUpdateChecked $ldapDisabled></td>";
            echo "</tr>";
            
            // Cron link
            echo "<tr class='center'><td colspan='5'><a href='" . $cronTask->getLinkURL() . "'>" . CronTask::getTypeName() . "</a></td></tr>";
        }
        
        // Submit buttons
        echo "<tr>";
        echo "<td class='center' colspan='3'><input type='submit' name='update' class='submit' value='" . __('Save') . "'></td>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='$csrf'>";
        if ($groups) {
            echo "<td class='center' colspan='2'><input type='submit' name='import' class='submit' value='" . __('Import', 'okta') . "'></td>";
        }
        echo "</tr>";
        
        echo "</tbody></table></form>";
        
        // Render imported users table
        $this->renderImportedUsersTable($oidcMappings);
        
        // JavaScript
        $this->renderFormScript($action);
        
        echo "</div>";
    }

    /**
     * Render the JavaScript for the configuration form.
     *
     * @param string $action Form action URL
     */
    private function renderFormScript(string $action): void
    {
        $escapedAction = htmlspecialchars($action);
        echo <<<SCRIPT
<script>
$(function() {
    $('#user').select2({width: '100%'});
});
document.getElementById('deactivate-checkbox').addEventListener('change', function() {
    $('#ldap-checkbox').prop('disabled', !this.checked);
});
document.getElementById('group').addEventListener('change', function() {
    var group = this.querySelector('option:checked').getAttribute('data-gid');
    var user = document.getElementById('user');
    user.innerHTML = '<option value="">Please wait...</option>';
    fetch('{$escapedAction}?action=getUsers&group=' + group)
        .then(response => response.json())
        .then(data => {
            $('#user').html('');
            $('#user').append('<option value="-1">-----</option>');
            for (const [key, value] of Object.entries(data)) {
                $('#user').append('<option value="' + key + '">' + value + '</option>');
            }
        });
});
document.getElementById('regex_group_checkbox').addEventListener('change', function() {
    const value = this.checked;
    const dropdown = document.getElementById('group');
    const regex = document.getElementById('group_regex');
    if (value) {
        $(dropdown).hide().prop('disabled', true);
        $(regex).show().removeAttr('disabled');
    } else {
        $(dropdown).show().removeAttr('disabled');
        $(regex).hide().prop('disabled', true);
    }
});
</script>
SCRIPT;
    }

    /**
     * Render the imported users table if available in session.
     *
     * @param array $oidcMappings OIDC field mappings
     */
    private function renderImportedUsersTable(array $oidcMappings): void
    {
        if (!isset($_SESSION['okta_imported_users'])) {
            return;
        }

        echo "<table class='tab_cadre'><tbody>";
        echo "<tr><th colspan='3'>" . __('Users imported', 'okta') . "</th></tr>";
        echo "<tr><th>" . __('Name') . "</th><th>" . __('Complete name') . "</th><th>" . _n('Group', 'Groups', 2) . "</th></tr>";
        
        foreach ($_SESSION['okta_imported_users'] as $user) {
            $name = htmlspecialchars($user[$oidcMappings['name']] ?? '');
            $givenName = htmlspecialchars($user[$oidcMappings['given_name']] ?? '');
            $familyName = htmlspecialchars($user[$oidcMappings['family_name']] ?? '');
            $userUrl = User::getFormURLWithID($user['id']);
            
            echo "<tr>";
            echo "<td><a href='$userUrl'>$name</a></td>";
            echo "<td>$givenName $familyName</td>";
            echo "<td>";
            if (isset($user[$oidcMappings['group']])) {
                foreach ($user[$oidcMappings['group']] as $group) {
                    echo htmlspecialchars(stripslashes($group)) . ', ';
                }
            }
            echo "</td></tr>";
        }
        
        unset($_SESSION['okta_imported_users']);
        echo "</tbody></table>";
    }
}
