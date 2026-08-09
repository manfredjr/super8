<?php

require __DIR__ . '/cabecalho.php';

$versao = TERMO_VERSAO;
$titulo = 'Termo de uso';
ob_start();
require __DIR__ . '/../views/termo.php';
$conteudo = ob_get_clean();
require __DIR__ . '/../views/layout.php';
