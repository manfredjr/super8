<?php

/**
 * Atende ao direito de exclusao da LGPD sem apagar registro: a regra do
 * projeto proibe exclusao de dado, entao a saida e anonimizar e desativar a
 * conta, nunca fazer DELETE nela ou em qualquer linha que dependa dela. A
 * logica de verdade mora em Auth::anonimizarPorEmail (src/Auth.php); este
 * arquivo so valida o ponto de entrada e imprime o resultado.
 *
 * So roda em linha de comando (PHP_SAPI !== 'cli' devolve 404 em silencio):
 * e uma operacao rara, feita pelo administrador com acesso direto ao banco,
 * e nao merece ponto de entrada exposto na web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Auth.php';

$email = $argv[1] ?? '';
if ($email === '') {
    exit("Uso: php admin/anonimizar.php email@do.titular\n");
}

$pdo = db();
$id = Auth::anonimizarPorEmail($pdo, $email);

if ($id === null) {
    exit("Nenhuma conta com esse e-mail.\n");
}

echo "Conta {$id} anonimizada e desativada. O nome de exibicao de cada inscricao desse titular virou um "
    . "identificador anonimo e perdeu o vinculo com a conta; os placares de cada campeonato foram mantidos, e a "
    . "conta some do ranking acumulado.\n";
