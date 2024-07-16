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
include("../../../inc/includes.php");
require_once(PLUGIN_OKTA_DIR . "/inc/config.class.php");

$plugin = new Plugin();

if($plugin->isActivated("okta")) {
    $config = new PluginOktaConfig();
    if(isset($_POST["update"]) || isset($_POST["import"])) {
        Session::checkRight("plugin_okta_config", UPDATE);
        $config::updateConfigValues($_POST);
        if (isset($_POST["import"])) {
            $config::importUsers();
            Session::addMessageAfterRedirect(__('Users imported successfully'), 'okta');
        } else {
            Session::addMessageAfterRedirect(__('Settings updated successfully'), 'okta');
        };
    }

    Html::header("Okta", $_SERVER["PHP_SELF"], "config", Plugin::class);
    $config->showConfigForm();
} else {
    Html::header("settings", '', "config", "plugins");
    echo "<div class='center'><br><br><img src=\"".$CFG_GLPI["root_doc"]."/pics/warning.png\" alt='warning'><br><br>";
    echo "<b>Please enable the plugin before configuring it</b></div>";
    Html::footer();
}

Html::footer();
