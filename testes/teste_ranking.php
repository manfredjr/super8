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

$pdo->prepare("UPDATE campeonatos SET status = 'encerrado' WHERE id = ?")->execute([$campeonatoId]);

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

$pdo->rollBack();

exit(Teste::resumo());
