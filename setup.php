<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

/**
 * Plugin version
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
 * ⚠️ NESSUNA classe del plugin qui
 */
function plugin_init_shellcmd() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['shellcmd'] = true;
}

/**
 * Prerequisites
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
 * INSTALL
 */
function plugin_shellcmd_install() {
   global $DB;

   $sql = "
   CREATE TABLE `glpi_plugin_shellcmd_commands` (
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

   if (!$DB->tableExists('glpi_plugin_shellcmd_commands')) {
      $DB->queryOrDie($sql, 'shellcmd: install failed');
   }

   return true;
}

/**
 * UNINSTALL
 */
function plugin_shellcmd_uninstall() {
   global $DB;

   if ($DB->tableExists('glpi_plugin_shellcmd_commands')) {
      $DB->queryOrDie(
         'DROP TABLE `glpi_plugin_shellcmd_commands`',
         'shellcmd: uninstall failed'
      );
   }

   return true;
}