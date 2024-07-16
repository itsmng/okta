<?php

include ('../../../inc/includes.php');

Session::haveRight('plugin_okta_profile', UPDATE);

$prof = new PluginOktaProfile();

if (isset($_POST['update'])) {
    $prof->update($_POST);
    Html::back();
}
