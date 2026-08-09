<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);
$campeonatos = Campeonato::listarDoOrganizador($pdo, (int) $usuario['id']);

renderizar('campeonatos', 'Meus campeonatos', [
    'campeonatos' => $campeonatos,
]);
