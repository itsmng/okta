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

define('PLUGIN_OKTA_VERSION', '1.4.4');

if (!defined("PLUGIN_OKTA_DIR")) {
   define("PLUGIN_OKTA_DIR", Plugin::getPhpDir("okta"));
}
if (!defined("PLUGIN_OKTA_WEB_DIR")) {
   define("PLUGIN_OKTA_WEB_DIR", Plugin::getWebDir("okta"));
}

function plugin_init_okta() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['okta'] = true;
    $PLUGIN_HOOKS['change_profile']['okta'] = array('PluginOktaProfile', 'changeProfile');

    Plugin::registerClass('PluginOktaProfile', ['addtabon' => array('Profile')]);

    if (Session::haveRight("profile", UPDATE)) {
        $PLUGIN_HOOKS['config_page']['okta'] = 'front/config.form.php';
    }
}

function plugin_version_okta() {
    return array(
        'name'           => "Okta",
        'version'        => PLUGIN_OKTA_VERSION,
        'author'         => 'ITSM Dev Team',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://github.com/itsmng/okta',
        'minGlpiVersion' => '9.5'
    );
}

function plugin_okta_check_prerequisites() {
    if (version_compare(ITSM_VERSION, '1.5', 'lt')) {
        echo "This plugin requires ITSM >= 1.5";
        return false;
    }
    return true;
}

function plugin_okta_check_config() {
    return true;
}
