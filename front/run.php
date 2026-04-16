<?php
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();
if (!Session::haveRight('config', UPDATE)) {
   Html::displayRightError();
   exit;
}

/*
 * IMPORTANTISSIMO:
 * Apriamo un buffer nostro, ma NON lo chiudiamo mai.
 * GLPI (LegacyFileLoadController) se ne occuperà.
 */
ob_start();

require_once __DIR__ . '/../inc/runner.class.php';

$action = $_GET['action'] ?? '';
if ($action !== 'run') {
   Html::header(__('Shell CMD', 'shellcmd'), $_SERVER['PHP_SELF'], "tools");
   echo '<div class="center"><h2>Shell CMD</h2></div>';
   Html::footer();
   exit;
}

$ip        = $_GET['ip'] ?? '';
$scriptKey = $_GET['script'] ?? '';
$returnUrl = $_GET['return'] ?? (CFG_GLPI['root_doc'] . '/front/central.php');

if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
   http_response_code(400);
   echo "IP non valido";
   exit;
}

$WHITELIST = PluginShellcmdRunner::getScriptsWhitelist();
if (!isset($WHITELIST[$scriptKey])) {
   http_response_code(403);
   echo "Script non autorizzato";
   exit;
}

$commandPath = $WHITELIST[$scriptKey];

/* Header risposta */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlescape($scriptKey); ?> – realtime</title>
<style>
body { background:#111; color:#0f0; font-family:monospace; margin:0; }
#bar { padding:8px 12px; background:#000; border-bottom:1px solid #333 }
#term { white-space:pre-wrap; padding:12px; }
.err { color:#f55 }
</style>
</head>
<body>

<div id="bar">
  <?php echo htmlescape($scriptKey); ?> su <?php echo htmlescape($ip); ?>
</div>

<div id="term">
<?php
flush();

/* Comando DEFINITIVO: usa bash esplicito */
$cmd = ['/usr/bin/env', 'bash', $commandPath, $ip];

$proc = proc_open(
   $cmd,
   [
      1 => ['pipe', 'w'], // STDOUT
      2 => ['pipe', 'w'], // STDERR
   ],
   $pipes,
   null,
   [
      'LANG'   => 'C',
      'LC_ALL' => 'C',
   ]
);

if (!is_resource($proc)) {
   echo "<span class='err'>Errore: impossibile eseguire il comando</span>\n";
   flush();
   exit;
}

stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

while (true) {
   $read = [$pipes[1], $pipes[2]];
   $write = null;
   $except = null;

   if (stream_select($read, $write, $except, 0, 200000) === false) {
      break;
   }

   foreach ($read as $r) {
      $out = stream_get_contents($r);
      if ($out !== '') {
         if ($r === $pipes[2]) {
            echo '<span class="err">' . htmlescape($out) . '</span>';
         } else {
            echo htmlescape($out);
         }
         flush();
      }
   }

   $status = proc_get_status($proc);
   if (!$status['running']) {
      break;
   }
}

fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);

echo "\n=== OPERAZIONE COMPLETATA ===\n";
flush();
?>
</div>

<script>
document.addEventListener('click', () => {
  window.location.href = <?php echo json_encode($returnUrl); ?>;
});
</script>

</body>
</html>