<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

/**
 * Versione del plugin
 */
function plugin_version_shellcmd() {
   return [
      'name'        => 'Shell Commands',
      'version'     => '0.1.0',
      'author'      => 'Mariano Benzi',
      'license'     => 'GPL-2.0-or-later',
      'homepage'    => 'https://github.com/inext68/shellcmd',
      'requirements'=> [
         'glpi' => [
            'min' => '11.0.0'
         ]
      ]
   ];
}

/**
 * Init plugin (NESSUNA CLASSE QUI)
 */
function plugin_init_shellcmd() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['shellcmd'] = true;

   // lingua
   loadPluginLocale('shellcmd');
}

/**
 * Check prerequisiti
 */
function plugin_shellcmd_check_prerequisites() {
   return true;
}

/**
 * Check configurazione
 */
function plugin_shellcmd_check_config() {
   return true;
}

/**
 * INSTALLAZIONE (procedural pura)
 */
function plugin_shellcmd_install() {
   global $DB;

   $query = "
   CREATE TABLE IF NOT EXISTS `glpi_plugin_shellcmd_commands` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) NOT NULL,
      `description` TEXT,
      `pre_cmd` TEXT,
      `command` TEXT NOT NULL,
      `post_cmd` TEXT,
      `allowed_itemtypes` TEXT NOT NULL,
      `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
      PRIMARY KEY (`id`)
   ) ENGINE=InnoDB
     DEFAULT CHARSET=utf8mb4
     COLLATE=utf8mb4_unicode_ci
   ";

   $DB->queryOrDie($query, 'shellcmd: table creation failed');

   return true;
}

/**
 * DISINSTALLAZIONE
 */
function plugin_shellcmd_uninstall() {
   global $DB;

   $DB->queryOrDie(
      "DROP TABLE IF EXISTS `glpi_plugin_shellcmd_commands`",
      'shellcmd: table drop failed'
   );

   return true;
}