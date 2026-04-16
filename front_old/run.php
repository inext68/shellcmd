<?php
/**
 * Shell CMD Runner - GLPI 10/11 Streaming Fix
 * 
 * Istruzioni critiche:
 * 1. NON inserire spazi o newline prima del primo <?php.
 * 2. NON inserire il tag ?> finale.
 * 3. Salvare come UTF-8 SENZA BOM.
 */

// 1. Controllo di sicurezza: Evita l'inclusione in contesti non previsti
if (!defined('GLPI_ROOT')) {
    http_response_code(403);
    exit('Accesso non autorizzato.');
}

// 2. Caricamento GLPI
if (!file_exists(__DIR__ . '/../../../inc/includes.php')) {
    exit('Errore: File GLPI non trovato.');
}
include_once __DIR__ . '/../../../inc/includes.php';

// 3. Verifica sessione e permessi
Session::checkLoginUser();
if (!Session::haveRight('config', UPDATE)) {
    Html::displayRightError();
    exit;
}

require_once __DIR__ . '/../inc/runner.class.php';

// 4. DEFINIZIONE PERCORSI
$glpiRoot   = realpath(dirname(__DIR__, 3));
$glpiPublic = realpath($glpiRoot . '/public');
$SERVICE_HOME  = dirname(__DIR__, 1);
$SERVICE_GNUPG = $SERVICE_HOME . '/.gnupg';

$action = $_GET['action'] ?? '';

// 5. GESTIONE PAGINA DI CORTESIA (non 'run')
if ($action !== 'run') {
    // Pulizia CRITICA dei buffer prima di qualsiasi output
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Disabilita buffering di GLPI per questo file
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    
    // Imposta header
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    Html::header(__('Shell CMD', 'shellcmd'), $_SERVER['PHP_SELF'], "tools");
    echo "<div class='center'>";
    echo "<h2>" . htmlescape(__('Shell CMD', 'shellcmd')) . "</h2>";
    echo "<p>" . htmlescape(__('Questo plugin è pensato per l’uso dal TAB degli asset.', 'shellcmd')) . "</p>";
    echo "</div>";
    Html::footer();
    exit;
}

// 6. PREPARAZIONE STREAMING (CRITICO PER GLPI)
// Pulizia totale dei buffer residui di GLPI
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Disabilita buffering di sistema
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@ini_set('max_execution_time', 0); // Nessuna limitazione tempo

// Recupero parametri
$ip        = $_GET['ip'] ?? '';
$scriptKey = $_GET['script'] ?? '';
$returnUrl = $_GET['return'] ?? (CFG_GLPI['root_doc'] . '/front/central.php');

// Validazione IP
if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || in_array($ip, ['0.0.0.0', '127.0.0.1', '::1'])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Errore: IP non valido.\n");
}

$WHITELIST = PluginShellcmdRunner::getScriptsWhitelist();
if (empty($scriptKey) || !isset($WHITELIST[$scriptKey])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Errore: Script non autorizzato.\n");
}

$commandPath = $WHITELIST[$scriptKey];
if (!file_exists($commandPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Errore: File del comando non trovato: " . $commandPath . "\n");
}

// 7. SETUP AMBIENTE
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

// 8. HEADER PER STREAMING (Disabilita cache e buffering proxy)
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, no-transform');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no'); // Per Nginx
header('Connection: close'); // Importante per disabilitare keep-alive che blocca lo streaming

// 9. OUTPUT HTML
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
    <span class="hl"><?php echo htmlescape(__('Inizio esecuzione...', 'shellcmd')); ?> <?php echo htmlescape($scriptKey); ?> su <?php echo htmlescape($ip); ?>...</span><br>

<?php
$cmd = [$commandPath, $ip];
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

$proc = @proc_open($cmd, $descriptors, $pipes, null, $env);

if (!is_resource($proc)) {
    echo '<span class="err">' . htmlescape(__('Errore: impossibile eseguire il comando.', 'shellcmd')) . '</span><br>';
    flush();
} else {
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;

        // Timeout di 200ms per evitare loop infinito
        if (@stream_select($read, $write, $except, 0, 200000) === false) {
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
                // FLUSH OBBLIGATORIO
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                @flush();
            }
        }

        $status = proc_get_status($proc);
        if ($status && !$status['running']) {
            $rem1 = @stream_get_contents($pipes[1]);
            $rem2 = @stream_get_contents($pipes[2]);
            if ($rem1) echo htmlescape($rem1);
            if ($rem2) echo '<span class="err">' . htmlescape($rem2) . '</span>';
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