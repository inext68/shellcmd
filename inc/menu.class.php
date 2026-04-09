<?php

class PluginShellcmdMenu extends CommonGLPI {

   public static $rightname = 'config'; // MVP: solo admin

   static function getMenuName() {
      return __('Shell CMD', 'shellcmd');
   }

   static function getMenuContent() {
      $url = Plugin::getWebDir('shellcmd') . '/front/run.php';
      return [
         'title' => self::getMenuName(),
         'page'  => $url,
         'icon'  => 'fas fa-terminal',
      ];
   }
}