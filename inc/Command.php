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
      string $pre = '',
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

   public function getName(): string { return $this->name; }
   public function getDescription(): string { return $this->description; }
   public function isEnabled(): bool { return $this->enabled; }
   public function getAllowedItemtypes(): array { return $this->allowedItemtypes; }

   public function isAllowedForItemtype(string $itemtype): bool {
      return CommandItemtype::isAllowed($itemtype, $this->allowedItemtypes);
   }

   public function build(array $context): string {
      if (!$this->enabled) {
         throw new \RuntimeException('Command disabled');
      }

      $vars = Variable::resolve($context);
      $parts = [];

      if ($this->pre !== '') {
         $parts[] = Variable::substitute($this->pre, $vars);
      }

      $parts[] = Variable::substitute($this->command, $vars);

      if ($this->post !== '') {
         $parts[] = Variable::substitute($this->post, $vars);
      }

      return trim(implode(' ', $parts));
   }

   /**
    * Recupero comandi (DB-first)
    *
    * @return Command[]
    */
   public static function all(): array {
      try {
         return CommandDAO::getAll();
      } catch (\Throwable) {
         return [];
      }
   }
}