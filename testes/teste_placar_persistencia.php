<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';
require __DIR__ . '/../src/Placar.php';

echo "Placar (persistencia)\n";

$pdo = db();
$pdo->beginTransaction();

$organizadorId = Auth::cadastrar($pdo, 'Organizador Placar', 'placar' . random_int(1000, 9999) . '@exemplo.com', 'senhaforte123');

$campeonatoId = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Super 8 para testar placar',
    'data_evento' => '2026-09-10',
    'local'       => 'Arena Placar',
    'custo'       => '',
    'descricao'   => '',
]);
foreach (range(1, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoId, "Jogador {$numero}", null);
}
Campeonato::sortear($pdo, $campeonatoId, 7777);

// --- gravar(): validacao dos limites -------------------------------------
$partidas = $pdo->prepare(
    'SELECT p.id, r.numero, p.quadra FROM partidas p JOIN rodadas r ON r.id = p.rodada_id
     WHERE r.campeonato_id = ? ORDER BY r.numero, p.quadra'
);
$partidas->execute([$campeonatoId]);
$linhasPartidas = $partidas->fetchAll();
Teste::igual(14, count($linhasPartidas), 'o campeonato sorteado tem 14 partidas para gravar placar');

$idPrimeiraPartida = (int) $linhasPartidas[0]['id'];

foreach ([[-1, 3], [3, -1], [100, 3], [3, 100]] as [$gA, $gB]) {
    $erro = null;
    try {
        Placar::gravar($pdo, $idPrimeiraPartida, $gA, $gB, $organizadorId);
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::igual(
        'Os games precisam ficar entre 0 e 99.',
        $erro,
        "gravar recusa games fora de 0-99 ({$gA}, {$gB})"
    );
}

// Os limites 0 e 99 propriamente ditos tem que ser aceitos (validacao usa <
// e >, nao <= e >=).
Placar::gravar($pdo, $idPrimeiraPartida, 0, 99, $organizadorId);
$lida = $pdo->prepare('SELECT games_a, games_b, encerrada, gravado_por, gravado_em FROM partidas WHERE id = ?');
$lida->execute([$idPrimeiraPartida]);
$linhaLida = $lida->fetch();
Teste::igual(0, (int) $linhaLida['games_a'], 'aceita o limite inferior 0');
Teste::igual(99, (int) $linhaLida['games_b'], 'aceita o limite superior 99');
Teste::igual(1, (int) $linhaLida['encerrada'], 'gravar marca a partida como encerrada');
Teste::igual($organizadorId, (int) $linhaLida['gravado_por'], 'gravar registra quem gravou o placar');
Teste::verdade($linhaLida['gravado_em'] !== null, 'gravar registra o horario');

// --- gravar() funciona dentro de uma transacao ja aberta pelo chamador ---
// (o mesmo padrao de Campeonato::inscrever/sortear: no transacaoPropria
// guard, gravar nao pode tentar abrir uma segunda transacao aninhada, que o
// PDO/MariaDB nao suportam do jeito que o codigo assume.)
Teste::verdade($pdo->inTransaction(), 'o teste ja esta dentro de uma transacao (a de fora)');
Placar::gravar($pdo, $idPrimeiraPartida, 6, 4, $organizadorId);
Teste::verdade($pdo->inTransaction(), 'gravar chamado dentro de uma transacao existente nao fecha essa transacao');
$lida->execute([$idPrimeiraPartida]);
$linhaLida = $lida->fetch();
Teste::igual(6, (int) $linhaLida['games_a'], 'a gravacao seguinte, dentro da mesma transacao do chamador, atualiza o placar');
Teste::igual(4, (int) $linhaLida['games_b'], 'games_b tambem atualiza');

// gravar() com um id de partida que nao existe nao pode lancar excecao (nao
// ha nada para travar nem para atualizar, o UPDATE so afeta 0 linhas,
// igual uma UPDATE comum contra um id inexistente).
Placar::gravar($pdo, 999999999, 6, 3, $organizadorId);
Teste::verdade(true, 'gravar com id de partida inexistente nao lanca excecao (UPDATE de 0 linhas)');

// --- Torneio completo: as 14 partidas, conferidas a mao -------------------
// Os resultados abaixo foram somados partida a partida, na mao, para as
// posicoes de sorteio 1 e 8 (ver task-8-report.md para a conta completa).
// Os placares usam a POSICAO do sorteio (1 a 8), nao o id de inscricao, e
// sao traduzidos para dupla_a_j1 etc. via porPosicao, na mesma ordem de
// Rodizio::RODADAS (quadra 1 = duplaA/duplaB do primeiro item da rodada,
// quadra 2 = do segundo).
$porPosicao = [];
foreach (Campeonato::listarInscricoes($pdo, $campeonatoId) as $inscricao) {
    $porPosicao[(int) $inscricao['posicao_sorteio']] = (int) $inscricao['id'];
}

// [numero da rodada, quadra] => [games_a, games_b], na mesma ordem de
// Rodizio::RODADAS[numero][quadra - 1] = [duplaA, duplaB].
$placarPorRodada = [
    1 => [1 => [6, 3], 2 => [5, 5]],
    2 => [1 => [4, 6], 2 => [6, 1]],
    3 => [1 => [6, 5], 2 => [6, 6]],
    4 => [1 => [6, 2], 2 => [6, 4]],
    5 => [1 => [5, 6], 2 => [3, 6]],
    6 => [1 => [6, 6], 2 => [7, 2]],
    7 => [1 => [3, 6], 2 => [2, 6]],
];

$idsPartidasGravadas = [];
foreach ($linhasPartidas as $linha) {
    $numero = (int) $linha['numero'];
    $quadra = (int) $linha['quadra'];
    [$gamesA, $gamesB] = $placarPorRodada[$numero][$quadra];
    Placar::gravar($pdo, (int) $linha['id'], $gamesA, $gamesB, $organizadorId);
    $idsPartidasGravadas[] = (int) $linha['id'];
}
Teste::igual(14, count($idsPartidasGravadas), 'gravou o placar das 14 partidas');

Teste::verdade(
    Campeonato::temPlacarLancado($pdo, $campeonatoId),
    'depois de gravar os placares, temPlacarLancado enxerga o campeonato como jogado'
);

$classificacao = Placar::classificacao($pdo, $campeonatoId);
Teste::igual(8, count($classificacao), 'a classificacao tem os 8 competidores');

$porInscricaoId = array_column($classificacao, null, 'inscricao_id');
$idPosicao1 = $porPosicao[1];
$idPosicao8 = $porPosicao[8];

// Conferencia a mao (posicao 1): jogos contra as posicoes 8, 3(dupla), 6/7,
// 2/6, 3/7, 2/3, 7/8 - somando feitos e sofridos rodada a rodada:
// R1 quadra1 (dupla 1,8 vs 2,7) 6-3: feitos 6, sofridos 3, vitoria
// R2 quadra1 (dupla 2,8 vs 1,3) 4-6: feitos 6, sofridos 4, vitoria (posicao 1 esta na dupla B)
// R3 quadra2 (dupla 1,5 vs 6,7) 6-6: feitos 6, sofridos 6, empate
// R4 quadra2 (dupla 2,6 vs 1,7) 6-4: feitos 4, sofridos 6, derrota (posicao 1 esta na dupla B)
// R5 quadra2 (dupla 3,7 vs 1,2) 3-6: feitos 6, sofridos 3, vitoria (posicao 1 esta na dupla B)
// R6 quadra2 (dupla 1,4 vs 2,3) 7-2: feitos 7, sofridos 2, vitoria
// R7 quadra1 (dupla 7,8 vs 1,6) 3-6: feitos 6, sofridos 3, vitoria (posicao 1 esta na dupla B)
// total games = 6+6+6+4+6+7+6 = 41; sofridos = 3+4+6+6+3+2+3 = 27; saldo = 14; vitorias = 5
Teste::igual(41, $porInscricaoId[$idPosicao1]['games'], 'posicao 1: 41 games somados a mao, batendo com a classificacao');
Teste::igual(27, $porInscricaoId[$idPosicao1]['sofridos'], 'posicao 1: 27 games sofridos somados a mao');
Teste::igual(14, $porInscricaoId[$idPosicao1]['saldo'], 'posicao 1: saldo 14');
Teste::igual(5, $porInscricaoId[$idPosicao1]['vitorias'], 'posicao 1: 5 vitorias');
Teste::igual(7, $porInscricaoId[$idPosicao1]['jogadas'], 'posicao 1: jogou as 7 partidas');

// Conferencia a mao (posicao 8):
// R1 quadra1 (dupla 1,8 vs 2,7) 6-3: feitos 6, sofridos 3, vitoria
// R2 quadra1 (dupla 2,8 vs 1,3) 4-6: feitos 4, sofridos 6, derrota (posicao 8 esta na dupla A)
// R3 quadra1 (dupla 3,8 vs 2,4) 6-5: feitos 6, sofridos 5, vitoria
// R4 quadra1 (dupla 4,8 vs 3,5) 6-2: feitos 6, sofridos 2, vitoria
// R5 quadra1 (dupla 5,8 vs 4,6) 5-6: feitos 5, sofridos 6, derrota
// R6 quadra1 (dupla 6,8 vs 5,7) 6-6: feitos 6, sofridos 6, empate
// R7 quadra1 (dupla 7,8 vs 1,6) 3-6: feitos 3, sofridos 6, derrota (posicao 8 esta na dupla A)
// total games = 6+4+6+6+5+6+3 = 36; sofridos = 3+6+5+2+6+6+6 = 34; saldo = 2; vitorias = 3
Teste::igual(36, $porInscricaoId[$idPosicao8]['games'], 'posicao 8: 36 games somados a mao, batendo com a classificacao');
Teste::igual(34, $porInscricaoId[$idPosicao8]['sofridos'], 'posicao 8: 34 games sofridos somados a mao');
Teste::igual(2, $porInscricaoId[$idPosicao8]['saldo'], 'posicao 8: saldo 2');
Teste::igual(3, $porInscricaoId[$idPosicao8]['vitorias'], 'posicao 8: 3 vitorias');
Teste::igual(7, $porInscricaoId[$idPosicao8]['jogadas'], 'posicao 8: jogou as 7 partidas');

// A posicao 1 (41 games) fica na frente da posicao 8 (36 games) na
// classificacao final.
$ids = array_column($classificacao, 'inscricao_id');
Teste::verdade(
    array_search($idPosicao1, $ids, true) < array_search($idPosicao8, $ids, true),
    'posicao 1 (mais games) fica classificada na frente da posicao 8'
);

// Soma de conferencia: a soma de "games" de todas as 8 linhas tem que bater
// com a soma de todos os games_a + games_b gravados nas 14 partidas vezes 2
// (cada jogador de cada dupla soma o mesmo total feito pela dupla, entao
// cada games_a/games_b conta 2 vezes, uma por jogador da dupla).
$somaTotalPlacares = 0;
foreach ($placarPorRodada as $porQuadra) {
    foreach ($porQuadra as [$gA, $gB]) {
        $somaTotalPlacares += $gA + $gB;
    }
}
$somaClassificacao = array_sum(array_column($classificacao, 'games'));
Teste::igual(
    $somaTotalPlacares * 2,
    $somaClassificacao,
    'a soma de games de todas as linhas da classificacao bate com 2x a soma de todos os placares gravados'
);

$pdo->rollBack();

exit(Teste::resumo());
