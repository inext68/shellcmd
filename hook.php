<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

use GlpiPlugin\Shellcmd\Rights;

/**
 * Installazione plugin
 */
function plugin_shellcmd_install(): bool {
   Rights::install();
   return true;
}

/**
 * Disinstallazione plugin
 */
function plugin_shellcmd_uninstall(): bool {
   Rights::uninstall();
   return true;
}