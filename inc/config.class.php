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

    /**
     * Displays the configuration page for the plugin
     *
     * @return void
     */
    public function showConfigForm() {
        if (!Session::haveRight("plugin_okta_config",UPDATE)) {
            return false;
        }

       echo <<<HTML
        <form>
            coucou
        </form>
       HTML; 
    }
}
