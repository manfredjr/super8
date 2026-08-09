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
// discordam: 501 tem saldo bem melhor mas so 1 vitoria (um jogo so, goleada);
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

// Precedencia vitorias > confronto direto, de verdade: os testes acima de
// confronto direto (o do brief e os de cima) sempre tem vitorias JA
// empatadas entre os dois jogadores comparados, entao uma implementacao que
// comparasse confronto direto antes de vitorias passaria por eles do mesmo
// jeito. Aqui os dois discordam de proposito: 701 vence o confronto direto
// contra 705 por goleada (10-2), mas 705 tem mais vitorias no total da
// carreira (2 contra 1). Games e saldo empatados em 12/0 para os dois. Pela
// ordem certa (vitorias antes de confronto), 705 fica na frente de 701,
// mesmo perdendo o confronto direto entre eles.
$vinteUm = [];
foreach ([701, 705, 702, 703, 706, 707, 708, 709, 710, 711, 712, 713, 714] as $id) {
    $vinteUm[] = ['id' => $id, 'nome_exibicao' => "Jogador {$id}"];
}
$porConfrontoAntesDeVitorias = [
    // Confronto direto entre 701 e 705: 701 goleia.
    [
        'dupla_a_j1' => 701, 'dupla_a_j2' => 702,
        'dupla_b_j1' => 705, 'dupla_b_j2' => 706,
        'games_a' => 10, 'games_b' => 2, 'encerrada' => 1,
    ],
    // 701 perde a segunda partida (parceiro e adversarios diferentes, nunca
    // encontra 705 de novo): fecha games=12, saldo=0, so 1 vitoria no total.
    [
        'dupla_a_j1' => 701, 'dupla_a_j2' => 703,
        'dupla_b_j1' => 709, 'dupla_b_j2' => 710,
        'games_a' => 2, 'games_b' => 10, 'encerrada' => 1,
    ],
    // 705 vence duas partidas apertadas contra adversarios diferentes
    // (nunca contra 701 de novo): fecha games=12, saldo=0, 2 vitorias no
    // total - mais que 701, apesar de ter perdido o confronto direto.
    [
        'dupla_a_j1' => 705, 'dupla_a_j2' => 707,
        'dupla_b_j1' => 711, 'dupla_b_j2' => 712,
        'games_a' => 5, 'games_b' => 1, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 705, 'dupla_a_j2' => 708,
        'dupla_b_j1' => 713, 'dupla_b_j2' => 714,
        'games_a' => 5, 'games_b' => 1, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($vinteUm, $porConfrontoAntesDeVitorias);
$porId = array_column($linhas, null, 'inscricao_id');

Teste::igual(12, $porId[701]['games'], '701 soma 12 games');
Teste::igual(0, $porId[701]['saldo'], '701 fica com saldo zero');
Teste::igual(1, $porId[701]['vitorias'], '701 tem so 1 vitoria no total');
Teste::igual(12, $porId[705]['games'], '705 tambem soma 12 games, empatado com 701');
Teste::igual(0, $porId[705]['saldo'], '705 tambem fica com saldo zero');
Teste::igual(2, $porId[705]['vitorias'], '705 tem 2 vitorias no total, mais que 701');

$ids = array_column($linhas, 'inscricao_id');
Teste::verdade(
    array_search(705, $ids, true) < array_search(701, $ids, true),
    'com games e saldo empatados, quem tem mais vitorias no total (705) fica na frente de quem venceu o confronto direto mas tem menos vitorias (701): vitorias decide antes do confronto direto'
);

// Precedencia confronto direto > nome, de verdade: o teste de confronto
// direto do brief usa os ids 101 e 103, nomeados "Jogador 01" e "Jogador
// 03" - e 101 (que vence o confronto) tambem vem primeiro por strcmp, entao
// aquele teste nao prova que o confronto decide ANTES do nome: uma
// implementacao que comparasse nome antes de confronto direto passaria por
// ele do mesmo jeito, so por coincidencia alfabetica. Aqui os nomes vao na
// direcao CONTRARIA ao confronto de proposito: "Jogador Zulu" vence o
// confronto direto contra "Jogador Alfa", mas "Alfa" vem antes de "Zulu"
// por ordem alfabetica. Games, saldo e vitorias empatados em 9/0/1 para os
// dois. Pela ordem certa (confronto antes de nome), Zulu fica na frente de
// Alfa, apesar do nome.
$vinteQuatro = [];
foreach ([900, 901, 902, 903, 904, 906, 907, 908, 909, 910] as $id) {
    $vinteQuatro[] = ['id' => $id, 'nome_exibicao' => "Jogador {$id}"];
}
// Sobrescreve so os nomes de 900 e 901, que sao os unicos que este teste
// examina.
foreach ($vinteQuatro as &$linhaInscricao) {
    if ($linhaInscricao['id'] === 900) {
        $linhaInscricao['nome_exibicao'] = 'Jogador Zulu';
    } elseif ($linhaInscricao['id'] === 901) {
        $linhaInscricao['nome_exibicao'] = 'Jogador Alfa';
    }
}
unset($linhaInscricao);

Teste::verdade(strcmp('Jogador Alfa', 'Jogador Zulu') < 0, 'checagem do proprio teste: "Alfa" vem antes de "Zulu" por ordem alfabetica');

$porNomeContraConfronto = [
    // Confronto direto entre 900 (Zulu) e 901 (Alfa): Zulu vence.
    [
        'dupla_a_j1' => 900, 'dupla_a_j2' => 902,
        'dupla_b_j1' => 901, 'dupla_b_j2' => 903,
        'games_a' => 6, 'games_b' => 3, 'encerrada' => 1,
    ],
    // 900 (Zulu) perde a segunda partida, contra adversarios diferentes:
    // fecha games=9, saldo=0, 1 vitoria.
    [
        'dupla_a_j1' => 900, 'dupla_a_j2' => 904,
        'dupla_b_j1' => 906, 'dupla_b_j2' => 907,
        'games_a' => 3, 'games_b' => 6, 'encerrada' => 1,
    ],
    // 901 (Alfa) vence a segunda partida, contra adversarios diferentes:
    // fecha games=9, saldo=0, 1 vitoria - empatado com 900 em tudo, menos
    // no confronto direto.
    [
        'dupla_a_j1' => 901, 'dupla_a_j2' => 908,
        'dupla_b_j1' => 909, 'dupla_b_j2' => 910,
        'games_a' => 6, 'games_b' => 3, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($vinteQuatro, $porNomeContraConfronto);
$porId = array_column($linhas, null, 'inscricao_id');

Teste::igual(9, $porId[900]['games'], '900 (Zulu) soma 9 games');
Teste::igual(0, $porId[900]['saldo'], '900 (Zulu) fica com saldo zero');
Teste::igual(1, $porId[900]['vitorias'], '900 (Zulu) tem 1 vitoria');
Teste::igual(9, $porId[901]['games'], '901 (Alfa) tambem soma 9 games');
Teste::igual(0, $porId[901]['saldo'], '901 (Alfa) tambem fica com saldo zero');
Teste::igual(1, $porId[901]['vitorias'], '901 (Alfa) tambem tem 1 vitoria');

$ids = array_column($linhas, 'inscricao_id');
Teste::verdade(
    array_search(900, $ids, true) < array_search(901, $ids, true),
    'com games, saldo e vitorias empatados, quem vence o confronto direto (900, "Zulu") fica na frente de quem perde (901, "Alfa"), mesmo com o nome em desvantagem alfabetica: confronto decide antes do nome'
);

// --- empatado com 3 ou mais jogadores: ciclo nao-transitivo ---------------
// A marca de empate so olhando pares (equal-pair) nao pega um CICLO: A bate
// B, B bate C e C bate A no confronto direto, com os tres empatados em
// games/saldo/vitorias. Toda comparacao de PAR e par tem um vencedor
// decisivo (nenhum par empata), entao uma implementacao que so verificasse
// pares marcaria os tres como NAO empatados e deixaria o usort() (que so
// compara dois a dois, sem enxergar o ciclo) inventar uma ordem que muda so
// pela ordem de entrada dos jogadores - exatamente o problema que a coluna
// empatado existe para evitar.
$grupoCiclo = [];
foreach ([1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009] as $id) {
    $grupoCiclo[] = ['id' => $id, 'nome_exibicao' => "Jogador {$id}"];
}
$partidasCiclo = [
    // 1001 bate 1002 no confronto direto.
    [
        'dupla_a_j1' => 1001, 'dupla_a_j2' => 1004,
        'dupla_b_j1' => 1002, 'dupla_b_j2' => 1006,
        'games_a' => 5, 'games_b' => 3, 'encerrada' => 1,
    ],
    // 1002 bate 1003 no confronto direto.
    [
        'dupla_a_j1' => 1002, 'dupla_a_j2' => 1007,
        'dupla_b_j1' => 1003, 'dupla_b_j2' => 1008,
        'games_a' => 5, 'games_b' => 3, 'encerrada' => 1,
    ],
    // 1003 bate 1001 no confronto direto - fecha o ciclo.
    [
        'dupla_a_j1' => 1003, 'dupla_a_j2' => 1009,
        'dupla_b_j1' => 1001, 'dupla_b_j2' => 1005,
        'games_a' => 5, 'games_b' => 3, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($grupoCiclo, $partidasCiclo);
$porId = array_column($linhas, null, 'inscricao_id');

foreach ([1001, 1002, 1003] as $id) {
    Teste::igual(8, $porId[$id]['games'], "jogador {$id} soma 8 games (ciclo)");
    Teste::igual(0, $porId[$id]['saldo'], "jogador {$id} fica com saldo zero (ciclo)");
    Teste::igual(1, $porId[$id]['vitorias'], "jogador {$id} tem 1 vitoria (ciclo)");
}
Teste::verdade($porId[1001]['empatado'], '1001 fica marcado como empatado dentro do ciclo (bate 1002, mas perde para 1003)');
Teste::verdade($porId[1002]['empatado'], '1002 fica marcado como empatado dentro do ciclo (bate 1003, mas perde para 1001)');
Teste::verdade($porId[1003]['empatado'], '1003 fica marcado como empatado dentro do ciclo (bate 1001, mas perde para 1002)');

// chave_grupo (achado "Menor 4" da rodada de revisao da tarefa 14): a view
// de classificacao usa este campo, em vez de remontar "games|saldo|vitorias"
// por conta propria, para saber quando repetir a posicao de um grupo
// empatado. Os tres do ciclo compartilham a mesma chave; quem nao esta no
// ciclo (games/saldo/vitorias diferentes) tem uma chave diferente.
Teste::igual($porId[1001]['chave_grupo'], $porId[1002]['chave_grupo'], 'chave_grupo: os tres do ciclo compartilham a mesma chave (1001 e 1002)');
Teste::igual($porId[1001]['chave_grupo'], $porId[1003]['chave_grupo'], 'chave_grupo: os tres do ciclo compartilham a mesma chave (1001 e 1003)');
Teste::igual('8|0|1', $porId[1001]['chave_grupo'], 'chave_grupo segue o formato games|saldo|vitorias');
Teste::verdade(
    $porId[1001]['chave_grupo'] !== $porId[1004]['chave_grupo'],
    'chave_grupo: quem tem games/saldo/vitorias diferentes (1004, que so joga 1 partida) fica com uma chave diferente da do ciclo'
);

// --- empatado com 3 ou mais jogadores: confronto ORDENA o grupo (nenhum
// fica marcado) -------------------------------------------------------------
// O contrario do ciclo acima: um grupo de 3 jogadores empatados em
// games/saldo/vitorias onde o confronto direto ordena o grupo INTEIRO de
// forma estrita (2001 bate os outros dois, 2002 bate so 2003, 2003 nao bate
// ninguem do grupo) tem que ficar SEM a marca de empate para os tres. Este
// teste importa tanto quanto o do ciclo: sem ele, uma implementacao que
// marcasse QUALQUER grupo de 3+ como empatado (em vez de checar se o
// confronto realmente ordena) passaria pelo teste do ciclo e por este ao
// mesmo tempo passaria por "sorte", nunca provando que o desempate por
// confronto continua funcionando quando ele de fato decide.
// Os nomes de 2001/2002/2003 sao escolhidos DE PROPOSITO para discordar do
// confronto direto: 2001 vence o confronto (bate os outros dois) mas recebe
// o nome que vem POR ULTIMO por ordem alfabetica ("Zulu"); 2003 nao vence
// ninguem no confronto mas recebe o nome que vem PRIMEIRO ("Alfa"). Com os
// ids "Jogador 2001/2002/2003" originais, a ordem alfabetica (2001 < 2002 <
// 2003) coincidia por acaso com a ordem do confronto, entao esta asserticao
// passaria mesmo com o bloco de confronto inteiro apagado do comparador -
// exatamente a armadilha que o achado Important 1 da revisao apontou. Com
// os nomes invertidos, so o confronto direto pode produzir esta ordem.
$grupoOrdenado = [];
foreach ([2001, 2002, 2003, 2010, 2011, 2012, 2013, 2014, 2015, 2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027] as $id) {
    $grupoOrdenado[] = ['id' => $id, 'nome_exibicao' => "Jogador {$id}"];
}
foreach ($grupoOrdenado as &$linhaGrupoOrdenado) {
    if ($linhaGrupoOrdenado['id'] === 2001) {
        $linhaGrupoOrdenado['nome_exibicao'] = 'Jogador Zulu';
    } elseif ($linhaGrupoOrdenado['id'] === 2002) {
        $linhaGrupoOrdenado['nome_exibicao'] = 'Jogador Meio';
    } elseif ($linhaGrupoOrdenado['id'] === 2003) {
        $linhaGrupoOrdenado['nome_exibicao'] = 'Jogador Alfa';
    }
}
unset($linhaGrupoOrdenado);
$partidasGrupoOrdenado = [
    // 2001 bate 2002 no confronto direto.
    [
        'dupla_a_j1' => 2001, 'dupla_a_j2' => 2010,
        'dupla_b_j1' => 2002, 'dupla_b_j2' => 2011,
        'games_a' => 5, 'games_b' => 3, 'encerrada' => 1,
    ],
    // 2001 bate 2003 no confronto direto (2001 bate os dois outros do grupo).
    [
        'dupla_a_j1' => 2001, 'dupla_a_j2' => 2012,
        'dupla_b_j1' => 2003, 'dupla_b_j2' => 2013,
        'games_a' => 5, 'games_b' => 3, 'encerrada' => 1,
    ],
    // 2002 bate 2003 no confronto direto (2002 bate so 2003; 2003 nao bate ninguem do grupo).
    [
        'dupla_a_j1' => 2002, 'dupla_a_j2' => 2014,
        'dupla_b_j1' => 2003, 'dupla_b_j2' => 2015,
        'games_a' => 5, 'games_b' => 3, 'encerrada' => 1,
    ],
    // Partidas extras (fora do grupo) so para igualar games/saldo/vitorias
    // dos tres em 14/10/+4/2 vitorias, sem criar nenhum novo confronto entre
    // 2001, 2002 e 2003 entre si.
    [
        'dupla_a_j1' => 2001, 'dupla_a_j2' => 2016,
        'dupla_b_j1' => 2020, 'dupla_b_j2' => 2021,
        'games_a' => 4, 'games_b' => 4, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 2002, 'dupla_a_j2' => 2017,
        'dupla_b_j1' => 2022, 'dupla_b_j2' => 2023,
        'games_a' => 6, 'games_b' => 2, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 2003, 'dupla_a_j2' => 2018,
        'dupla_b_j1' => 2024, 'dupla_b_j2' => 2025,
        'games_a' => 5, 'games_b' => 0, 'encerrada' => 1,
    ],
    [
        'dupla_a_j1' => 2003, 'dupla_a_j2' => 2019,
        'dupla_b_j1' => 2026, 'dupla_b_j2' => 2027,
        'games_a' => 3, 'games_b' => 0, 'encerrada' => 1,
    ],
];
$linhas = Placar::classificarLinhas($grupoOrdenado, $partidasGrupoOrdenado);
$porId = array_column($linhas, null, 'inscricao_id');

foreach ([2001, 2002, 2003] as $id) {
    Teste::igual(14, $porId[$id]['games'], "jogador {$id} soma 14 games (grupo ordenado)");
    Teste::igual(4, $porId[$id]['saldo'], "jogador {$id} fica com saldo +4 (grupo ordenado)");
    Teste::igual(2, $porId[$id]['vitorias'], "jogador {$id} tem 2 vitorias (grupo ordenado)");
}

$ids = array_column($linhas, 'inscricao_id');
Teste::verdade(
    strcmp('Jogador Zulu', 'Jogador Meio') > 0 && strcmp('Jogador Meio', 'Jogador Alfa') > 0,
    'checagem do proprio teste: por nome, a ordem seria o CONTRARIO da ordem esperada por confronto (Alfa, Meio, Zulu) - se esta asserticao falhar, o teste abaixo nao prova mais nada'
);
Teste::verdade(
    array_search(2001, $ids, true) < array_search(2002, $ids, true)
        && array_search(2002, $ids, true) < array_search(2003, $ids, true),
    'o confronto direto ordena o grupo inteiro (2001 "Zulu", depois 2002 "Meio", depois 2003 "Alfa") mesmo com os nomes na ordem alfabetica contraria: so o confronto pode ter produzido esta ordem'
);
Teste::verdade(!$porId[2001]['empatado'], '2001 nao fica marcado como empatado: o confronto direto ordena o grupo inteiro');
Teste::verdade(!$porId[2002]['empatado'], '2002 nao fica marcado como empatado: o confronto direto ordena o grupo inteiro');
Teste::verdade(!$porId[2003]['empatado'], '2003 nao fica marcado como empatado: o confronto direto ordena o grupo inteiro');

exit(Teste::resumo());
