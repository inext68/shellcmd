<?php
/**
 * Shell CMD Plugin - Runner Script
 * Corretto per evitare errori di Output Buffer
 */

// Caricamento ambiente GLPI
if (!file_exists(__DIR__ . '/../../../inc/includes.php')) {
    // Non usare echo prima di header, ma usiamo die() con codice di stato
    http_response_code(500);
    die('Errore: Impossibile trovare il file di configurazione GLPI. Verifica il percorso.');
}
include_once __DIR__ . '/../../../inc/includes.php';

Session::checkLoginUser();

if (!Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
    exit;
}

require_once __DIR__ . '/../inc/runner.class.php';

$glpiRoot   = realpath(dirname(__DIR__, 3));
$glpiPublic = realpath($glpiRoot . '/public');

$SERVICE_HOME  = dirname(__DIR__, 1);
$SERVICE_GNUPG = $SERVICE_HOME . '/.gnupg';

$action = $_GET['action'] ?? '';

if ($action !== 'run') {
    Html::header(__('Shell CMD', 'shellcmd'), $_SERVER['PHP_SELF'], "tools");
    echo "<div class='center'>";
    echo "<h2>" . htmlescape(__('Shell CMD', 'shellcmd')) . "</h2>";
    echo "<p>" . htmlescape(__('Questo plugin è pensato per l’uso dal TAB degli asset.', 'shellcmd')) . "</p>";
    echo "</div>";
    Html::footer();
    exit;
}

// Rimuoviamo ob_start() se non strettamente necessario per lo streaming, 
// ma lo teniamo per sicurezza se ci sono include precedenti.
// Tuttavia, il problema principale è l'output "prima" o "dopo".
// GLPI gestisce già i buffer.

$ip        = $_GET['ip'] ?? '';
$scriptKey = $_GET['script'] ?? '';
$returnUrl = $_GET['return'] ?? (CFG_GLPI['root_doc'] . '/front/central.php');

// Validazione IP
if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $ip === '0.0.0.0' || $ip === '127.0.0.1' || $ip === '::1') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit("Errore: IP non valido.\n");
}

$WHITELIST = PluginShellcmdRunner::getScriptsWhitelist();

if (empty($scriptKey) || !isset($WHITELIST[$scriptKey])) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit("Errore: Script non autorizzato.\n");
}

$commandPath = $WHITELIST[$scriptKey];

if (!file_exists($commandPath)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    exit("Errore: File del comando non trovato: " . $commandPath . "\n");
}

$env = [
    'GNUPGHOME' => $SERVICE_GNUPG,
    'LANG'      => 'C',
    'LC_ALL'    => 'C',
    'GLPI_ROOT_DIR'   => $glpiRoot ?: '',
    'GLPI_PUBLIC_DIR' => $glpiPublic ?: '',
];

@mkdir($SERVICE_GNUPG, 0700, true);
@chmod($SERVICE_HOME, 0700);
@chmod($SERVICE_GNUPG, 0700);

// Header per streaming
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');

// Disabilita buffering per streaming
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

// Pulizia eventuali buffer residui di GLPI
while (ob_get_level() > 0) {
    ob_end_clean();
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlescape($scriptKey); ?> – Esecuzione Realtime</title>
<style>
    html, body { margin: 0; padding: 0; background: #111; color: #0f0; font-family: 'Consolas', 'Monaco', monospace; height: 100%; }
    #toolbar { display: flex; gap: 8px; align-items: center; padding: 8px 12px; background: #0b0b0b; border-bottom: 1px solid #222; }
    button { background: #222; color: #ccc; border: 1px solid #444; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-family: inherit; }
    button:hover { background: #2c2c2c; color: #fff; }
    #term { padding: 12px; white-space: pre-wrap; line-height: 1.4; overflow: auto; height: calc(100vh - 46px); box-sizing: border-box; }
    .hl { color: #8cf; font-weight: bold; }
    .err { color: #f66; }
    .success { color: #6f6; }
</style>
</head>
<body>

<div id="toolbar">
    <button id="btnBack"><?php echo htmlescape(__('Torna all’asset', 'shellcmd')); ?></button>
    <span style="color:#7aa">Esecuzione: <?php echo htmlescape($scriptKey); ?> su <?php echo htmlescape($ip); ?></span>
</div>

<div id="term">
    <span class="hl">
        <?php echo htmlescape(__('Inizio esecuzione...', 'shellcmd')); ?> 
        <?php echo htmlescape($scriptKey); ?> 
        su <?php echo htmlescape($ip); ?>…
    </span>
    <br>

<?php
$cmd = [$commandPath, $ip];

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = @proc_open($cmd, $descriptors, $pipes, null, $env);

if (!is_resource($proc)) {
    echo '<span class="err">' . htmlescape(__('Errore: impossibile inizializzare il processo.', 'shellcmd')) . '</span><br>';
    flush();
} else {
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;

        $result = @stream_select($read, $write, $except, 0, 200000);

        if ($result === false) {
            break;
        }

        foreach ($read as $r) {
            $chunk = @stream_get_contents($r);
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
            $rem1 = @stream_get_contents($pipes[1]);
            $rem2 = @stream_get_contents($pipes[2]);
            
            if ($rem1) echo htmlescape($rem1);
            if ($rem2) {
                echo '<span class="err">' . htmlescape($rem2) . '</span>';
            }
            flush();
            break;
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    
    if ($exitCode === 0) {
        echo '<br><span class="success">' . htmlescape(__('Operazione completata con successo.', 'shellcmd')) . '</span>';
    } else {
        echo '<br><span class="err">' . htmlescape(sprintf(__('Processo terminato con codice di errore: %d', 'shellcmd'), $exitCode)) . '</span>';
    }
    flush();
}
?>

</div>

<script>
document.getElementById('btnBack').addEventListener('click', function() {
    window.location.href = <?php echo json_encode($returnUrl); ?>;
});
</script>

</body>
</html>