<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

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

function plugin_init_shellcmd() {
   global $PLUGIN_HOOKS;

   // Required by GLPI 11
   $PLUGIN_HOOKS['csrf_compliant']['shellcmd'] = true;
}

function plugin_shellcmd_check_prerequisites() {
   return true;
}

function plugin_shellcmd_check_config() {
   return true;
}

/**
 * INSTALL — GLPI 11 API CORRETTA
 */
function plugin_shellcmd_install() {
   require_once GLPI_ROOT . '/src/Migration.php';

   $migration = new Migration('0.1.0');

   $migration->addTable(
      'glpi_plugin_shellcmd_commands',
      [
         'id' => [
            'type' => 'integer',
            'null' => false,
            'auto' => true
         ],
         'name' => [
            'type' => 'string',
            'size' => 255,
            'null' => false
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
            'type'  => 'bool',
            'null'  => false,
            'value' => 1
         ]
      ],
      [
         'primary' => ['id'],
         'engine'  => 'InnoDB',
         'charset' => 'utf8mb4',
         'collation' => 'utf8mb4_unicode_ci'
      ]
   );

   $migration->executeMigration();

   return true;
}

/**
 * UNINSTALL — GLPI 11 API CORRETTA
 */
function plugin_shellcmd_uninstall() {
   require_once GLPI_ROOT . '/src/Migration.php';

   $migration = new Migration('0.1.0');
   $migration->dropTable('glpi_plugin_shellcmd_commands');
   $migration->executeMigration();

   return true;
}