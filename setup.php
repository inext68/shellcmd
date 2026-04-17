<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

/**
 * Versione plugin
 */
function plugin_version_shellcmd() {
   return [
      'name'         => 'Shell Commands',
      'version'      => '0.1.0',
      'author'       => 'Mariano Benzi',
      'license'      => 'GPL-2.0-or-later',
      'homepage'     => 'https://github.com/inext68/shellcmd',
      'requirements' => [
         'glpi' => [
            'min' => '11.0.0'
         ]
      ]
   ];
}

/**
 * Init plugin
 */
function plugin_init_shellcmd() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['shellcmd'] = true;
}

/**
 * Prerequisiti
 */
function plugin_shellcmd_check_prerequisites() {
   return true;
}

/**
 * Config check
 */
function plugin_shellcmd_check_config() {
   return true;
}

/**
 * INSTALLAZIONE (GLPI 11 CORRETTA)
 */
function plugin_shellcmd_install() {
   require_once GLPI_ROOT . '/src/Migration.php';

   $migration = new Migration('0.1.0');

   // Tabella comandi
   if (!$migration->tableExists('glpi_plugin_shellcmd_commands')) {
      $migration->addTable('glpi_plugin_shellcmd_commands', [
         'id' => [
            'type'    => 'integer',
            'value'   => null,
            'null'    => false,
            'auto'    => true
         ],
         'name' => [
            'type'  => 'string',
            'size'  => 255,
            'null'  => false
         ],
         'description' => [
            'type' => 'text'
         ],
         'pre_cmd' => [
            'type' => 'text'
         ],
         'command' => [
            'type' => 'text',
            'null' => false
         ],
         'post_cmd' => [
            'type' => 'text'
         ],
         'allowed_itemtypes' => [
            'type' => 'text',
            'null' => false
         ],
         'is_enabled' => [
            'type'    => 'bool',
            'value'   => 1,
            'null'    => false
         ]
      ]);
   }

   $migration->executeMigration();

   return true;
}

/**
 * DISINSTALLAZIONE
 */
function plugin_shellcmd_uninstall() {
   require_once GLPI_ROOT . '/src/Migration.php';

   $migration = new Migration('0.1.0');

   if ($migration->tableExists('glpi_plugin_shellcmd_commands')) {
      $migration->dropTable('glpi_plugin_shellcmd_commands');
   }

   $migration->executeMigration();

   return true;
}