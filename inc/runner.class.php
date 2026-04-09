<?php

class PluginShellcmdRunner extends CommonGLPI {


   // MVP: usa diritto "config" (admin). In seguito possiamo creare un diritto dedicato al plugin.
   public static $rightname = 'config';

   /**
    * Nome del TAB sugli oggetti core (Computer, NetworkEquipment).
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      // Mostra tab solo ad utenti autorizzati
      if (!Session::haveRight('config', UPDATE)) {
         return '';
      }

      $type = $item::getType();
      if (in_array($type, ['Computer', 'NetworkEquipment'], true)) {
         return self::createTabEntry(__('Shell CMD', 'shellcmd'));
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
      echo "<h3>" . htmlescape(__('Esegui script su IP dell’asset', 'tcinvtools')) . "</h3>";

      if (empty($ips)) {
         echo "<div class='alert alert-warning'>";
         echo htmlescape(__('Nessun IPv4 valido trovato sulle porte di rete (esclusi IPv6, 0.0.0.0 e 127.0.0.1).', 'tcinvtools'));
         echo "</div>";
         echo "</div>";
         return true;
      }

      // URL pagina esecuzione streaming (front/run.php)
      $actionUrl = Plugin::getWebDir('shelltool') . "/front/run.php";

      echo "<form method='get' action='" . htmlescape($actionUrl) . "'>";
      echo "<input type='hidden' name='action' value='run'>";
      echo "<input type='hidden' name='itemtype' value='" . htmlescape($itemtype) . "'>";
      echo "<input type='hidden' name='items_id' value='" . (int)$items_id . "'>";

      // Dropdown IP
      echo "<p style='margin:10px 0 6px 0;'><b>" . htmlescape(__('IP (IPv4) disponibili:', 'tcinvtools')) . "</b></p>";
      echo "<select name='ip' required style='min-width:260px;'>";
      foreach ($ips as $ip) {
         $ipEsc = htmlescape($ip);
         echo "<option value='{$ipEsc}'>{$ipEsc}</option>";
      }
      echo "</select>";

      // Dropdown Script
      $scripts = self::getScriptsWhitelist();
      echo "<p style='margin:12px 0 6px 0;'><b>" . htmlescape(__('Script:', 'tcinvtools')) . "</b></p>";
      echo "<select name='script' required style='min-width:260px;'>";
      foreach ($scripts as $key => $path) {
         $k = htmlescape($key);
    //     $p = htmlescape($path);
    //     echo "<option value='{$k}'>{$k} ({$p})</option>";
         echo "<option value='{$k}'>{$k}</option>";


      }
      echo "</select>";

      // Return URL: torna all'asset dopo esecuzione
      $return = $item->getFormURLWithID($items_id);
      echo "<input type='hidden' name='return' value='" . htmlescape($return) . "'>";

      echo "<div style='margin-top:12px;'>";
      echo "<button class='submit' type='submit'>" . htmlescape(__('Esegui', 'tcinvtools')) . "</button>";
      echo "</div>";

      echo "</form>";
      echo "</div>";

      return true;
   }

   /**
    * Whitelist script eseguibili.
    * IMPORTANTE: tutti gli script devono accettare l'IP come primo parametro ($1).
    * Funzione PUBLIC: front/run.php può usarla direttamente.
    */
   public static function getScriptsWhitelist(): array {
	 $BASEDIR='/var/www/html/glpi/plugins';
    return [
//         'Test'    => __DIR__.'/test.php',
         'Inventario TC'    => $BASEDIR.'/shellcmd/scripts/TCInventory/tcinventory.sh',
         'PING'   => $BASEDIR.'/shellcmd/scripts/ping-exe/launch_ping.sh',
//         'PINGPLUS' => '/usr/local/bin/PINGPLUS',
      ];
   }

   /**
    * Recupera IPv4 dalla struttura NetworkPort -> NetworkName -> IPAddress
    * usando il query builder ($DB->request) e NON query dirette (vietate in GLPI 11). [1](https://glpi-developer-documentation.readthedocs.io/en/master/upgradeguides/glpi-11.0.html)[2](https://forum.glpi-project.org/viewtopic.php?id=287465)
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

      foreach ($DB->request($criteria) as $row) { // request(): compatibile GLPI 11 [2](https://forum.glpi-project.org/viewtopic.php?id=287465)[1](https://glpi-developer-documentation.readthedocs.io/en/master/upgradeguides/glpi-11.0.html)
         $ip = $row['name'] ?? '';

         // Solo IPv4 validi (esclude automaticamente IPv6)
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
}
