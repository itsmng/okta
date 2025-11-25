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

use GlpiPlugin\Okta\PluginOktaConfig;
use GlpiPlugin\Okta\PluginOktaProfile;

function plugin_okta_install() {
    set_time_limit(900);
    ini_set('memory_limit', '2048M');

    Crontask::register(PluginOktaConfig::class, 'ImportOktaUsers', DAY_TIMESTAMP, [
        [
            'comment' => 'Import users from Okta',
            'mode'    => Crontask::MODE_EXTERNAL,
        ]
    ]);

    $classesToInstall = [
        PluginOktaConfig::class,
        PluginOktaProfile::class,
    ];

    echo "<center>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>" . __("MySQL tables installation", "okta") . "<th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td align='center'>";

    // Install each class
    foreach ($classesToInstall as $class) {
        if (!call_user_func([$class, 'install'])) {
            return false;
        }
    }

    echo "</td>";
    echo "</tr>";
    echo "</table></center>";

    return true;
}

function plugin_okta_uninstall() {
    echo "<center>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>" . __("MySQL tables uninstallation", "okta") . "<th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td align='center'>";

    $classesToUninstall = [
        PluginOktaConfig::class,
        PluginOktaProfile::class,
    ];

    foreach ($classesToUninstall as $class) {
        if (!call_user_func([$class, 'uninstall'])) {
            return false;
        }
    }

    echo "</td>";
    echo "</tr>";
    echo "</table></center>";

    return true;
}
