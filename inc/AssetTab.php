<?php
namespace GlpiPlugin\Shellcmd;

use CommonGLPI;
use Session;
use Html;

defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

class AssetTab extends CommonGLPI {

   /**
    * Nome del TAB
    */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string {
      // Utenti senza diritti NON vedono il tab
      if (!Session::haveRight(Rights::READ, READ)
          && !Session::haveRight(Rights::CONFIG, READ)) {
         return '';
      }

      return __('Shell Commands', 'shellcmd');
   }

   /**
    * Contenuto del TAB
    */
   public static function displayTabContentForItem(
      CommonGLPI $item,
      $tabnum = 1,
      $withtemplate = 0
   ): bool {

      // Controllo diritti
      if (!Session::haveRight(Rights::READ, READ)
          && !Session::haveRight(Rights::CONFIG, READ)) {
         return true;
      }

      $itemtype = $item::getType();
      $items_id = (int)$item->getID();

      // Recupero comandi validi
      $commands = array_filter(
         Command::all(),
         fn(Command $cmd) =>
            $cmd->isEnabled() &&
            $cmd->isAllowedForItemtype($itemtype)
      );

      // Rendering Twig
      Html::requireJs('shellcmd'); // verrà usato nello STEP 4

      Html::displayTwigTemplate(
         'shellcmd',
         'asset_tab.twig',
         [
            'itemtype' => $itemtype,
            'items_id' => $items_id,
            'commands' => $commands,
            'can_execute' => true
         ]
      );

      return true;
   }
}