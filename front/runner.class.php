<?php

class PluginShellcmdRunner extends CommonGLPI {

   // MVP: usa diritto config; in seguito possiamo creare un diritto dedicato plugin.
   public static $rightname = 'config';

   /**
    * Nome del TAB sugli oggetti core (Computer, NetworkEquipment).
    * Meccanismo standard: getTabNameForItem / displayTabContentForItem. [6](https://intranet.gies-informatique.fr/glpi/apirest.php)
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      // Mostra tab solo agli utenti autorizzati
      if (!Session::haveRight('config', UPDATE)) {
         return '';
      }

      $type = $item::getType();
      if (in_array($type, ['Computer', 'NetworkEquipment'], true)) {
         return self::createTabEntry(__('SHELL cmd', 'shellcmd'));
      }

      return '';
   }

   /**
    * Contenuto del TAB: dropdown IP (IPv4) + dropdown script + pulsante Esegui.
    */
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if (!Session::haveRight('config', UPDATE)) {
         Html::displayRightError();
         return true;
      }

      $itemtype = $item::getType();
      $items_id = (int)$item->getID();

      // Recupera IPv4 dall'asset (porte di rete), escludendo IPv6, 0.0.0.0 e 127.0.0.1
      $ips = self::getIPv4FromNetworkPorts($itemtype, $items_id);

      echo "<div class='spaced'>";
      echo "<h3>" . Html::clean(__('Esegui script su IP dell’asset', 'tcinvtools')) . "</h3>";

      if (empty($ips)) {
         echo "<div class='alert alert-warning'>";
         echo Html::clean(__('Nessun IPv4 valido trovato sulle porte di rete (esclusi IPv6, 0.0.0.0 e 127.0.0.1).', 'tcinvtools'));
         echo "</div>";
         echo "</div>";
         return true;
      }

      // URL pagina esecuzione streaming (front/run.php)
      $actionUrl = Plugin::getWebDir('shellcmd') . "/front/run.php";

      echo "<form method='get' action='" . Html::clean($actionUrl) . "'>";
      echo "<input type='hidden' name='action' value='run'>";
      echo "<input type='hidden' name='itemtype' value='" . Html::clean($itemtype) . "'>";
      echo "<input type='hidden' name='items_id' value='" . (int)$items_id . "'>";

      // Dropdown IP
      echo "<p style='margin:10px 0 6px 0;'><b>" . Html::clean(__('IP (IPv4) disponibili:', 'tcinvtools')) . "</b></p>";
      echo "<select name='ip' required style='min-width:260px;'>";
      foreach ($ips as $ip) {
         $ipEsc = Html::clean($ip);
         echo "<option value='{$ipEsc}'>{$ipEsc}</option>";
      }
      echo "</select>";

      // Dropdown Script
      $scripts = self::getScriptsWhitelist();
      echo "<p style='margin:12px 0 6px 0;'><b>" . Html::clean(__('Script:', 'tcinvtools')) . "</b></p>";
      echo "<select name='script' required style='min-width:260px;'>";
      foreach ($scripts as $key => $path) {
         $k = Html::clean($key);
         $p = Html::clean($path);
         echo "<option value='{$k}'>{$k} ({$p})</option>";
      }
      echo "</select>";

      // Return URL: torna all'asset dopo esecuzione
      $return = $item->getFormURLWithID($items_id);
      echo "<input type='hidden' name='return' value='" . Html::clean($return) . "'>";

      echo "<div style='margin-top:12px;'>";
      echo "<button class='submit' type='submit'>" . Html::clean(__('Esegui', 'tcinvtools')) . "</button>";
      echo "</div>";

      echo "</form>";
      echo "</div>";

      return true;
   }

   /**
    * Whitelist script eseguibili.
    * IMPORTANTE: tutti gli script devono accettare l'IP come primo parametro ($1).
    * Questa funzione è PUBLIC così front/run.php può leggerla direttamente.
    */
/*   public static function getScriptsWhitelist(): array {
      return [
         'Test'    => '/var/www/html/glpi/plugins/shellcmd/scripts/TCInventory/test.sh',
         'TCINV'    => '/var/www/html/glpi/plugins/shellcmd/scripts/TCInventory/tcinventory.sh',
         'PING'  => '/usr/bin/ping -c 10',
         'PINGPLUS' => '/usr/local/bin/PINGPLUS',
      ];
   }
*/
   /**
    * Recupera IPv4 dalla struttura NetworkPort -> NetworkName -> IPAddress.
    * Evita SQL "raw" e usa $DB->request() (query builder GLPI) per compatibilità GLPI 11. [2](https://forum.glpi-project.org/viewtopic.php?id=287465)[3](https://forum.glpi-project.org/viewtopic.php?id=291722)
    * Join coerente con il modello rete GLPI. [4](https://www.linkedin.com/posts/glpi-developed-by-teclib_glpi-11-the-next-level-ep23-will-there-activity-7356232086493487104-_HGt)[5](https://www.youtube.com/watch?v=gS_nMOMiuqc)
    */
   private static function getIPv4FromNetworkPorts(string $itemtype, int $items_id): array {
      global $DB;

      $ips = [];

      $criteria = [
         'SELECT'   => ['glpi_ipaddresses' => ['name']],
         'DISTINCT' => true,
         'FROM'     => 'glpi_networkports',
         'INNER JOIN' => [
            'glpi_networknames' => [
               'ON' => [
                  'glpi_networkports' => 'id',
                  'glpi_networknames' => 'items_id',
               ],
            ],
            'glpi_ipaddresses' => [
               'ON' => [
                  'glpi_networknames' => 'id',
                  'glpi_ipaddresses'  => 'items_id',
               ],
            ],
         ],
         'WHERE' => [
            'glpi_networkports.itemtype' => $itemtype,
            'glpi_networkports.items_id' => $items_id,
            // esclusioni richieste
            'glpi_ipaddresses.name'      => ['NOT IN', ['0.0.0.0', '127.0.0.1']],
         ],
      ];

      // Iterazione risultati con request() [2](https://forum.glpi-project.org/viewtopic.php?id=287465)[3](https://forum.glpi-project.org/viewtopic.php?id=291722)
      foreach ($DB->request($criteria) as $row) {
         $ip = $row['name'] ?? '';
		 
         if ($ip !== '0.0.0.0'
			&& $ip !== '127.0.0.1'
			&& filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
		) {
			$ips[] = $ip;
		}
   }

   $ips = array_values(array_unique($ips));
   sort($ips);
   return $ips;
}
