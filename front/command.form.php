<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

use GlpiPlugin\Shellcmd\Rights;
use GlpiPlugin\Shellcmd\CommandDAO;
use Session;
use Html;

Session::checkLoginUser();

if (!Session::haveRight(Rights::CONFIG, READ)) {
   Html::displayRightError();
   exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   CommandDAO::insert([
      'name'              => $_POST['name'],
      'description'       => $_POST['description'] ?? '',
      'pre_cmd'           => $_POST['pre'] ?? '',
      'command'           => $_POST['command'],
      'post_cmd'          => $_POST['post'] ?? '',
      'allowed_itemtypes' => $_POST['itemtypes'] ?? [],
      'is_enabled'        => true
   ]);

   Html::redirect(CFG_GLPI['root_doc'] . '/plugins/shellcmd/front/command.php');
}

Html::header(
   __('Shell Commands', 'shellcmd'),
   $_SERVER['PHP_SELF'],
   'config',
   'pluginshellcmd'
);

Html::displayTwigTemplate(
   'shellcmd',
   'command_form.twig',
   [
      'itemtypes' => ['Computer','NetworkEquipment','Peripheral']
   ]
);

Html::footer();
