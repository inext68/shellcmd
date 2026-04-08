<?php

define('PLUGIN_TCINVTOOLS_VERSION', '1.0.0');
define('PLUGIN_TCINVTOOLS_MIN_GLPI', '11.0.0');

function plugin_init_tcinvtools() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['tcinvtools'] = true;

   // Menu Tools (opzionale, utile per test/debug)
   $PLUGIN_HOOKS['menu_toadd']['tcinvtools'] = ['tools' => 'PluginTcinvtoolsMenu'];

   // Registra la classe che aggiunge TAB agli asset
   // addtabon: meccanismo standard per aggiungere tab su item core [3](https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tips.html)
   Plugin::registerClass(
      'PluginTcinvtoolsRunner',
      ['addtabon' => ['Computer', 'NetworkEquipment']]
   );
}

function plugin_version_tcinvtools() {
   return [
      'name'         => 'TCINV Tools (Script Runner)',
      'version'      => PLUGIN_TCINVTOOLS_VERSION,
      'author'       => 'Mariano Benzi',
      'license'      => 'GPLv2',
      'homepage'     => 'https://intranet.finstral.org/',
      'requirements' => [
         'glpi' => [
            'min' => PLUGIN_TCINVTOOLS_MIN_GLPI
         ]
      ]
   ];
}

function plugin_tcinvtools_check_prerequisites() {
   // serve proc_open per streaming realtime
   $disabled = ini_get('disable_functions') ?: '';
   if (stripos($disabled, 'proc_open') !== false) {
      echo "TCINV Tools richiede proc_open() abilitato (disable_functions lo blocca).";
      return false;
   }
   return true;
}

function plugin_tcinvtools_check_config($verbose = false) {
   return true;
}
/**
 * Installazione plugin (chiamata da GLPI quando clicchi "Installa")
 */
function plugin_tcinvtools_install() {
   // Se un domani ti servono tabelle, qui useremo Migration()
   return true;
}

/**
 * Disinstallazione plugin (chiamata da GLPI quando clicchi "Disinstalla")
 */
function plugin_tcinvtools_uninstall() {
   // Qui eventualmente si rimuovono tabelle/config create dal plugin
   return true;
}
