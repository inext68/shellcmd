<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

use GlpiPlugin\Shellcmd\AssetTab;

function plugin_init_shellcmd(): void {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['shellcmd'] = true;

   // TAB sugli asset
   Plugin::registerClass(
      AssetTab::class,
      [
         'addtabon' => [
            'Computer',
            'NetworkEquipment',
            'Peripheral'
         ]
      ]
   );

   // Caricamento lingua
   loadPluginLocale('shellcmd');
}

function plugin_version_shellcmd(): array {
   return [
      'name'           => 'Shell Commands',
      'version'        => '0.1.0',
      'author'         => 'Mariano Benzi',
      'license'        => 'GPL-2.0-or-later',
      'homepage'       => 'https://github.com/inext68/shellcmd',
      'requirements'   => [
         'glpi' => [
            'min' => '11.0.0',
            'max' => '11.99.99'
         ]
      ]
   ];
}

function plugin_shellcmd_check_prerequisites(): bool {
   return true;
}

function plugin_shellcmd_check_config(): bool {
   return true;
}
