<?php

require __DIR__ . '/cabecalho.php';

$titulo = 'Politica de privacidade';
ob_start();
require __DIR__ . '/../views/privacidade.php';
$conteudo = ob_get_clean();
require __DIR__ . '/../views/layout.php';
