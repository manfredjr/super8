<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';
require __DIR__ . '/../src/Placar.php';
require __DIR__ . '/../src/Ranking.php';

echo "Ranking\n";

$pdo = db();
$pdo->beginTransaction();

$sufixo = random_int(1000, 9999);
$organizadorId = Auth::cadastrar($pdo, 'Organizador', "orgrank{$sufixo}@exemplo.com", 'senhaforte123');

$jogadorId = Auth::cadastrar($pdo, 'Jogador Com Conta', "jog{$sufixo}@exemplo.com", 'senhaforte123');

$campeonatoId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa 1', 'data_evento' => '2026-05-10',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);

Campeonato::inscrever($pdo, $campeonatoId, 'Jogador Com Conta', $jogadorId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoId, "Convidado {$numero}", null);
}

Campeonato::sortear($pdo, $campeonatoId, 777);

$buscaPartidas = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidas->execute([$campeonatoId]);
$partidas = $buscaPartidas->fetchAll(PDO::FETCH_COLUMN);

foreach ($partidas as $partidaId) {
    Placar::gravar($pdo, $campeonatoId, (int) $partidaId, 6, 3, $organizadorId);
}

// C1 (Critico, rodada de revisao): este e o unico evento deste arquivo que
// chega ao estado encerrado usando so a API publica, sem UPDATE manual - a
// prova de que Campeonato::encerrar e o que faz um evento passar a contar
// no ranking acumulado. Os outros campeonatos deste arquivo continuam
// forjando o status direto no banco, porque testam o FILTRO de
// Ranking::acumulado, nao a transicao em si.
Campeonato::encerrar($pdo, $campeonatoId);

$linhas = Ranking::acumulado($pdo, null, null);
$nossa = null;
foreach ($linhas as $linha) {
    if ((int) $linha['jogador_id'] === $jogadorId) {
        $nossa = $linha;
    }
}

Teste::verdade($nossa !== null, 'o jogador com conta aparece no ranking');
Teste::igual(1, (int) $nossa['eventos'], 'conta 1 evento disputado');
Teste::igual(7, (int) $nossa['jogadas'], 'conta as 7 partidas do Super 8');
Teste::verdade((int) $nossa['games'] > 0, 'soma os games do evento');

$foraDoPeriodo = Ranking::acumulado($pdo, '2026-06-01', '2026-06-30');
$achou = false;
foreach ($foraDoPeriodo as $linha) {
    if ((int) $linha['jogador_id'] === $jogadorId) {
        $achou = true;
    }
}
Teste::verdade(!$achou, 'o filtro de periodo exclui evento de fora da janela');

// ============================================================================
// Testes adicionais, alem do que o brief pede.
//
// Todo task anterior deste projeto teve algum teste que passava contra
// codigo quebrado. Aqui os perigos especificos sao: uma JOIN que multiplica
// linha (inflaria os totais de forma consistente e ainda pareceria
// ordenada certo), o games creditado no lado errado da dupla (daria numeros
// plausiveis) e um filtro de periodo que silenciosamente casa com tudo. As
// checagens abaixo usam contas feitas a mao a partir de dados crus (o
// proprio Rodizio::RODADAS, que e tabela fixa do projeto, e nao passa pelo
// SQL de Ranking::acumulado em nenhum momento), nunca so "maior que zero".
// ============================================================================

// Devolve, para uma posicao de sorteio (1 a 8), em que rodadas ela jogou na
// dupla A e em que rodadas jogou na dupla B - direto de Rodizio::RODADAS,
// sem depender de nada que Ranking::acumulado calcule. Como cada posicao
// joga exatamente 7 rodadas (uma por rodada) e 7 e impar, a contagem de
// rodadas como dupla A nunca pode empatar com a contagem como dupla B
// (0-7, 1-6, 2-5 ou 3-4): por isso um placar com lados bem diferentes
// sempre desmascara um CASE que credita o lado errado, nao importa a
// posicao sorteada.
function ladoPorRodada(int $posicao): array
{
    $lados = [];
    foreach (Rodizio::RODADAS as $numero => $partidasDaRodada) {
        foreach ($partidasDaRodada as $partida) {
            [$duplaA, $duplaB] = $partida;
            if (in_array($posicao, $duplaA, true)) {
                $lados[$numero] = 'A';
            } elseif (in_array($posicao, $duplaB, true)) {
                $lados[$numero] = 'B';
            }
        }
    }
    return $lados;
}

function contemJogador(array $linhas, int $jogadorId): bool
{
    foreach ($linhas as $linha) {
        if ((int) $linha['jogador_id'] === $jogadorId) {
            return true;
        }
    }
    return false;
}

// --- Conta a mao o games/sofridos exatos do evento 1 ------------------------
// O evento 1 gravou 6-3 (dupla A - dupla B) em TODAS as 14 partidas (loop
// acima), entao o total certo depende so de quantas rodadas a posicao
// sorteada do jogador jogou de cada lado.
$buscaInscricaoJogador = $pdo->prepare(
    'SELECT posicao_sorteio FROM inscricoes WHERE campeonato_id = ? AND jogador_id = ?'
);
$buscaInscricaoJogador->execute([$campeonatoId, $jogadorId]);
$posicaoJogadorEvento1 = (int) $buscaInscricaoJogador->fetchColumn();

$ladosEvento1 = ladoPorRodada($posicaoJogadorEvento1);
Teste::igual(7, count($ladosEvento1), 'a posicao sorteada do jogador com conta jogou as 7 rodadas do evento 1');

$contagemEvento1 = array_count_values($ladosEvento1);
$comoAEvento1 = $contagemEvento1['A'] ?? 0;
$comoBEvento1 = $contagemEvento1['B'] ?? 0;
$gamesEsperadoEvento1 = $comoAEvento1 * 6 + $comoBEvento1 * 3;
$sofridosEsperadoEvento1 = $comoAEvento1 * 3 + $comoBEvento1 * 6;

Teste::igual(
    $gamesEsperadoEvento1,
    (int) $nossa['games'],
    'games do evento 1 batem com a conta a mao (nao so "maior que zero"): pega JOIN duplicando linha e lado trocado'
);
Teste::igual(
    $sofridosEsperadoEvento1,
    (int) $nossa['sofridos'],
    'sofridos do evento 1 batem com a conta a mao pelo lado certo da dupla'
);
Teste::igual(
    $gamesEsperadoEvento1 - $sofridosEsperadoEvento1,
    (int) $nossa['saldo'],
    'saldo do evento 1 e games menos sofridos'
);
Teste::igual(
    round($gamesEsperadoEvento1 / 1, 1),
    (float) $nossa['media'],
    'media do evento 1 (1 unico evento) e o proprio total de games'
);

// --- Torneio nao encerrado nao pode vazar para o ranking ---------------------
$jogadorNaoEncerradoId = Auth::cadastrar($pdo, 'Jogador Nao Encerrado', "jognaoenc{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoNaoEncerradoId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Nao Encerrada', 'data_evento' => '2026-05-15',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoNaoEncerradoId, 'Jogador Nao Encerrado', $jogadorNaoEncerradoId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoNaoEncerradoId, "Convidado NE {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoNaoEncerradoId, 4242);

$buscaPartidasNE = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidasNE->execute([$campeonatoNaoEncerradoId]);
foreach ($buscaPartidasNE->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    Placar::gravar($pdo, $campeonatoNaoEncerradoId, (int) $partidaId, 6, 0, $organizadorId);
}
// De proposito NAO atualiza o status para 'encerrado': continua 'sorteado',
// mesmo com todas as 14 partidas ja com placar lancado.

$linhasSemFiltro = Ranking::acumulado($pdo, null, null);
Teste::verdade(
    !contemJogador($linhasSemFiltro, $jogadorNaoEncerradoId),
    'um campeonato que nao esta encerrado nao aparece no ranking, mesmo com todas as partidas ja com placar lancado'
);

// --- p.encerrada = 1: uma partida sem placar dentro de um evento ENCERRADO
// nao pode contar como jogada, nem deixar SUM() ignorar o games nulo dela
// em silencio -----------------------------------------------------------
// Sem este filtro, os 4 jogadores da partida sem placar aparecem com 7
// jogadas (o COUNT(*) da JOIN nao se importa se games_a/games_b sao nulos)
// mas o SUM() pula o NULL sozinho - o total de games fica igual ao de
// quem jogou todas as 7, mas o numero de partidas mentindo diz que houve
// uma partida "de graca". E o defeito mais traicoeiro dos tres: continua
// parecendo um ranking plausivel.
function quadraDaRodada(int $numero, int $posicao): int
{
    foreach (Rodizio::RODADAS[$numero] as $indice => $partida) {
        [$duplaA, $duplaB] = $partida;
        if (in_array($posicao, $duplaA, true) || in_array($posicao, $duplaB, true)) {
            return $indice;
        }
    }
    throw new RuntimeException('posicao nao encontrada na rodada informada');
}

$jogadorParcialDentroId = Auth::cadastrar($pdo, 'Jogador Parcial Dentro', "jogpardentro{$sufixo}@exemplo.com", 'senhaforte123');
$jogadorParcialForaId = Auth::cadastrar($pdo, 'Jogador Parcial Fora', "jogparfora{$sufixo}@exemplo.com", 'senhaforte123');

$campeonatoParcialId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Parcial', 'data_evento' => '2026-04-10',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoParcialId, 'Jogador Parcial Dentro', $jogadorParcialDentroId);
Campeonato::inscrever($pdo, $campeonatoParcialId, 'Jogador Parcial Fora', $jogadorParcialForaId);
foreach (range(3, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoParcialId, "Convidado Parcial {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoParcialId, 5050);

$buscaPosParcial = $pdo->prepare('SELECT jogador_id, posicao_sorteio FROM inscricoes WHERE campeonato_id = ?');
$buscaPosParcial->execute([$campeonatoParcialId]);
$posicaoPorJogadorParcial = [];
foreach ($buscaPosParcial->fetchAll() as $linhaInsc) {
    if ($linhaInsc['jogador_id'] !== null) {
        $posicaoPorJogadorParcial[(int) $linhaInsc['jogador_id']] = (int) $linhaInsc['posicao_sorteio'];
    }
}
$posDentro = $posicaoPorJogadorParcial[$jogadorParcialDentroId];
$posFora = $posicaoPorJogadorParcial[$jogadorParcialForaId];

// Procura uma rodada onde os dois caem em partidas (quadras) diferentes,
// para poder deixar SO a partida do "dentro" sem placar sem tocar na do
// "fora" de jeito nenhum.
$rodadaEscolhida = null;
foreach (range(1, 7) as $numero) {
    if (quadraDaRodada($numero, $posDentro) !== quadraDaRodada($numero, $posFora)) {
        $rodadaEscolhida = $numero;
        break;
    }
}
Teste::verdade($rodadaEscolhida !== null, 'existe uma rodada onde os dois jogadores parciais caem em partidas (quadras) diferentes');

$quadraEscolhida = quadraDaRodada($rodadaEscolhida, $posDentro) + 1;
$buscaPartidaAlvo = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id
     WHERE r.campeonato_id = ? AND r.numero = ? AND p.quadra = ?'
);
$buscaPartidaAlvo->execute([$campeonatoParcialId, $rodadaEscolhida, $quadraEscolhida]);
$idPartidaSemPlacar = (int) $buscaPartidaAlvo->fetchColumn();

$buscaTodasPartidasParcial = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaTodasPartidasParcial->execute([$campeonatoParcialId]);
foreach ($buscaTodasPartidasParcial->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    if ((int) $partidaId === $idPartidaSemPlacar) {
        continue; // fica de proposito sem placar (encerrada = 0)
    }
    Placar::gravar($pdo, $campeonatoParcialId, (int) $partidaId, 5, 2, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoParcialId]);

// Conta a mao: "dentro" jogou 6 das 7 rodadas com placar (a rodada
// escolhida ficou sem placar); "fora" jogou as 7 normalmente, porque a
// partida sem placar nunca foi a dele.
$ladoDentro = ladoPorRodada($posDentro);
unset($ladoDentro[$rodadaEscolhida]);
$contagemDentro = array_count_values($ladoDentro);
$gamesEsperadoDentro = ($contagemDentro['A'] ?? 0) * 5 + ($contagemDentro['B'] ?? 0) * 2;

$contagemFora = array_count_values(ladoPorRodada($posFora));
$gamesEsperadoFora = ($contagemFora['A'] ?? 0) * 5 + ($contagemFora['B'] ?? 0) * 2;

$linhasParcial = Ranking::acumulado($pdo, null, null);
$linhaParcialDentro = null;
$linhaParcialFora = null;
foreach ($linhasParcial as $linha) {
    if ((int) $linha['jogador_id'] === $jogadorParcialDentroId) {
        $linhaParcialDentro = $linha;
    }
    if ((int) $linha['jogador_id'] === $jogadorParcialForaId) {
        $linhaParcialFora = $linha;
    }
}

Teste::verdade($linhaParcialDentro !== null, 'quem jogou a partida sem placar continua aparecendo no ranking (pelas outras 6 partidas)');
Teste::igual(
    6,
    (int) $linhaParcialDentro['jogadas'],
    'p.encerrada = 1 exclui a partida sem placar da CONTAGEM de jogadas de quem estava nela (6, nao 7)'
);
Teste::igual(
    $gamesEsperadoDentro,
    (int) $linhaParcialDentro['games'],
    'p.encerrada = 1 exclui a partida sem placar da SOMA de games: sem o filtro, SUM() ignoraria o NULL em silencio e o total bateria igual mesmo contando 7 jogadas'
);

Teste::verdade($linhaParcialFora !== null, 'quem NAO jogou a partida sem placar aparece no ranking normalmente');
Teste::igual(
    7,
    (int) $linhaParcialFora['jogadas'],
    'quem nao estava na partida sem placar continua com as 7 jogadas normais, sem ser afetado pelo evento'
);
Teste::igual(
    $gamesEsperadoFora,
    (int) $linhaParcialFora['games'],
    'o total de quem nao estava na partida sem placar bate com a conta a mao das 7 rodadas normais'
);

// --- Convidado sem conta nunca aparece ---------------------------------------
$nomesConvidadosEvento1 = array_map(static fn (int $n): string => "Convidado {$n}", range(2, 8));
foreach ($linhasSemFiltro as $linha) {
    Teste::verdade(
        !in_array($linha['nome'], $nomesConvidadosEvento1, true),
        "a linha de nome '{$linha['nome']}' nao bate com nenhum nome de convidado sem conta do evento 1"
    );
    Teste::verdade(
        $linha['jogador_id'] !== null,
        'toda linha do ranking tem jogador_id preenchido (convidado sem conta nunca acumula)'
    );
}

// --- Soma entre dois eventos, dois jogadores, sem misturar -------------------
// jogadorId disputa o evento 1 (ja gravado acima) e agora tambem o evento 2;
// jogadorSoNoEvento2Id disputa SO o evento 2. Isso pega tanto um bug de soma
// entre eventos do MESMO jogador quanto um bug que misturasse o total de UM
// jogador com o de OUTRO.
$jogadorSoNoEvento2Id = Auth::cadastrar($pdo, 'Jogador So Evento 2', "jogsoeve2{$sufixo}@exemplo.com", 'senhaforte123');

$campeonatoId2 = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa 2', 'data_evento' => '2026-05-24',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoId2, 'Jogador Com Conta E2', $jogadorId);
Campeonato::inscrever($pdo, $campeonatoId2, 'Jogador So Evento 2', $jogadorSoNoEvento2Id);
foreach (range(3, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoId2, "Convidado E2 {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoId2, 999);

$buscaInscEvento2 = $pdo->prepare('SELECT jogador_id, posicao_sorteio FROM inscricoes WHERE campeonato_id = ?');
$buscaInscEvento2->execute([$campeonatoId2]);
$posicaoPorJogadorEvento2 = [];
foreach ($buscaInscEvento2->fetchAll() as $linhaInsc) {
    if ($linhaInsc['jogador_id'] !== null) {
        $posicaoPorJogadorEvento2[(int) $linhaInsc['jogador_id']] = (int) $linhaInsc['posicao_sorteio'];
    }
}

$buscaPartidasEvento2 = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidasEvento2->execute([$campeonatoId2]);
foreach ($buscaPartidasEvento2->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    // Placar bem diferente do evento 1 (4-2 em vez de 6-3), para nao deixar
    // um bug de soma entre eventos passar por coincidencia de numeros.
    Placar::gravar($pdo, $campeonatoId2, (int) $partidaId, 4, 2, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoId2]);

$ladosEvento2Jogador = ladoPorRodada($posicaoPorJogadorEvento2[$jogadorId]);
$contagemEvento2Jogador = array_count_values($ladosEvento2Jogador);
$gamesEsperadoEvento2Jogador = ($contagemEvento2Jogador['A'] ?? 0) * 4 + ($contagemEvento2Jogador['B'] ?? 0) * 2;

$ladosEvento2Secundario = ladoPorRodada($posicaoPorJogadorEvento2[$jogadorSoNoEvento2Id]);
$contagemEvento2Secundario = array_count_values($ladosEvento2Secundario);
$gamesEsperadoEvento2Secundario = ($contagemEvento2Secundario['A'] ?? 0) * 4 + ($contagemEvento2Secundario['B'] ?? 0) * 2;

$linhasComDoisEventos = Ranking::acumulado($pdo, null, null);
$linhaJogadorDoisEventos = null;
$linhaJogadorSoEvento2 = null;
foreach ($linhasComDoisEventos as $linha) {
    if ((int) $linha['jogador_id'] === $jogadorId) {
        $linhaJogadorDoisEventos = $linha;
    }
    if ((int) $linha['jogador_id'] === $jogadorSoNoEvento2Id) {
        $linhaJogadorSoEvento2 = $linha;
    }
}

Teste::verdade($linhaJogadorDoisEventos !== null, 'o jogador que disputou os dois eventos aparece no ranking');
Teste::igual(2, (int) $linhaJogadorDoisEventos['eventos'], 'conta os 2 eventos disputados pelo mesmo jogador');
Teste::igual(14, (int) $linhaJogadorDoisEventos['jogadas'], 'soma as 7+7 partidas dos dois eventos, sem multiplicar linha');
Teste::igual(
    $gamesEsperadoEvento1 + $gamesEsperadoEvento2Jogador,
    (int) $linhaJogadorDoisEventos['games'],
    'a soma entre os dois eventos bate com a conta a mao de cada evento separado'
);

Teste::verdade($linhaJogadorSoEvento2 !== null, 'o jogador que so disputou o evento 2 aparece no ranking');
Teste::igual(1, (int) $linhaJogadorSoEvento2['eventos'], 'conta so 1 evento para quem so jogou o evento 2');
Teste::igual(7, (int) $linhaJogadorSoEvento2['jogadas'], 'jogou so as 7 partidas do evento 2, sem herdar nada do evento 1');
Teste::igual(
    $gamesEsperadoEvento2Secundario,
    (int) $linhaJogadorSoEvento2['games'],
    'o total de quem so jogou o evento 2 bate com a conta a mao do evento 2, sem misturar com o total do outro jogador'
);

// --- Fronteira de periodo: exatamente no inicio e exatamente no fim da
// janela tem que entrar; um dia fora de cada lado tem que sair. Pega tanto
// um ">=" que virasse ">" quanto um "<=" que virasse "<". -------------------
$jogadorInicioId = Auth::cadastrar($pdo, 'Jogador Fronteira Inicio', "jogini{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoInicioId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Fronteira Inicio', 'data_evento' => '2026-03-01',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoInicioId, 'Jogador Fronteira Inicio', $jogadorInicioId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoInicioId, "Convidado FI {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoInicioId, 1010);
$buscaPartidasInicio = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidasInicio->execute([$campeonatoInicioId]);
foreach ($buscaPartidasInicio->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    Placar::gravar($pdo, $campeonatoInicioId, (int) $partidaId, 6, 1, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoInicioId]);

$jogadorFimId = Auth::cadastrar($pdo, 'Jogador Fronteira Fim', "jogfim{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoFimId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Fronteira Fim', 'data_evento' => '2026-03-31',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoFimId, 'Jogador Fronteira Fim', $jogadorFimId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoFimId, "Convidado FF {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoFimId, 2020);
$buscaPartidasFim = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidasFim->execute([$campeonatoFimId]);
foreach ($buscaPartidasFim->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    Placar::gravar($pdo, $campeonatoFimId, (int) $partidaId, 6, 1, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoFimId]);

$dentroDaJanela = Ranking::acumulado($pdo, '2026-03-01', '2026-03-31');
Teste::verdade(
    contemJogador($dentroDaJanela, $jogadorInicioId),
    'torneio na data EXATA de inicio da janela entra (limite inclusivo)'
);
Teste::verdade(
    contemJogador($dentroDaJanela, $jogadorFimId),
    'torneio na data EXATA de fim da janela entra (limite inclusivo)'
);

$semODiaDeInicio = Ranking::acumulado($pdo, '2026-03-02', '2026-03-31');
Teste::verdade(
    !contemJogador($semODiaDeInicio, $jogadorInicioId),
    'torneio um dia antes do novo inicio da janela fica de fora'
);
Teste::verdade(
    contemJogador($semODiaDeInicio, $jogadorFimId),
    'o torneio do fim da janela continua entrando quando so o inicio aperta'
);

$semODiaDeFim = Ranking::acumulado($pdo, '2026-03-01', '2026-03-30');
Teste::verdade(
    !contemJogador($semODiaDeFim, $jogadorFimId),
    'torneio um dia depois do novo fim da janela fica de fora'
);
Teste::verdade(
    contemJogador($semODiaDeFim, $jogadorInicioId),
    'o torneio do inicio da janela continua entrando quando so o fim aperta'
);

// --- Data mal formada e tratada como sem filtro, nunca passada crua para o
// MariaDB (que truncaria com aviso e alargaria a janela para tudo em vez de
// filtrar nada e ainda por cima parecer que filtrou) ------------------------
$semFiltroNenhum = Ranking::acumulado($pdo, null, null);
Teste::igual(
    $semFiltroNenhum,
    Ranking::acumulado($pdo, 'nao-e-data', null),
    'uma data mal formada em "de" e tratada como sem filtro, resultado identico a null'
);
Teste::igual(
    $semFiltroNenhum,
    Ranking::acumulado($pdo, null, '31/03/2026'),
    'uma data em formato errado (com barras) em "ate" e tratada como sem filtro, resultado identico a null'
);
Teste::igual(
    $semFiltroNenhum,
    Ranking::acumulado($pdo, '', ''),
    'string vazia nas duas datas continua tratada como sem filtro'
);

// --- Data com o FORMATO certo mas calendario impossivel (mes 13, dia que
// nao existe no mes) tambem tem que ser tratada como sem filtro. O
// comportamento do MariaDB NAO e uniforme entre os dois jeitos de ser
// impossivel, verificado direto neste servidor (SHOW WARNINGS): um mes
// fora de 1-12 dispara o aviso 1292 (Truncated incorrect datetime value) e
// a comparacao vira "verdadeira para tudo", alargando a janela para todo
// mundo, exatamente o sintoma que este item descreve. Ja um DIA fora do
// mes (30 de fevereiro, 31 de abril) NAO dispara aviso nenhum neste
// MariaDB - o valor e aceito como data literal e comparado componente a
// componente (ano, mes, dia) sem validar se o dia cabe no mes, o que da um
// resultado errado DIFERENTE (nem "tudo" nem "nada": exclui so os eventos
// cuja tripla ano-mes-dia fique "lexicograficamente antes" do valor
// impossivel). Por isso os testes de dia impossivel abaixo usam um evento
// dedicado, colocado exatamente onde esse defeito o excluiria por engano,
// em vez de comparar a lista inteira com o cenario sem filtro. -----------
Teste::igual(
    $semFiltroNenhum,
    Ranking::acumulado($pdo, '2026-13-01', null),
    'mes 13 (impossivel) bate no formato AAAA-MM-DD mas nao existe no calendario: tratado como sem filtro'
);

$jogadorJaneiroId = Auth::cadastrar($pdo, 'Jogador Janeiro', "jogjaneiro{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoJaneiroId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Janeiro', 'data_evento' => '2026-01-10',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoJaneiroId, 'Jogador Janeiro', $jogadorJaneiroId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoJaneiroId, "Convidado JAN {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoJaneiroId, 7002);
$buscaPartidasJaneiro = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidasJaneiro->execute([$campeonatoJaneiroId]);
foreach ($buscaPartidasJaneiro->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    Placar::gravar($pdo, $campeonatoJaneiroId, (int) $partidaId, 6, 2, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoJaneiroId]);

// "2026-02-30" nao existe, mas se comparado cru (ano, mes, dia) fica
// "depois" de qualquer data de janeiro/2026 (mes 02 > mes 01) - um "de"
// sem checkdate() excluiria o evento de janeiro por engano em vez de virar
// sem filtro.
Teste::verdade(
    contemJogador(Ranking::acumulado($pdo, '2026-02-30', null), $jogadorJaneiroId),
    'dia 30 de fevereiro (nao existe, mas dentro da faixa 1-31) e tratado como sem filtro: o evento de janeiro/2026 nao pode ser excluido por uma comparacao crua de ano-mes-dia'
);

// "2026-04-31" nao existe; comparado cru como limite superior ("ate"),
// fica "antes" de qualquer data de maio/2026 (mes 04 < mes 05) - um "ate"
// sem checkdate() excluiria o evento 1 (2026-05-10, $jogadorId) por
// engano.
Teste::verdade(
    contemJogador(Ranking::acumulado($pdo, null, '2026-04-31'), $jogadorId),
    'dia 31 de abril (abril so tem 30 dias) e tratado como sem filtro: o evento 1 (maio/2026) nao pode ser excluido por uma comparacao crua de ano-mes-dia'
);

// --- 29 de fevereiro: a fronteira exata que checkdate() decide. Existe de
// verdade em 2028 (ano bissexto) e NAO existe em 2027 (ano comum) --------
$jogadorBissextoId = Auth::cadastrar($pdo, 'Jogador Bissexto', "jogbissexto{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoBissextoId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Bissexto', 'data_evento' => '2028-02-29',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoBissextoId, 'Jogador Bissexto', $jogadorBissextoId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoBissextoId, "Convidado BIS {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoBissextoId, 7001);
$buscaPartidasBissexto = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidasBissexto->execute([$campeonatoBissextoId]);
foreach ($buscaPartidasBissexto->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    Placar::gravar($pdo, $campeonatoBissextoId, (int) $partidaId, 6, 2, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoBissextoId]);

Teste::verdade(
    contemJogador(Ranking::acumulado($pdo, '2028-02-29', null), $jogadorBissextoId),
    '29 de fevereiro de 2028 (ano bissexto, essa data existe de verdade) filtra normalmente e inclui o evento marcado nela'
);
Teste::verdade(
    !contemJogador(Ranking::acumulado($pdo, '2028-03-01', null), $jogadorBissextoId),
    'confirma que uma data valida continua filtrando de verdade: "de" = 2028-03-01 exclui o evento de 2028-02-29'
);

$semFiltroComBissexto = Ranking::acumulado($pdo, null, null);
Teste::igual(
    $semFiltroComBissexto,
    Ranking::acumulado($pdo, '2027-02-29', null),
    '29 de fevereiro de 2027 (2027 nao e bissexto, essa data NAO existe) e tratado como sem filtro, nao como um "de" que excluiria tudo'
);

// --- ORDER BY: para um ranking, a ordem E o produto. Nada mais no projeto
// re-ordena o array devolvido por Ranking::acumulado, entao um ORDER BY
// quebrado (removido ou invertido) devolveria o pior jogador em primeiro
// lugar e nenhum outro lugar do sistema notaria. -----------------------------
// Tres jogadores com faixas de games BEM separadas (impossivel de se
// confundir mesmo variando o lado da dupla sorteado), criados de proposito
// na ordem CONTRARIA a ordem esperada no ranking (baixo primeiro, alto por
// ultimo) - se o ORDER BY sumir e a consulta cair de volta para a ordem
// natural (que tende a seguir a ordem de insercao/id), a checagem abaixo
// pega isso na hora, porque a ordem natural ficaria CRESCENTE, nao
// decrescente.
$jogadorBaixoId = Auth::cadastrar($pdo, 'Jogador Ordem Baixo', "jogordbaixo{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoBaixoId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Ordem Baixo', 'data_evento' => '2026-07-01',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoBaixoId, 'Jogador Ordem Baixo', $jogadorBaixoId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoBaixoId, "Convidado OB {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoBaixoId, 6001);
$buscaPartidasBaixo = $pdo->prepare('SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?');
$buscaPartidasBaixo->execute([$campeonatoBaixoId]);
foreach ($buscaPartidasBaixo->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    // Faixa possivel para qualquer posicao: 0 a 7 (1 ponto por rodada, no
    // maximo, se sempre do lado que soma 1).
    Placar::gravar($pdo, $campeonatoBaixoId, (int) $partidaId, 1, 0, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoBaixoId]);

$jogadorMedioId = Auth::cadastrar($pdo, 'Jogador Ordem Medio', "jogordmedio{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoMedioId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Ordem Medio', 'data_evento' => '2026-07-02',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoMedioId, 'Jogador Ordem Medio', $jogadorMedioId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoMedioId, "Convidado OM {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoMedioId, 6002);
$buscaPartidasMedio = $pdo->prepare('SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?');
$buscaPartidasMedio->execute([$campeonatoMedioId]);
foreach ($buscaPartidasMedio->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    // Faixa possivel: pior caso 7*15=105, melhor caso 7*20=140 - sempre
    // maior que o pior caso de "baixo" (7) e sempre menor que o melhor caso
    // de "alto" (veja abaixo).
    Placar::gravar($pdo, $campeonatoMedioId, (int) $partidaId, 20, 15, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoMedioId]);

$jogadorAltoId = Auth::cadastrar($pdo, 'Jogador Ordem Alto', "jogordalto{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoAltoId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Ordem Alto', 'data_evento' => '2026-07-03',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::inscrever($pdo, $campeonatoAltoId, 'Jogador Ordem Alto', $jogadorAltoId);
foreach (range(2, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoAltoId, "Convidado OA {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoAltoId, 6003);
$buscaPartidasAlto = $pdo->prepare('SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?');
$buscaPartidasAlto->execute([$campeonatoAltoId]);
foreach ($buscaPartidasAlto->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    // Faixa possivel: pior caso 7*80=560, sempre maior que o melhor caso de
    // "medio" (140).
    Placar::gravar($pdo, $campeonatoAltoId, (int) $partidaId, 90, 80, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoAltoId]);

// Dois jogadores com o MESMO total de games (empate genuino, nao
// coincidencia): um placar de empate (mesmo numero nos dois lados) em
// TODAS as 14 partidas de um campeonato da o mesmo total para os 8
// competidores, nao importa o lado da dupla. Usa 36-36 (total 252 por
// competidor) para nao coincidir com nenhuma outra faixa usada acima ou
// com nenhum dos totais ja calculados neste arquivo (7, 20, 22 ou 42, 58,
// 105-140, 560-630).
$jogadorZuluId = Auth::cadastrar($pdo, 'Zulu Empate', "jogzulu{$sufixo}@exemplo.com", 'senhaforte123');
$jogadorAlfaId = Auth::cadastrar($pdo, 'Alfa Empate', "jogalfa{$sufixo}@exemplo.com", 'senhaforte123');
$campeonatoEmpateId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Etapa Empate', 'data_evento' => '2026-07-04',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
// Cadastra Zulu ANTES de Alfa de proposito: se o desempate por nome sumisse
// e a consulta caisse de volta para alguma ordem ligada a insercao/id dos
// jogadores empatados, Zulu apareceria antes de Alfa - o contrario do que
// "u.nome ASC" exige.
Campeonato::inscrever($pdo, $campeonatoEmpateId, 'Zulu Empate', $jogadorZuluId);
Campeonato::inscrever($pdo, $campeonatoEmpateId, 'Alfa Empate', $jogadorAlfaId);
foreach (range(3, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoEmpateId, "Convidado OE {$numero} {$sufixo}", null);
}
Campeonato::sortear($pdo, $campeonatoEmpateId, 6004);
$buscaPartidasEmpate = $pdo->prepare('SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?');
$buscaPartidasEmpate->execute([$campeonatoEmpateId]);
foreach ($buscaPartidasEmpate->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
    Placar::gravar($pdo, $campeonatoEmpateId, (int) $partidaId, 36, 36, $organizadorId);
}
$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoEmpateId]);

$linhasOrdenadas = Ranking::acumulado($pdo, null, null);

Teste::verdade(count($linhasOrdenadas) >= 10, 'o ranking final tem todas as linhas esperadas para checar a ordem');

$ordemNaoCrescente = true;
for ($indice = 1; $indice < count($linhasOrdenadas); $indice++) {
    if ((int) $linhasOrdenadas[$indice]['games'] > (int) $linhasOrdenadas[$indice - 1]['games']) {
        $ordemNaoCrescente = false;
        break;
    }
}
Teste::verdade(
    $ordemNaoCrescente,
    'o ranking inteiro vem em ordem NAO crescente de games (ORDER BY games DESC): cada linha tem games menor ou igual a linha anterior'
);

$posicaoBaixo = null;
$posicaoMedio = null;
$posicaoAlto = null;
foreach ($linhasOrdenadas as $indice => $linha) {
    if ((int) $linha['jogador_id'] === $jogadorBaixoId) {
        $posicaoBaixo = $indice;
    }
    if ((int) $linha['jogador_id'] === $jogadorMedioId) {
        $posicaoMedio = $indice;
    }
    if ((int) $linha['jogador_id'] === $jogadorAltoId) {
        $posicaoAlto = $indice;
    }
}
Teste::verdade(
    $posicaoAlto !== null && $posicaoMedio !== null && $posicaoBaixo !== null,
    'os tres jogadores de faixas de games separadas aparecem no ranking'
);
Teste::verdade(
    $posicaoAlto < $posicaoMedio && $posicaoMedio < $posicaoBaixo,
    'o jogador de games alto vem antes do medio, que vem antes do baixo (ordem decrescente de verdade, nao so a ordem de cadastro)'
);

$posicaoZulu = null;
$posicaoAlfa = null;
foreach ($linhasOrdenadas as $indice => $linha) {
    if ((int) $linha['jogador_id'] === $jogadorZuluId) {
        $posicaoZulu = $indice;
    }
    if ((int) $linha['jogador_id'] === $jogadorAlfaId) {
        $posicaoAlfa = $indice;
    }
}
Teste::verdade($posicaoZulu !== null && $posicaoAlfa !== null, 'os dois jogadores empatados em games aparecem no ranking');
Teste::igual(
    (int) $linhasOrdenadas[$posicaoZulu]['games'],
    (int) $linhasOrdenadas[$posicaoAlfa]['games'],
    'checagem da premissa do cenario de empate: os dois tem exatamente o mesmo total de games'
);
Teste::verdade(
    $posicaoAlfa < $posicaoZulu,
    'entre dois jogadores empatados em games, o desempate e por nome em ordem alfabetica (Alfa antes de Zulu), mesmo Zulu tendo sido cadastrado primeiro'
);

$pdo->rollBack();

exit(Teste::resumo());
