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

namespace GlpiPlugin\Okta;

use CommonDBTM;
use Html;
use Profile;
use ProfileRight;
use Session;

/**
 * PluginOktaProfile handles plugin permissions and profile management.
 */
class PluginOktaProfile extends CommonDBTM
{
    /**
     * @var string Table name
     */
    public static $table = 'glpi_plugin_okta_profiles';

    public static function getTable($classname = null): string
    {
        return self::$table;
    }

    /**
     * Install the plugin profile table.
     *
     * @return bool
     */
    public static function install(): bool
    {
        global $DB;

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                `id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_profiles (id)',
                `right` char(1) collate utf8_unicode_ci default NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

            $DB->queryOrDie($query, $DB->error());

            self::createAdminAccess($_SESSION['glpiactiveprofile']['id']);

            foreach (self::getRightsGeneral() as $right) {
                self::addDefaultProfileInfos($_SESSION['glpiactiveprofile']['id'], [$right['field'] => $right['default']]);
            }
        }

        return true;
    }

    /**
     * Uninstall the plugin profile table.
     *
     * @return bool
     */
    public static function uninstall(): bool
    {
        global $DB;

        if ($DB->tableExists('glpi_plugin_okta_profiles')) {
            $DB->queryOrDie("DROP TABLE `glpi_plugin_okta_profiles`", $DB->error());
        }

        // Clear profiles
        foreach (self::getRightsGeneral() as $right) {
            $query = "DELETE FROM `glpi_profilerights` WHERE `name` = '" . $DB->escape($right['field']) . "'";
            $DB->query($query);

            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
        
        return true;
    }

    /**
     * Check if user can create.
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        if (isset($_SESSION['profile'])) {
            return ($_SESSION['profile']['okta'] ?? '') === 'w';
        }
        return false;
    }

    /**
     * Check if user can view.
     *
     * @return bool
     */
    public static function canView(): bool
    {
        if (isset($_SESSION['profile'])) {
            $right = $_SESSION['profile']['okta'] ?? '';
            return $right === 'w' || $right === 'r';
        }
        return false;
    }

    /**
     * Create admin access for a profile.
     *
     * @param int $ID Profile ID
     */
    public static function createAdminAccess(int $ID): void
    {
        $myProf = new self();
        // Only create profile if it's new
        if (!$myProf->getFromDB($ID)) {
            // Add entry to permissions database giving the user write privileges
            $myProf->add([
                'id'    => $ID,
                'right' => 'w'
            ]);
        }
    }

    /**
     * Add default profile info for a profile.
     *
     * @param int   $profiles_id Profile ID
     * @param array $rights      Rights to add
     */
    public static function addDefaultProfileInfos(int $profiles_id, array $rights): void
    {
        $profileRight = new ProfileRight();
        
        foreach ($rights as $right => $value) {
            if (!countElementsInTable('glpi_profilerights', ['profiles_id' => $profiles_id, 'name' => $right])) {
                $myright = [
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => $value
                ];
                $profileRight->add($myright);
                // Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    /**
     * Handle profile change.
     */
    public static function changeProfile(): void
    {
        $prof = new self();
        
        if ($prof->getFromDB($_SESSION['glpiactiveprofile']['id'])) {
            $_SESSION["glpi_plugin_okta_profile"] = $prof->fields;
        } else {
            unset($_SESSION["glpi_plugin_okta_profile"]);
        }
    }

    /**
     * Get general rights definition.
     *
     * @return array
     */
    public static function getRightsGeneral(): array
    {
        return [
            [
                'itemtype'  => 'PluginOktaProfile',
                'label'     => __('Use okta', 'okta'),
                'field'     => 'plugin_okta_config',
                'rights'    => [UPDATE => __('Allow editing', 'whitelabel')],
                'default'   => 23
            ]
        ];
    }

    /**
     * Display the profile form.
     *
     * @param int  $profiles_id Profile ID
     * @param bool $openform    Whether to open form tag
     * @param bool $closeform   Whether to close form tag
     *
     * @return bool|void
     */
    public function showForm(int $profiles_id = 0, bool $openform = true, bool $closeform = true)
    {
        if (!Session::haveRight("profile", READ)) {
            return false;
        }

        echo "<div class='firstbloc'>";
        
        if (($canedit = Session::haveRight('profile', UPDATE)) && $openform) {
            $profile = new Profile();
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        $profile = new Profile();
        $profile->getFromDB($profiles_id);
        $rights = $this->getRightsGeneral();
        $profile->displayRightsChoiceMatrix($rights, [
            'default_class' => 'tab_bg_2',
            'title'         => __('General')
        ]);

        if ($canedit && $closeform) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }
        
        echo "</div>";
    }
}
