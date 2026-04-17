<?php
namespace GlpiPlugin\Shellcmd;

use DB;
use RuntimeException;

defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

final class CommandDAO {

   /**
    * Ritorna tutti i comandi dal DB
    *
    * @return Command[]
    */
   public static function getAll(): array {
      global $DB;

      $commands = [];

      $iterator = $DB->request([
         'FROM' => 'glpi_plugin_shellcmd_commands',
         'ORDER' => 'name'
      ]);

      foreach ($iterator as $row) {
         $commands[] = new Command(
            name: $row['name'],
            description: $row['description'] ?? '',
            command: $row['command'],
            allowedItemtypes: json_decode($row['allowed_itemtypes'], true, 512, JSON_THROW_ON_ERROR),
            pre: $row['pre_cmd'] ?? '',
            post: $row['post_cmd'] ?? '',
            enabled: (bool)$row['is_enabled']
         );
      }

      return $commands;
   }

   /**
    * Inserisce un comando
    */
   public static function insert(array $data): void {
      global $DB;

      $DB->insert('glpi_plugin_shellcmd_commands', [
         'name'              => $data['name'],
         'description'       => $data['description'],
         'pre_cmd'           => $data['pre_cmd'],
         'command'           => $data['command'],
         'post_cmd'          => $data['post_cmd'],
         'allowed_itemtypes' => json_encode($data['allowed_itemtypes'], JSON_THROW_ON_ERROR),
         'is_enabled'        => $data['is_enabled'] ? 1 : 0
      ]);
   }

   /**
    * Elimina comando
    */
   public static function delete(int $id): void {
      global $DB;
      $DB->delete('glpi_plugin_shellcmd_commands', ['id' => $id]);
   }
}