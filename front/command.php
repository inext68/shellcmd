<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

use GlpiPlugin\Shellcmd\Command;
use GlpiPlugin\Shellcmd\Rights;
use Session;
use Html;

Session::checkLoginUser();

if (!Session::haveRight(Rights::CONFIG, READ)) {
   Html::displayRightError();
   exit;
}

Html::header(
   __('Shell Commands', 'shellcmd'),
   $_SERVER['PHP_SELF'],
   'config',
   'pluginshellcmd'
);

$commands = Command::all();

Html::displayTwigTemplate(
   'shellcmd',
   'command_list.twig',
   [
      'commands' => $commands
   ]
);

Html::footer();