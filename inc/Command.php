<?php
namespace GlpiPlugin\Shellcmd;

defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

final class Command {

   private string $name;
   private string $description;
   private string $pre;
   private string $command;
   private string $post;
   private array  $allowedItemtypes;
   private bool   $enabled;

   public function __construct(
      string $name,
      string $description,
      string $command,
      array  $allowedItemtypes,
      string $pre  = '',
      string $post = '',
      bool   $enabled = true
   ) {
      $this->name             = $name;
      $this->description      = $description;
      $this->pre              = trim($pre);
      $this->command          = trim($command);
      $this->post             = trim($post);
      $this->allowedItemtypes = $allowedItemtypes;
      $this->enabled          = $enabled;
   }

   /**
    * Nome visualizzato
    */
   public function getName(): string {
      return $this->name;
   }

   /**
    * Descrizione
    */
   public function getDescription(): string {
      return $this->description;
   }

   /**
    * Verifica se il comando è abilitato
    */
   public function isEnabled(): bool {
      return $this->enabled;
   }

   /**
    * Verifica se è eseguibile sull'oggetto
    */
   public function isAllowedForItemtype(string $itemtype): bool {
      return CommandItemtype::isAllowed($itemtype, $this->allowedItemtypes);
   }

   /**
    * Ritorna la command line finale (stringa)
    */
   public function build(array $context): string {
      if (!$this->enabled) {
         throw new \RuntimeException('Command disabled');
      }

      $variables = Variable::resolve($context);

      $parts = [];

      if ($this->pre !== '') {
         $parts[] = Variable::substitute($this->pre, $variables);
      }

      $parts[] = Variable::substitute($this->command, $variables);

      if ($this->post !== '') {
         $parts[] = Variable::substitute($this->post, $variables);
      }

      // Uniamo con spazio, trim finale
      return trim(implode(' ', $parts));
   }

   /**
    * Comandi definiti (per ora hardcoded, DB-ready)
    */
   public static function all(): array {
      return [
         new self(
            name: __('Inventario TC', 'shellcmd'),
            description: __('Esegue inventario ThinClient', 'shellcmd'),
            command: '/usr/local/bin/tcinventory.sh',
            allowedItemtypes: ['Computer'],
            pre: '/usr/bin/env',
            post: '--ip {IP} --name {NAME}'
         ),
      ];
   }

  /**
    * Itemtype consentiti
    */
   public function getAllowedItemtypes(): array {
      return $this->allowedItemtypes;
   }
}