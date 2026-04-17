<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

function plugin_version_shellcmd(): array {
   return [
      'name'           => 'Shell Commands',
      'version'        => '0.1.0',
      'author'         => 'Mariano Benzi',
      'license'        => 'GPL-2.0-or-later',
      'requirements'   => [
         'glpi' => [
            'min' => '11.0.0'
         ]
      ]
   ];
}

function plugin_init_shellcmd(): void {
   global $PLUGIN_HOOKS;
   $PLUGIN_HOOKS['csrf_compliant']['shellcmd'] = true;
   loadPluginLocale('shellcmd');
}

function plugin_shellcmd_install(): bool {
   global $DB;

   $query = <<<SQL
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
  COLLATE=utf8mb4_unicode_ci;
SQL;

   $DB->queryOrDie($query, 'shellcmd install failed');

   return true;
}

function plugin_shellcmd_uninstall(): bool {
   global $DB;

   $DB->queryOrDie(
      "DROP TABLE IF EXISTS `glpi_plugin_shellcmd_commands`",
      'shellcmd uninstall failed'
   );

   return true;
}