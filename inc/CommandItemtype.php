<?php
namespace GlpiPlugin\Shellcmd;

defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

final class CommandItemtype {

   /**
    * Verifica se un comando è valido per l'itemtype
    */
   public static function isAllowed(string $itemtype, array $allowed): bool {
      return in_array($itemtype, $allowed, true);
   }
}