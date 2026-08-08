<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';

/**
 * Mapa id da inscricao => posicao_sorteio, ordenado pela chave. Serve para
 * comparar dois sorteios sem depender da ordem de exibicao de
 * listarInscricoes, que muda conforme as posicoes ja atribuidas.
 */
function mapaPosicoes(PDO $pdo, int $campeonatoId): array
{
    $mapa = [];
    foreach (Campeonato::listarInscricoes($pdo, $campeonatoId) as $inscricao) {
        $mapa[(int) $inscricao['id']] = (int) $inscricao['posicao_sorteio'];
    }
    ksort($mapa);

    return $mapa;
}

/** As 14 partidas, em ordem de rodada e quadra, com os 4 ids de inscricao de cada uma. */
function partidasBrutas(PDO $pdo, int $campeonatoId): array
{
    $busca = $pdo->prepare(
        'SELECT r.numero, p.quadra, p.dupla_a_j1, p.dupla_a_j2, p.dupla_b_j1, p.dupla_b_j2
         FROM partidas p
         JOIN rodadas r ON r.id = p.rodada_id
         WHERE r.campeonato_id = ?
         ORDER BY r.numero, p.quadra'
    );
    $busca->execute([$campeonatoId]);

    return array_map(
        static fn (array $linha): array => array_map('intval', $linha),
        $busca->fetchAll()
    );
}

echo "Campeonato\n";

$pdo = db();
$pdo->beginTransaction();

$organizadorId = Auth::cadastrar($pdo, 'Organizador', 'org' . random_int(1000, 9999) . '@exemplo.com', 'senhaforte123');

$campeonatoId = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Super 8 de teste',
    'data_evento' => '2026-09-01',
    'local'       => 'Arena Central',
    'custo'       => 50.00,
    'descricao'   => 'Evento de teste',
]);
Teste::verdade($campeonatoId > 0, 'criar devolve o id do campeonato');

$campeonato = Campeonato::buscar($pdo, $campeonatoId);
Teste::igual('rascunho', $campeonato['status'], 'nasce como rascunho');
Teste::igual(null, $campeonato['seed_sorteio'], 'nasce sem semente');

$erro = null;
try {
    Campeonato::sortear($pdo, $campeonatoId);
} catch (RuntimeException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa sortear sem os 8 inscritos');

foreach (range(1, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoId, "Jogador {$numero}", null);
}
Teste::igual(8, count(Campeonato::listarInscricoes($pdo, $campeonatoId)), 'tem 8 inscritos');

$erro = null;
try {
    Campeonato::inscrever($pdo, $campeonatoId, 'Jogador 9', null);
} catch (RuntimeException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa o nono inscrito');

$semente = Campeonato::sortear($pdo, $campeonatoId, 4242);
Teste::igual(4242, $semente, 'grava a semente informada');

$campeonato = Campeonato::buscar($pdo, $campeonatoId);
Teste::igual('sorteado', $campeonato['status'], 'muda o status para sorteado');
Teste::igual(4242, (int) $campeonato['seed_sorteio'], 'a semente fica no campeonato');

$posicoes = array_map(
    static fn (array $inscricao): int => (int) $inscricao['posicao_sorteio'],
    Campeonato::listarInscricoes($pdo, $campeonatoId)
);
sort($posicoes);
Teste::igual([1, 2, 3, 4, 5, 6, 7, 8], $posicoes, 'as 8 posicoes foram distribuidas');

$chaveamento = Campeonato::chaveamento($pdo, $campeonatoId);
Teste::igual(7, count($chaveamento), 'gera 7 rodadas');
foreach ($chaveamento as $rodada) {
    Teste::igual(2, count($rodada['partidas']), "a rodada {$rodada['numero']} tem 2 partidas");
}

$contaPartidas = $pdo->prepare(
    'SELECT COUNT(*) FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);

$contaPartidas->execute([$campeonatoId]);
Teste::igual(14, (int) $contaPartidas->fetchColumn(), 'gera 14 partidas');

Teste::verdade(!Campeonato::temPlacarLancado($pdo, $campeonatoId), 'ainda nao tem placar lancado');

// Estado de auditoria: campeonato ja sorteado, com posicao_sorteio gravada
// para os 8 inscritos. E exatamente esse o estado em que alguem pediria para
// refazer o sorteio com a mesma semente e conferir que da o mesmo resultado.
$mapaPosicoesAntes = mapaPosicoes($pdo, $campeonatoId);
$partidasAntes = partidasBrutas($pdo, $campeonatoId);

Campeonato::sortear($pdo, $campeonatoId, 4242);

$contaPartidas->execute([$campeonatoId]);
Teste::igual(14, (int) $contaPartidas->fetchColumn(), 'refazer o sorteio nao duplica partidas');

$mapaPosicoesDepois = mapaPosicoes($pdo, $campeonatoId);
$partidasDepois = partidasBrutas($pdo, $campeonatoId);

Teste::igual(
    $mapaPosicoesAntes,
    $mapaPosicoesDepois,
    'refazer o sorteio com a mesma semente reproduz o mesmo mapeamento de posicoes (auditoria)'
);
Teste::igual(
    $partidasAntes,
    $partidasDepois,
    'refazer o sorteio com a mesma semente reproduz exatamente as mesmas 14 partidas (auditoria)'
);

$semente9999 = Campeonato::sortear($pdo, $campeonatoId, 9999);
Teste::igual(9999, $semente9999, 'sorteia de novo com outra semente');
$mapaPosicoesOutraSemente = mapaPosicoes($pdo, $campeonatoId);
Teste::verdade(
    $mapaPosicoesAntes !== $mapaPosicoesOutraSemente,
    'uma semente diferente muda o mapeamento de posicoes (a asserta acima nao passa a toa)'
);

$pdo->rollBack();

exit(Teste::resumo());
