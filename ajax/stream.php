<?php
defined('GLPI_ROOT') or die('Sorry. You can\'t access this file directly.');

use GlpiPlugin\Shellcmd\Command;
use GlpiPlugin\Shellcmd\Rights;
use GlpiPlugin\Shellcmd\Variable;
use Session;
use CommonGLPI;

// --------------------
// Sicurezza & diritti
// --------------------
Session::checkLoginUser();

if (!Session::haveRight(Rights::READ, READ)
    && !Session::haveRight(Rights::CONFIG, READ)) {
   http_response_code(403);
   exit;
}

// CSRF (obbligatorio in GLPI 11)
if (!Session::validateCSRF($_POST['_glpi_csrf_token'] ?? '')) {
   http_response_code(400);
   exit;
}

// --------------------
// Parametri input
// --------------------
$itemtype   = $_POST['itemtype']  ?? '';
$items_id   = (int)($_POST['items_id'] ?? 0);
$commandName = $_POST['command'] ?? '';

if ($itemtype === '' || $items_id <= 0 || $commandName === '') {
   http_response_code(400);
   exit;
}

// --------------------
// Recupero oggetto GLPI
// --------------------
if (!class_exists($itemtype)) {
   http_response_code(400);
   exit;
}

/** @var CommonGLPI $item */
$item = new $itemtype();
if (!$item->getFromDB($items_id)) {
   http_response_code(404);
   exit;
}

// --------------------
// Recupero comando
// --------------------
$command = null;
foreach (Command::all() as $cmd) {
   if ($cmd->getName() === $commandName) {
      $command = $cmd;
      break;
   }
}

if ($command === null || !$command->isEnabled()) {
   http_response_code(404);
   exit;
}

if (!$command->isAllowedForItemtype($itemtype)) {
   http_response_code(403);
   exit;
}

// --------------------
// Contesto variabili
// --------------------
$context = [
   'ip'        => method_exists($item, 'getMainIP') ? (string)$item->getMainIP() : '',
   'name'      => (string)$item->fields['name'],
   'mac'       => method_exists($item, 'getMainMAC') ? (string)$item->getMainMAC() : '',
   'itemtype'  => $itemtype,
   'items_id'  => $items_id,
];

// --------------------
// Preparazione STREAM
// --------------------
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // nginx
header('Connection: keep-alive');

// --------------------
// Costruzione comando
// --------------------
try {
   $cmdline = $command->build($context);
} catch (Throwable $e) {
   echo "ERROR: " . $e->getMessage() . "\n";
   flush();
   exit;
}

// --------------------
// Esecuzione processo
// --------------------
$descriptors = [
   0 => ['pipe', 'r'],
   1 => ['pipe', 'w'],
   2 => ['pipe', 'w'],
];

$process = proc_open($cmdline, $descriptors, $pipes);

if (!is_resource($process)) {
   echo "ERROR: Unable to start command\n";
   flush();
   exit;
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

// --------------------
// LOOP di STREAM
// --------------------
while (true) {
   $read = [$pipes[1], $pipes[2]];
   $write = null;
   $except = null;

   if (stream_select($read, $write, $except, 0, 200000) === false) {
      break;
   }

   foreach ($read as $r) {
      $data = fread($r, 8192);
      if ($data !== false && $data !== '') {
         echo $data;
         flush();
      }
   }

   $status = proc_get_status($process);
   if (!$status['running']) {
      break;
   }
}

fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);

echo "\n=== " . __('Command finished.', 'shellcmd') . " ===\n";
flush();
