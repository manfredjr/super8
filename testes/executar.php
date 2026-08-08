<?php

$arquivos = glob(__DIR__ . '/teste_*.php');
$falhou = 0;

foreach ($arquivos as $arquivo) {
    echo "\n=== " . basename($arquivo) . " ===\n";
    $saida = [];
    $codigo = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($arquivo), $saida, $codigo);
    echo implode("\n", $saida) . "\n";
    if ($codigo !== 0) {
        $falhou++;
    }
}

echo "\n" . ($falhou === 0 ? 'TUDO PASSOU' : "{$falhou} arquivo(s) com falha") . "\n";
exit($falhou === 0 ? 0 : 1);
