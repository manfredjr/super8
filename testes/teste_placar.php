<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../src/Placar.php';

echo "Placar\n";

$inscricoes = [];
foreach (range(1, 4) as $numero) {
    $inscricoes[] = ['id' => 100 + $numero, 'nome_exibicao' => "Jogador {$numero}"];
}

$umaPartida = [[
    'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
    'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
    'games_a' => 6, 'games_b' => 4, 'encerrada' => 1,
]];

$linhas = Placar::classificarLinhas($inscricoes, $umaPartida);
$porId = array_column($linhas, null, 'inscricao_id');

Teste::igual(6, $porId[101]['games'], 'a dupla vencedora soma 6 games para cada jogador');
Teste::igual(6, $porId[102]['games'], 'o parceiro soma os mesmos games');
Teste::igual(4, $porId[101]['sofridos'], 'registra os games sofridos');
Teste::igual(2, $porId[101]['saldo'], 'calcula o saldo');
Teste::igual(1, $porId[101]['vitorias'], 'conta a vitoria');
Teste::igual(4, $porId[103]['games'], 'a dupla perdedora soma os games que fez');
Teste::igual(0, $porId[103]['vitorias'], 'quem perdeu nao soma vitoria');
Teste::igual(1, $porId[101]['jogadas'], 'conta a partida disputada');

Teste::igual(101, $linhas[0]['inscricao_id'], 'quem tem mais games fica em primeiro');

$naoEncerrada = [[
    'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
    'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
    'games_a' => null, 'games_b' => null, 'encerrada' => 0,
]];
$linhas = Placar::classificarLinhas($inscricoes, $naoEncerrada);
Teste::igual(0, $linhas[0]['games'], 'partida sem placar nao entra na soma');
Teste::igual(0, $linhas[0]['jogadas'], 'partida sem placar nao conta como disputada');

// Desempate por saldo: dois pares com 6 games cada, saldos diferentes.
// As partidas nao precisam formar um Super 8 valido. A funcao e pura e so soma o que recebe.
$oito = [];
foreach (range(1, 8) as $numero) {
    $oito[] = ['id' => 100 + $numero, 'nome_exibicao' => "Jogador {$numero}"];
}

$porSaldo = [
    [
        'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
        'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
        'games_a' => 6, 'games_b' => 2, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 105, 'dupla_a_j2' => 106,
        'dupla_b_j1' => 107, 'dupla_b_j2' => 108,
        'games_a' => 6, 'games_b' => 5, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($oito, $porSaldo);
$porId = array_column($linhas, null, 'inscricao_id');

Teste::igual(6, $porId[101]['games'], 'jogador 101 soma 6 games');
Teste::igual(6, $porId[105]['games'], 'jogador 105 soma os mesmos 6 games');
Teste::igual(4, $porId[101]['saldo'], 'jogador 101 fica com saldo 4');
Teste::igual(1, $porId[105]['saldo'], 'jogador 105 fica com saldo 1');
Teste::igual(101, $linhas[0]['inscricao_id'], 'com games iguais, o melhor saldo fica na frente');
Teste::igual(105, $linhas[2]['inscricao_id'], 'o par de saldo menor vem depois');

// Desempate por confronto direto: 101 e 103 empatam em games, saldo e vitorias,
// e 101 fez mais games contra 103 no confronto entre eles.
$dez = [];
foreach (range(1, 10) as $numero) {
    $dez[] = ['id' => 100 + $numero, 'nome_exibicao' => sprintf('Jogador %02d', $numero)];
}

$porConfronto = [
    [
        'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
        'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
        'games_a' => 6, 'games_b' => 3, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 101, 'dupla_a_j2' => 105,
        'dupla_b_j1' => 106, 'dupla_b_j2' => 107,
        'games_a' => 3, 'games_b' => 6, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 103, 'dupla_a_j2' => 108,
        'dupla_b_j1' => 109, 'dupla_b_j2' => 110,
        'games_a' => 6, 'games_b' => 3, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($dez, $porConfronto);
$porId = array_column($linhas, null, 'inscricao_id');

Teste::igual(9, $porId[101]['games'], 'jogador 101 soma 9 games');
Teste::igual(9, $porId[103]['games'], 'jogador 103 soma os mesmos 9 games');
Teste::igual(0, $porId[101]['saldo'], 'jogador 101 fica com saldo zero');
Teste::igual(0, $porId[103]['saldo'], 'jogador 103 fica com saldo zero');
Teste::igual(1, $porId[101]['vitorias'], 'jogador 101 tem 1 vitoria');
Teste::igual(1, $porId[103]['vitorias'], 'jogador 103 tem 1 vitoria');
Teste::igual(101, $linhas[0]['inscricao_id'], 'o confronto direto coloca 101 na frente');
Teste::igual(103, $linhas[1]['inscricao_id'], 'e 103 logo atras');
Teste::verdade(!$linhas[0]['empatado'], 'quem vence o confronto direto nao fica marcado como empate');

// Empate total fica sinalizado.
$espelho = [
    [
        'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
        'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
        'games_a' => 6, 'games_b' => 6, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($inscricoes, $espelho);
Teste::verdade($linhas[0]['empatado'], 'empate total fica sinalizado na linha');

// --- Cobertura adicional, alem do brief -----------------------------------
// Objetivo: pegar erros especificos de aritmetica que os testes acima nao
// cobrem: games creditados a dupla errada, partida "sofrida" nao contada,
// partida sem placar contada como jogada por engano, e a sequencia de
// desempate (games > saldo > vitorias > confronto > nome) aplicada fora de
// ordem.

// games_a preenchido mas encerrada = 0: pelo contrato (so conta com
// encerrada === 1 E os dois games presentes) isso NAO pode contar como
// jogada. Uma implementacao que checasse so "games preenchidos" (ignorando
// encerrada) passaria pelo teste de $naoEncerrada acima (que tem os dois
// games nulos) mas falharia aqui.
$encerradaZeroComGames = [[
    'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
    'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
    'games_a' => 6, 'games_b' => 3, 'encerrada' => 0,
]];
$linhas = Placar::classificarLinhas($inscricoes, $encerradaZeroComGames);
$porId = array_column($linhas, null, 'inscricao_id');
Teste::igual(0, $porId[101]['games'], 'placar preenchido mas nao encerrado nao conta na soma');
Teste::igual(0, $porId[101]['jogadas'], 'placar preenchido mas nao encerrado nao conta como jogada');

// encerrada = 1 mas um dos dois games ainda nulo (dado incompleto/corrompido):
// tambem nao pode contar, senao um NULL vira zero por engano e distorce o
// saldo de quem "venceu" contra um adversario fantasma.
$encerradaComGameFaltando = [[
    'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
    'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
    'games_a' => null, 'games_b' => 6, 'encerrada' => 1,
]];
$linhas = Placar::classificarLinhas($inscricoes, $encerradaComGameFaltando);
$porId = array_column($linhas, null, 'inscricao_id');
Teste::igual(0, $porId[103]['games'], 'encerrada=1 com um dos games nulo nao conta na soma');
Teste::igual(0, $porId[103]['jogadas'], 'encerrada=1 com um dos games nulo nao conta como jogada');

// Games nunca podem vazar para a dupla adversaria: verifica os 4 jogadores
// de uma unica partida assimetrica de uma vez, pegando uma troca dupla A
// dupla B (ou uma metade certa e a outra errada).
$assimetrica = [[
    'dupla_a_j1' => 101, 'dupla_a_j2' => 102,
    'dupla_b_j1' => 103, 'dupla_b_j2' => 104,
    'games_a' => 7, 'games_b' => 5, 'encerrada' => 1,
]];
$linhas = Placar::classificarLinhas($inscricoes, $assimetrica);
$porId = array_column($linhas, null, 'inscricao_id');
Teste::igual(7, $porId[101]['games'], 'dupla A j1 recebe os games da dupla A');
Teste::igual(7, $porId[102]['games'], 'dupla A j2 recebe os games da dupla A');
Teste::igual(5, $porId[103]['games'], 'dupla B j1 recebe os games da dupla B, nunca os da dupla A');
Teste::igual(5, $porId[104]['games'], 'dupla B j2 recebe os games da dupla B, nunca os da dupla A');
Teste::igual(5, $porId[101]['sofridos'], 'dupla A sofre os games da dupla B');
Teste::igual(7, $porId[103]['sofridos'], 'dupla B sofre os games da dupla A');

// Precedencia games > saldo: um jogador com mais games totais, mas saldo
// pior, ainda tem que ficar na frente de quem tem menos games e saldo
// melhor. Se o comparador olhasse saldo antes de games (ou os dois com o
// mesmo peso), esta ordem se inverteria.
$dezesseis = [];
foreach (range(1, 16) as $numero) {
    $dezesseis[] = ['id' => 200 + $numero, 'nome_exibicao' => sprintf('Jogador %02d', $numero)];
}
$porGamesAntesDeSaldo = [
    // 201/202 fazem 10 games no total (jogo unico), saldo +1 (o pior dos dois).
    [
        'dupla_a_j1' => 201, 'dupla_a_j2' => 202,
        'dupla_b_j1' => 203, 'dupla_b_j2' => 204,
        'games_a' => 10, 'games_b' => 9, 'encerrada' => 1,
    ],
    // 205/206 fazem so 6 games no total (jogo unico), mas com saldo +5, bem melhor.
    [
        'dupla_a_j1' => 205, 'dupla_a_j2' => 206,
        'dupla_b_j1' => 207, 'dupla_b_j2' => 208,
        'games_a' => 6, 'games_b' => 1, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($dezesseis, $porGamesAntesDeSaldo);
$porId = array_column($linhas, null, 'inscricao_id');
Teste::igual(10, $porId[201]['games'], 'jogador 201 soma 10 games');
Teste::igual(1, $porId[201]['saldo'], 'jogador 201 fica com saldo baixo');
Teste::igual(6, $porId[205]['games'], 'jogador 205 soma so 6 games');
Teste::igual(5, $porId[205]['saldo'], 'jogador 205 fica com saldo bem melhor que o de 201');
Teste::igual(201, $linhas[0]['inscricao_id'], 'mais games vence mesmo com saldo pior (games decide antes do saldo)');

// Precedencia saldo > vitorias: dois jogadores empatam em games e saldo
// totais, mas tem numero de vitorias diferente (um venceu 1 de 2 jogos por
// margem alta, o outro empatou os 2). O desempate tem que usar vitorias
// aqui, ja que games e saldo nao decidem.
// Cada jogador troca de parceiro entre as duas partidas (como no teste de
// confronto direto do brief), de proposito: se 301 e 303 jogassem as duas
// partidas sempre com o mesmo parceiro, esse parceiro terminaria com os
// EXATOS mesmos numeros (games, saldo, vitorias e confronto identicos, ja
// que dupla soma igual para os dois lados), o que marcaria os dois como
// empatados entre si e junto seria mais dificil isolar a comparacao entre
// 301 e 303 que este teste quer provar.
$catorze = [];
foreach ([301, 303, 401, 402, 403, 404, 305, 306, 307, 308, 309, 310, 311, 312] as $id) {
    $catorze[] = ['id' => $id, 'nome_exibicao' => "Jogador {$id}"];
}
$porVitorias = [
    // 301 empata duas vezes, com parceiros diferentes: 12 games, 12
    // sofridos, saldo 0, 0 vitorias.
    [
        'dupla_a_j1' => 301, 'dupla_a_j2' => 401,
        'dupla_b_j1' => 305, 'dupla_b_j2' => 306,
        'games_a' => 6, 'games_b' => 6, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 301, 'dupla_a_j2' => 402,
        'dupla_b_j1' => 307, 'dupla_b_j2' => 308,
        'games_a' => 6, 'games_b' => 6, 'encerrada' => 1,
    ],
    // 303 vence uma vez por goleada e perde outra pelo mesmo tanto, tambem
    // com parceiros diferentes: 12 games, 12 sofridos, saldo 0, 1 vitoria.
    [
        'dupla_a_j1' => 303, 'dupla_a_j2' => 403,
        'dupla_b_j1' => 309, 'dupla_b_j2' => 310,
        'games_a' => 9, 'games_b' => 3, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 303, 'dupla_a_j2' => 404,
        'dupla_b_j1' => 311, 'dupla_b_j2' => 312,
        'games_a' => 3, 'games_b' => 9, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($catorze, $porVitorias);
$porId = array_column($linhas, null, 'inscricao_id');
Teste::igual($porId[301]['games'], $porId[303]['games'], 'jogador 301 e 303 empatam em games totais (12)');
Teste::igual($porId[301]['saldo'], $porId[303]['saldo'], 'jogador 301 e 303 empatam em saldo total (0)');
Teste::igual(0, $porId[301]['vitorias'], 'jogador 301 nao venceu nenhum jogo (dois empates)');
Teste::igual(1, $porId[303]['vitorias'], 'jogador 303 venceu 1 jogo');
Teste::verdade(
    array_search(303, array_column($linhas, 'inscricao_id'), true)
        < array_search(301, array_column($linhas, 'inscricao_id'), true),
    'com games e saldo empatados, quem tem mais vitorias (303) fica na frente de quem nao venceu nenhuma (301)'
);
Teste::verdade(!$porId[301]['empatado'], '301 nao fica marcado como empatado: a diferenca de vitorias contra 303 desempata');
Teste::verdade(!$porId[303]['empatado'], '303 nao fica marcado como empatado: a diferenca de vitorias contra 301 desempata');

// Precedencia saldo > vitorias, de verdade: ate aqui nenhum teste prova que
// saldo decide ANTES de vitorias quando os dois discordam sobre quem fica na
// frente. O teste de desempate por vitorias, acima, tem saldo empatado entre
// 301 e 303, entao uma implementacao que comparasse vitorias antes do saldo
// passaria por ele do mesmo jeito (301 e 303 tem saldo igual, a ordem entre
// os dois criterios nao importa quando um deles ja empata). Aqui os dois
// discordam: 501 tem saldo bem melhor mas so 1 vitoria (um jogo só, goleada);
// 505 tem saldo pior mas 2 vitorias (dois jogos apertados). Games empatados
// em 6 para os tres. Pela ordem certa (saldo antes de vitorias), 501 (e seu
// parceiro 502, que jogou so essa partida e por isso fica com os mesmos
// numeros) ficam na frente de 505, mesmo com menos vitorias.
$dezoito = [];
foreach ([501, 502, 503, 504, 505, 601, 602, 506, 507, 508, 509] as $id) {
    $dezoito[] = ['id' => $id, 'nome_exibicao' => "Jogador {$id}"];
}
$porSaldoAntesDeVitorias = [
    // 501/502: 1 jogo, goleada. games 6, saldo +5, 1 vitoria.
    [
        'dupla_a_j1' => 501, 'dupla_a_j2' => 502,
        'dupla_b_j1' => 503, 'dupla_b_j2' => 504,
        'games_a' => 6, 'games_b' => 1, 'encerrada' => 1,
    ],
    // 505: 2 jogos apertados, parceiros diferentes em cada um. games 6 no
    // total, saldo so +2, mas 2 vitorias.
    [
        'dupla_a_j1' => 505, 'dupla_a_j2' => 601,
        'dupla_b_j1' => 506, 'dupla_b_j2' => 507,
        'games_a' => 4, 'games_b' => 3, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 505, 'dupla_a_j2' => 602,
        'dupla_b_j1' => 508, 'dupla_b_j2' => 509,
        'games_a' => 2, 'games_b' => 1, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($dezoito, $porSaldoAntesDeVitorias);
$porId = array_column($linhas, null, 'inscricao_id');

Teste::igual(6, $porId[501]['games'], '501 soma 6 games');
Teste::igual(5, $porId[501]['saldo'], '501 fica com saldo +5');
Teste::igual(1, $porId[501]['vitorias'], '501 tem so 1 vitoria');
Teste::igual(6, $porId[505]['games'], '505 tambem soma 6 games, empatado com 501');
Teste::igual(2, $porId[505]['saldo'], '505 fica com saldo +2, pior que o de 501');
Teste::igual(2, $porId[505]['vitorias'], '505 tem 2 vitorias, mais que 501');

$ids = array_column($linhas, 'inscricao_id');
Teste::verdade(
    array_search(501, $ids, true) < array_search(505, $ids, true),
    'com games empatados, quem tem o melhor saldo (501) fica na frente de quem tem mais vitorias mas saldo pior (505): saldo decide antes de vitorias'
);
Teste::verdade(
    array_search(502, $ids, true) < array_search(505, $ids, true),
    'o parceiro 502 (mesmo saldo de 501, so jogou aquela partida) tambem fica na frente de 505'
);

exit(Teste::resumo());
