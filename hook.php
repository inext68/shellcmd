<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

use GlpiPlugin\Shellcmd\Rights;
use GlpiPlugin\Shellcmd\CommandDAO;

function plugin_shellcmd_install(): bool {
   global $DB;

   Rights::install();

   // Creazione tabella comandi
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

   $DB->queryOrDie($query, "shellcmd: unable to create commands table");

   return true;
}

function plugin_shellcmd_uninstall(): bool {
   global $DB;

   Rights::uninstall();

   $DB->queryOrDie(
      "DROP TABLE IF EXISTS `glpi_plugin_shellcmd_commands`",
      "shellcmd: unable to drop table"
   );

   return true;
}