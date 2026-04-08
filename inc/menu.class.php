<?php

class PluginTcinvtoolsMenu extends CommonGLPI {

   public static $rightname = 'config'; // MVP: solo admin

   static function getMenuName() {
      return __('TCINV Tools', 'tcinvtools');
   }

   static function getMenuContent() {
      $url = Plugin::getWebDir('tcinvtools') . '/front/run.php';
      return [
         'title' => self::getMenuName(),
         'page'  => $url,
         'icon'  => 'fas fa-terminal',
      ];
   }
}