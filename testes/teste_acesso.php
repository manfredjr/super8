<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/acesso.php';
require __DIR__ . '/../src/Auth.php';

echo "Acesso\n";

$pdo = db();

/**
 * O caminho de nao-dono (e o de sessao invalida/desativada) chama exit(),
 * entao roda em outro processo. Ver o comentario no topo do arquivo
 * ajudante.
 */
function rodarAjudanteDono(string $ajudante, int $idUsuario, int $idCampeonato): array
{
    $saida = [];
    $codigo = 0;
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ajudante) . ' '
            . escapeshellarg((string) $idUsuario) . ' ' . escapeshellarg((string) $idCampeonato),
        $saida,
        $codigo
    );
    return [$codigo, implode("\n", $saida)];
}

// As linhas criadas aqui nao ficam dentro de uma transacao: o ajudante roda
// em outro processo, com sua propria conexao, e precisa enxergar os dados
// ja gravados. Por isso a limpeza no final apaga cada linha explicitamente.
$idOrganizador1 = null;
$idOrganizador2 = null;
$idCampeonato = null;

try {
    // B2: usuarioLogado() falha fechado quando a sessao nao tem um array.
    $_SESSION['usuario'] = 'nao e um array';
    Teste::igual(null, usuarioLogado(), 'usuarioLogado devolve nulo quando a sessao nao tem um array');
    unset($_SESSION['usuario']);
    Teste::igual(null, usuarioLogado(), 'usuarioLogado devolve nulo sem sessao nenhuma');

    $organizador1 = 'dono' . uniqid() . '@exemplo.com';
    $organizador2 = 'intruso' . uniqid() . '@exemplo.com';
    $idOrganizador1 = Auth::cadastrar($pdo, 'Dono', $organizador1, 'senhaforte123');
    $idOrganizador2 = Auth::cadastrar($pdo, 'Intruso', $organizador2, 'senhaforte123');

    $insereCampeonato = $pdo->prepare(
        "INSERT INTO campeonatos (organizador_id, nome, data_evento, status, criado_em)
         VALUES (?, ?, CURDATE(), 'rascunho', NOW())"
    );
    $insereCampeonato->execute([$idOrganizador1, 'Campeonato de Teste']);
    $idCampeonato = (int) $pdo->lastInsertId();

    // Caminho do dono: logica pura, sem exit, testada no mesmo processo.
    $_SESSION['usuario'] = ['id' => $idOrganizador1];
    $campeonatoLido = exigirDonoDoCampeonato($pdo, $idCampeonato);
    Teste::igual($idCampeonato, (int) $campeonatoLido['id'], 'o dono recebe a linha do proprio campeonato');
    unset($_SESSION['usuario']);

    $ajudante = __DIR__ . '/_ajuda_dono_campeonato.php';

    // Caminho de quem nao e dono: o campeonato existe, mas pertence a outra
    // pessoa.
    [$codigoIntruso, $saidaIntruso] = rodarAjudanteDono($ajudante, $idOrganizador2, $idCampeonato);
    Teste::verdade($codigoIntruso !== 0, 'quem nao e dono do campeonato e rejeitado');
    Teste::verdade(str_contains($saidaIntruso, 'Campeonato nao encontrado.'), 'a rejeicao mostra a mensagem generica');
    Teste::verdade(str_contains($saidaIntruso, 'codigo_http=404'), 'a rejeicao usa 404, nao 403 (403 confirmaria que o id existe)');

    // Mesmo teste, mas o id nem existe. A resposta precisa ser identica a de
    // cima: nada pode diferenciar "existe e nao e seu" de "nao existe".
    [$codigoInexistente, $saidaInexistente] = rodarAjudanteDono($ajudante, $idOrganizador2, 999999999);
    Teste::verdade($codigoInexistente !== 0, 'campeonato inexistente tambem e rejeitado');
    Teste::igual(
        $saidaIntruso,
        $saidaInexistente,
        'a resposta e igual, byte a byte, para "existe mas nao e seu" e para "nao existe": nao da para diferenciar os dois casos pela saida'
    );

    // B1: desativar o dono precisa cortar o acesso na hora, mesmo que a
    // sessao dele ainda "pareca" valida. exigirLogin tem que reler o banco
    // a cada chamada, e nao confiar na copia parada em $_SESSION.
    $pdo->prepare('UPDATE users SET ativo = 0 WHERE id = ?')->execute([$idOrganizador1]);
    [$codigoDesativado, $saidaDesativado] = rodarAjudanteDono($ajudante, $idOrganizador1, $idCampeonato);
    Teste::verdade($codigoDesativado !== 0, 'dono desativado e rejeitado mesmo sendo dono de verdade do campeonato');
    Teste::verdade(
        !str_contains($saidaDesativado, 'Campeonato nao encontrado.'),
        'a rejeicao de usuario desativado acontece dentro de exigirLogin, antes mesmo de chegar na checagem de dono'
    );
    Teste::verdade(!str_contains($saidaDesativado, 'dono confirmado'), 'usuario desativado nao recebe o campeonato');
} finally {
    if ($idCampeonato !== null) {
        $pdo->prepare('DELETE FROM campeonatos WHERE id = ?')->execute([$idCampeonato]);
    }
    if ($idOrganizador2 !== null) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$idOrganizador2]);
    }
    if ($idOrganizador1 !== null) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$idOrganizador1]);
    }
    unset($_SESSION['usuario']);
}

exit(Teste::resumo());
