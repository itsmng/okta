<?php


class PluginOktaProfile extends CommonDBTM {

    static function install() {
        global $DB;

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE `$table` (
                `id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_profiles (id)',
                `right` char(1) collate utf8_unicode_ci default NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

            $DB->queryOrDie($query, $DB->error());

            self::createAdminAccess($_SESSION['glpiactiveprofile']['id']);

            foreach (self::getRightsGeneral() as $right) {
                self::addDefaultProfileInfos($_SESSION['glpiactiveprofile']['id'],[$right['field'] => $right['default']]);
            }
        }

      return true;
   }

   static function uninstall() {
      global $DB;

      if($DB->tableExists('glpi_plugin_okta_profiles')) {
          $DB->queryOrDie("DROP TABLE `glpi_plugin_okta_profiles`",$DB->error());
      }

      // Clear profiles
      foreach (self::getRightsGeneral() as $right) {
          $query = "DELETE FROM `glpi_profilerights` WHERE `name` = '".$right['field']."'";
          $DB->query($query);

          if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
              unset($_SESSION['glpiactiveprofile'][$right['field']]);
          }
      }
      return true;
   }

   static function canCreate() {
       if (isset($_SESSION['profile'])) {
           return ($_SESSION['profile']['okta'] == 'w');
      }
      return false;
   }

   static function canView() {
       if (isset($_SESSION['profile'])) {
           return ($_SESSION['profile']['okta'] == 'w'
               || $_SESSION['profile']['okta'] == 'r');
      }
      return false;
   }

   static function createAdminAccess($ID) {
       $myProf = new self();
       // Only create profile if it's new
       if (!$myProf->getFromDB($ID)) {
           // Add entry to permissions database giving the user write privileges
           $myProf->add(array('id'    => $ID,
               'right' => 'w'));
      }
   }

   static function addDefaultProfileInfos($profiles_id, $rights) {
       $profileRight = new ProfileRight();
       foreach ($rights as $right => $value) {
           if (!countElementsInTable('glpi_profilerights',
               ['profiles_id' => $profiles_id, 'name' => $right])) {
               $myright['profiles_id'] = $profiles_id;
               $myright['name']        = $right;
               $myright['rights']      = $value;
               $profileRight->add($myright);
               //Add right to the current session
               $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   static function changeProfile() {
       $prof = new self();
       if ($prof->getFromDB($_SESSION['glpiactiveprofile']['id'])) {
           $_SESSION["glpi_plugin_okta_profile"] = $prof->fields;
      } else {
          unset($_SESSION["glpi_plugin_okta_profile"]);
      }
   }

   static function getRightsGeneral() {
       $rights = [
           ['itemtype'  => 'PluginOktaProfile',
           'label'     => __('Use okta', 'okta'),
           'field'     => 'plugin_okta_config',
           'rights'    =>  [UPDATE    => __('Allow editing', 'whitelabel')],
           'default'   => 23]];
       return $rights;
   }

   function showForm($profiles_id = 0, $openform = true, $closeform = true) {
       if (!Session::haveRight("profile",READ)) {
           return false;
      }

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRight('profile', UPDATE))
          && $openform) {
          $profile = new Profile();

          echo "<form method='post' action='".$profile->getFormURL()."'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);
      $rights = $this->getRightsGeneral();
      $profile->displayRightsChoiceMatrix($rights, ['default_class' => 'tab_bg_2',
          'title'         => __('General')]);

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
