<?php
namespace GlpiPlugin\Shellcmd;

use ProfileRight;

defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

class Rights {

   public const READ   = 'plugin_shellcmd_read';
   public const CONFIG = 'plugin_shellcmd_config';

   public static function install(): void {
      ProfileRight::addProfileRights([
         self::READ,
         self::CONFIG
      ]);
   }

   public static function uninstall(): void {
      ProfileRight::deleteProfileRights([
         self::READ,
         self::CONFIG
      ]);
   }