<?php

define('PLUGIN_SHELLCMD_VERSION', '1.0.0');
define('PLUGIN_SHELLCMD_MIN_GLPI', '11.0.0');

function plugin_init_shellcmd() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['shellcmd'] = true;

   // Menu Tools (opzionale, utile per test/debug)
   $PLUGIN_HOOKS['menu_toadd']['shellcmd'] = ['tools' => 'PluginShellcmdMenu'];

   // Registra la classe che aggiunge TAB agli asset
   // addtabon: meccanismo standard per aggiungere tab su item core [3](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tips.html)
   Plugin::registerClass(
      'PluginShellcmdRunner',
      ['addtabon' => ['Computer', 'NetworkEquipment']]
   );
}

function plugin_version_shellcmd() {
   return [
      'name'         => 'Shell CMD (Script Runner)',
      'version'      => PLUGIN_SHELLCMD_VERSION,
      'author'       => 'Mariano Benzi',
      'license'      => 'GPLv2',
      'homepage'     => 'https://www.benzimariano.altervista.org',
      'requirements' => [
         'glpi' => [
            'min' => PLUGIN_SHELLCMD_MIN_GLPI
         ]
      ]
   ];
}

function plugin_shellcmd_check_prerequisites() {
   // serve proc_open per streaming realtime
   $disabled = ini_get('disable_functions') ?: '';
   if (stripos($disabled, 'proc_open') !== false) {
      echo "SHELL_cmd richiede proc_open() abilitato (disable_functions lo blocca).";
      return false;
   }
   return true;
}

function plugin_shellcmd_check_config($verbose = false) {
   return true;
}
/**
 * Installazione plugin (chiamata da GLPI quando clicchi "Installa")
 */
function plugin_shellcmd_install() {
   // Se un domani ti servono tabelle, qui useremo Migration()
   return true;
}

/**
 * Disinstallazione plugin (chiamata da GLPI quando clicchi "Disinstalla")
 */
function plugin_shellcmd_uninstall() {
   // Qui eventualmente si rimuovono tabelle/config create dal plugin
   return true;
}
