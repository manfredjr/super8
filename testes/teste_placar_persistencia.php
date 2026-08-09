<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Validador.php';
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
        Placar::gravar($pdo, $campeonatoId, $idPrimeiraPartida, $gA, $gB, $organizadorId);
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
Placar::gravar($pdo, $campeonatoId, $idPrimeiraPartida, 0, 99, $organizadorId);
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
Placar::gravar($pdo, $campeonatoId, $idPrimeiraPartida, 6, 4, $organizadorId);
Teste::verdade($pdo->inTransaction(), 'gravar chamado dentro de uma transacao existente nao fecha essa transacao');
$lida->execute([$idPrimeiraPartida]);
$linhaLida = $lida->fetch();
Teste::igual(6, (int) $linhaLida['games_a'], 'a gravacao seguinte, dentro da mesma transacao do chamador, atualiza o placar');
Teste::igual(4, (int) $linhaLida['games_b'], 'games_b tambem atualiza');

// gravar() com um id de campeonato que nao existe tem que lancar
// RuntimeException, travando nada: e o primeiro passo do metodo agora
// (campeonato primeiro, sempre), entao nem chega a olhar a partida.
$erroCampeonatoInexistente = null;
try {
    Placar::gravar($pdo, 999999999, $idPrimeiraPartida, 6, 3, $organizadorId);
} catch (RuntimeException $excecao) {
    $erroCampeonatoInexistente = $excecao->getMessage();
}
Teste::igual(
    'O campeonato informado não existe.',
    $erroCampeonatoInexistente,
    'gravar com id de campeonato inexistente lanca RuntimeException'
);

// gravar() com um id de partida que nao existe (em lugar nenhum) tem que
// lancar RuntimeException, nao silenciar num UPDATE de 0 linhas: a
// confirmacao de que a partida pertence ao campeonato agora usa leitura
// travada (FOR UPDATE), e se ela nao acha nada, e porque a partida de
// verdade nao existe (nao um retrato antigo escondendo uma linha real) -
// deixar passar para o UPDATE final gravaria um placar numa partida que
// nunca foi travada, ou simplesmente nao faria nada em silencio.
$erroPartidaInexistente = null;
try {
    Placar::gravar($pdo, $campeonatoId, 999999999, 6, 3, $organizadorId);
} catch (RuntimeException $excecao) {
    $erroPartidaInexistente = $excecao->getMessage();
}
Teste::igual(
    'A partida informada não existe neste campeonato.',
    $erroPartidaInexistente,
    'gravar com id de partida inexistente lanca RuntimeException, em vez de um UPDATE silencioso de 0 linhas'
);

// --- gravar() recusa uma partida que existe, mas pertence a OUTRO
// campeonato (controle de posse "de graca") -------------------------------
// $campeonatoId ser parametro explicito (em vez de resolvido a partir da
// partida) da o controle de posse de graca: a confirmacao
// "WHERE p.id = ? AND r.campeonato_id = ?" nao acha nada tanto para um id de
// partida que nao existe em lugar nenhum quanto para um id de partida que
// existe, mas pertence a outro campeonato - e por isso as DUAS situacoes
// tem que produzir exatamente a MESMA mensagem de erro. Se as mensagens
// fossem diferentes, um organizador mal-intencionado poderia usar a
// diferenca para descobrir, testando ids de partida a esmo, se um id
// especifico existe em outro campeonato alheio (um oraculo de existencia).
$campeonatoAlheio = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Campeonato alheio para testar posse',
    'data_evento' => '2026-09-11',
    'local'       => 'Arena Alheia',
    'custo'       => '',
    'descricao'   => '',
]);
foreach (range(1, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoAlheio, "Jogador alheio {$numero}", null);
}
Campeonato::sortear($pdo, $campeonatoAlheio, 3333);
$buscaPartidaAlheia = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ? ORDER BY r.numero, p.quadra LIMIT 1'
);
$buscaPartidaAlheia->execute([$campeonatoAlheio]);
$idPartidaAlheia = (int) $buscaPartidaAlheia->fetchColumn();

$erroPartidaAlheia = null;
try {
    // Passa o campeonato de TESTE ($campeonatoId), mas o id de uma partida
    // que pertence ao campeonato ALHEIO ($campeonatoAlheio) - exatamente o
    // que um controller que ja resolveu e autorizou o campeonato errado
    // receberia se alguem tentasse gravar um placar usando o id de partida
    // de outro organizador.
    Placar::gravar($pdo, $campeonatoId, $idPartidaAlheia, 6, 3, $organizadorId);
} catch (RuntimeException $excecao) {
    $erroPartidaAlheia = $excecao->getMessage();
}
Teste::igual(
    'A partida informada não existe neste campeonato.',
    $erroPartidaAlheia,
    'gravar recusa uma partida que pertence a outro campeonato'
);
Teste::igual(
    $erroPartidaInexistente,
    $erroPartidaAlheia,
    'a mensagem de erro e IDENTICA para "partida nao existe em lugar nenhum" e "partida existe, mas e de outro campeonato" - nao da para diferenciar os dois casos pela mensagem'
);

// A partida do campeonato alheio continua sem placar: a rejeicao acima nao
// escreveu nada nela por engano.
$confereNaoEscreveu = $pdo->prepare('SELECT games_a, games_b, encerrada FROM partidas WHERE id = ?');
$confereNaoEscreveu->execute([$idPartidaAlheia]);
$linhaNaoEscrita = $confereNaoEscreveu->fetch();
Teste::igual(null, $linhaNaoEscrita['games_a'], 'a partida do campeonato alheio continua sem games_a depois da tentativa recusada');
Teste::igual(null, $linhaNaoEscrita['games_b'], 'a partida do campeonato alheio continua sem games_b depois da tentativa recusada');
Teste::igual(0, (int) $linhaNaoEscrita['encerrada'], 'a partida do campeonato alheio continua nao encerrada depois da tentativa recusada');

// --- Torneio completo: as 14 partidas, conferidas a mao -------------------
// Os resultados abaixo foram somados partida a partida, na mao, para as
// posicoes de sorteio 1, 4, 6 e 8 (ver task-8-report.md para a conta
// completa das 8 posicoes). Os placares usam a POSICAO do sorteio (1 a 8),
// nao o id de inscricao, e sao traduzidos para dupla_a_j1 etc. via
// porPosicao, na mesma ordem de Rodizio::RODADAS (quadra 1 = duplaA/duplaB
// do primeiro item da rodada, quadra 2 = do segundo).
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
    Placar::gravar($pdo, $campeonatoId, (int) $linha['id'], $gamesA, $gamesB, $organizadorId);
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
$idPosicao4 = $porPosicao[4];
$idPosicao6 = $porPosicao[6];
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

// Conferencia a mao (posicao 4): ATENCAO, esta posicao empata em games com a
// posicao 1 (41 cada), mas tem saldo melhor (18 contra 14) - e por isso
// quem fica em PRIMEIRO lugar de verdade neste torneio e a posicao 4, nao a
// posicao 1. Uma asserticao que so provasse "posicao 1 na frente da posicao
// 8" (como a versao anterior deste teste fazia) nunca pegaria um bug de
// saldo que trocasse a posicao 1 e a posicao 4 de lugar, ja que as duas
// continuariam na frente da posicao 8 de qualquer jeito.
// R1 quadra2 (dupla 3,6 vs 4,5) 5-5: feitos 5, sofridos 5, empate (posicao 4 esta na dupla B)
// R2 quadra2 (dupla 4,7 vs 5,6) 6-1: feitos 6, sofridos 1, vitoria
// R3 quadra1 (dupla 3,8 vs 2,4) 6-5: feitos 5, sofridos 6, derrota (posicao 4 esta na dupla B)
// R4 quadra1 (dupla 4,8 vs 3,5) 6-2: feitos 6, sofridos 2, vitoria
// R5 quadra1 (dupla 5,8 vs 4,6) 5-6: feitos 6, sofridos 5, vitoria (posicao 4 esta na dupla B)
// R6 quadra2 (dupla 1,4 vs 2,3) 7-2: feitos 7, sofridos 2, vitoria
// R7 quadra2 (dupla 2,5 vs 3,4) 2-6: feitos 6, sofridos 2, vitoria (posicao 4 esta na dupla B)
// total games = 5+6+5+6+6+7+6 = 41; sofridos = 5+1+6+2+5+2+2 = 23; saldo = 18; vitorias = 5
Teste::igual(41, $porInscricaoId[$idPosicao4]['games'], 'posicao 4: 41 games somados a mao, batendo com a classificacao');
Teste::igual(23, $porInscricaoId[$idPosicao4]['sofridos'], 'posicao 4: 23 games sofridos somados a mao');
Teste::igual(18, $porInscricaoId[$idPosicao4]['saldo'], 'posicao 4: saldo 18, melhor que o da posicao 1');
Teste::igual(5, $porInscricaoId[$idPosicao4]['vitorias'], 'posicao 4: 5 vitorias');
Teste::igual(7, $porInscricaoId[$idPosicao4]['jogadas'], 'posicao 4: jogou as 7 partidas');

// Conferencia a mao (posicao 6): outro par que empata em games (36, com a
// posicao 8) mas perde no saldo (1 contra 2), entao fica logo atras da
// posicao 8.
// R1 quadra2 (dupla 3,6 vs 4,5) 5-5: feitos 5, sofridos 5, empate (posicao 6 esta na dupla A)
// R2 quadra2 (dupla 4,7 vs 5,6) 6-1: feitos 1, sofridos 6, derrota (posicao 6 esta na dupla B)
// R3 quadra2 (dupla 1,5 vs 6,7) 6-6: feitos 6, sofridos 6, empate (posicao 6 esta na dupla B)
// R4 quadra2 (dupla 2,6 vs 1,7) 6-4: feitos 6, sofridos 4, vitoria
// R5 quadra1 (dupla 5,8 vs 4,6) 5-6: feitos 6, sofridos 5, vitoria (posicao 6 esta na dupla B)
// R6 quadra1 (dupla 6,8 vs 5,7) 6-6: feitos 6, sofridos 6, empate
// R7 quadra1 (dupla 7,8 vs 1,6) 3-6: feitos 6, sofridos 3, vitoria (posicao 6 esta na dupla B)
// total games = 5+1+6+6+6+6+6 = 36; sofridos = 5+6+6+4+5+6+3 = 35; saldo = 1; vitorias = 3
Teste::igual(36, $porInscricaoId[$idPosicao6]['games'], 'posicao 6: 36 games somados a mao, batendo com a classificacao');
Teste::igual(35, $porInscricaoId[$idPosicao6]['sofridos'], 'posicao 6: 35 games sofridos somados a mao');
Teste::igual(1, $porInscricaoId[$idPosicao6]['saldo'], 'posicao 6: saldo 1, pior que o da posicao 8');
Teste::igual(3, $porInscricaoId[$idPosicao6]['vitorias'], 'posicao 6: 3 vitorias');
Teste::igual(7, $porInscricaoId[$idPosicao6]['jogadas'], 'posicao 6: jogou as 7 partidas');

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

// Ordem completa das 8 posicoes de sorteio, calculada a mao a partir dos
// totais acima (games desc, saldo desc, vitorias desc - ninguem neste
// torneio chega a precisar de confronto direto ou nome, porque cada grupo
// de games empatados (41/41 e 36/36) ja se resolve no saldo):
// 4 (41g, saldo 18) > 1 (41g, saldo 14) > 8 (36g, saldo 2) > 6 (36g, saldo 1)
// > 7 (31g) > 3 (30g) > 2 (28g) > 5 (27g).
$ordemEsperada = array_map(
    static fn (int $posicao): int => $porPosicao[$posicao],
    [4, 1, 8, 6, 7, 3, 2, 5]
);
$ids = array_column($classificacao, 'inscricao_id');
Teste::igual(
    $ordemEsperada,
    $ids,
    'a ordem completa das 8 posicoes bate com a conta a mao (inclusive posicao 4 na frente da posicao 1, por saldo)'
);

// Nenhum dos 8 competidores empata em tudo (games, saldo, vitorias e
// confronto direto) com outro neste torneio - cada grupo de games iguais
// (41/41 e 36/36) ja se separa no saldo, como a ordem acima mostra. Os
// casos onde empatado tem que ficar true (pares e ciclos de confronto nao
// transitivo) estao cobertos em teste_placar.php, na funcao pura; aqui a
// intencao e confirmar que classificacao() de verdade devolve a chave
// empatado (nunca deixa de gera-la) e que ela nao acende por engano quando
// nao ha empate nenhum.
foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $posicao) {
    Teste::verdade(
        array_key_exists('empatado', $porInscricaoId[$porPosicao[$posicao]]),
        "posicao {$posicao}: a linha da classificacao tem a chave empatado"
    );
    Teste::verdade(
        !$porInscricaoId[$porPosicao[$posicao]]['empatado'],
        "posicao {$posicao}: nao fica marcada como empatada (nenhum grupo de games/saldo/vitorias tem mais de um membro neste torneio)"
    );
}

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
