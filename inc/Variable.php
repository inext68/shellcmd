<?php
namespace GlpiPlugin\Shellcmd;

use CommonGLPI;

defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

final class Variable {

   /**
    * Elenco variabili supportate
    *
    * @return array<string,string>
    */
   public static function definitions(): array {
      return [
         '{IP}'        => 'Primary IP address',
         '{NAME}'      => 'Object name',
         '{MAC}'       => 'Primary MAC address',
         '{ITEMTYPE}'  => 'GLPI item type',
         '{ITEM_ID}'   => 'GLPI item ID',
      ];
   }

   /**
    * Risolve le variabili per un item GLPI
    */
   public static function resolve(array $context): array {
      return [
         '{IP}'        => (string)($context['ip'] ?? ''),
         '{NAME}'      => (string)($context['name'] ?? ''),
         '{MAC}'       => (string)($context['mac'] ?? ''),
         '{ITEMTYPE}'  => (string)($context['itemtype'] ?? ''),
         '{ITEM_ID}'   => (string)($context['items_id'] ?? ''),
      ];
   }

   /**
    * Sostituzione sicura variabili in una stringa
    */
   public static function substitute(string $input, array $values): string {
      return strtr($input, $values);
   }
}