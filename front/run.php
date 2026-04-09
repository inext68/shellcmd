<?php
include_once __DIR__ . '/../../../inc/includes.php';


Session::checkLoginUser();

if (!Session::haveRight('config', UPDATE)) {
   Html::displayRightError();
   exit;
}

require_once __DIR__ . '/../inc/runner.class.php';

$glpiRoot   = realpath(dirname(__DIR__, 3));
$glpiPublic = realpath($glpiRoot . '/public');


$action = $_GET['action'] ?? '';

// Pagina “di cortesia” se aperta dal menu Tools
if ($action !== 'run') {
   Html::header(__('Shell CMD', 'shellcmd'), $_SERVER['PHP_SELF'], "tools");

   echo "<div class='center'>";
   echo "<h2>" . htmlescape(__('Shell CMD', 'shellcmd')) . "</h2>";
   echo "<p>" . htmlescape(__('Questo plugin è pensato per l’uso dal TAB degli asset.', 'shellcmd')) . "</p>";
   echo "</div>";

   Html::footer();
   exit;
}

// ---------------- RUN ----------------

$ip        = $_GET['ip'] ?? '';
$scriptKey = $_GET['script'] ?? '';
$returnUrl = $_GET['return'] ?? (CFG_GLPI['root_doc'] . '/front/central.php');

// Validazione IP: solo IPv4, escludi 0.0.0.0 e 127.0.0.1
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $ip === '0.0.0.0' || $ip === '127.0.0.1') {
   header('Content-Type: text/plain; charset=utf-8');
   http_response_code(400);
   die("IP non valido.\n");
}

// Whitelist script dal runner (no Reflection)
$WHITELIST = PluginTcinvtoolsRunner::getScriptsWhitelist();

if (!isset($WHITELIST[$scriptKey])) {
   header('Content-Type: text/plain; charset=utf-8');
   http_response_code(400);
   die("Script non autorizzato.\n");
}

$commandPath = $WHITELIST[$scriptKey];

// Disattiva buffering per favorire streaming realtime
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_flush(); }
ob_implicit_flush(true);

// Header per streaming
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, no-transform');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // aiuta su nginx

// Cartelle di servizio per evitare /var/www/.gnupg (gpg + sshpass)
//$SERVICE_HOME  = '';
//$SERVICE_GNUPG = $SERVICE_HOME . '.gnupg';


$SERVICE_GNUPG =  '.gnupg';


//@mkdir($SERVICE_HOME, 0700, true);
@mkdir($SERVICE_GNUPG, 0700, true);
@chmod($SERVICE_HOME, 0700);
@chmod($SERVICE_GNUPG, 0700);

// Env passate al processo (evita HOME=/var/www e GNUPG in /var/www/.gnupg)
$env = [


//   'HOME'      => $SERVICE_HOME,
   'GNUPGHOME' => $SERVICE_GNUPG,
   'LANG'      => 'C',
   'LC_ALL'    => 'C',
   'GLPI_ROOT_DIR'   => $glpiRoot ?: '',
   'GLPI_PUBLIC_DIR' => $glpiPublic ?: '',
];


?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlescape($scriptKey); ?> – realtime</title>
<style>
html,body{margin:0;padding:0;background:#111;color:#0f0;font-family:monospace}
#toolbar{display:flex;gap:8px;align-items:center;padding:8px 12px;background:#0b0b0b;border-bottom:1px solid #222;}
button{background:#222;color:#ccc;border:1px solid #444;padding:6px 10px;border-radius:4px;cursor:pointer}
button:hover{background:#2c2c2c;color:#fff}
#term{padding:12px;white-space:pre-wrap;line-height:1.25;overflow:auto;height:calc(100vh - 46px);box-sizing:border-box}
.hl{color:#8cf}
.err{color:#f66}
</style>
</head>
<body>

<div id="toolbar">
  <button id="btnBack"><?php echo htmlescape(__('Torna all’asset', 'shellcmd')); ?></button>
  <span style="color:#7aa"><?php echo htmlescape($scriptKey); ?> <?php echo htmlescape(__('su', 'shellcmd')); ?> <?php echo htmlescape($ip); ?></span>
</div>
<div id="temp">
<?php 
gnupg_dir=$(pwd)
echo $gnupg_dir
pause

?>   

</div>
<div id="term"><span class="hl"><?php echo htmlescape(__('Esecuzione', 'shellcmd')); ?> <?php echo htmlescape($scriptKey); ?> <?php echo htmlescape(__('su', 'shellcmd')); ?> <?php echo htmlescape($ip); ?>…</span>




<?php
// “primer” per sbloccare buffering di alcuni proxy/browser
echo "\n\n" . str_repeat(' ', 8192);
flush();

// Esecuzione
$cmd = [$commandPath, $ip];

$descriptors = [
   0 => ['pipe', 'r'],
   1 => ['pipe', 'w'],
   2 => ['pipe', 'w'],
];

$proc = @proc_open($cmd, $descriptors, $pipes, null, $env);

if (!is_resource($proc)) {
   echo "\n<span class='err'>" . htmlescape(__('Errore: impossibile eseguire il comando.', 'shellcmd')) . "</span>\n";
   flush();
} else {
   fclose($pipes[0]);
   stream_set_blocking($pipes[1], false);
   stream_set_blocking($pipes[2], false);

   while (true) {
      $read = [$pipes[1], $pipes[2]];
      $write = null;
      $except = null;

      @stream_select($read, $write, $except, 0, 200000);

      foreach ($read as $r) {
         $chunk = stream_get_contents($r);
         if ($chunk !== false && $chunk !== '') {
            if ($r === $pipes[2]) {
               echo '<span class="err">' . htmlescape($chunk) . '</span>';
            } else {
               echo htmlescape($chunk);
            }
            flush();
         }
      }

      $status = proc_get_status($proc);
      if ($status && !$status['running']) {
         $rem1 = stream_get_contents($pipes[1]);
         $rem2 = stream_get_contents($pipes[2]);
         if ($rem1) echo htmlescape($rem1);
         if ($rem2) echo '<span class="err">' . htmlescape($rem2) . '</span>';
         flush();
         break;
      }
   }

   fclose($pipes[1]);
   fclose($pipes[2]);
   proc_close($proc);
}

echo "\n\n<span class='hl'>" . htmlescape(__('Operazione completata.', 'shellcmd')) . "</span>\n";
flush();
?>
</div>

<script>
// In GLPI 11 conviene evitare di concatenare HTML non escapato dentro JS.
// Qui usiamo json_encode (equivalente a JS safe string) per l’URL di ritorno.
document.getElementById('btnBack').addEventListener('click', function() {
  window.location.href = <?php echo json_encode($returnUrl); ?>;
});
</script>

</body>
</html>
