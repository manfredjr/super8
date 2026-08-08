<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';

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

Campeonato::sortear($pdo, $campeonatoId, 4242);

$contaPartidas->execute([$campeonatoId]);
Teste::igual(14, (int) $contaPartidas->fetchColumn(), 'refazer o sorteio nao duplica partidas');

$pdo->rollBack();

exit(Teste::resumo());
