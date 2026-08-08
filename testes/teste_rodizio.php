<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../src/Rodizio.php';

echo "Rodizio\n";

Teste::igual(7, count(Rodizio::RODADAS), 'tem 7 rodadas');

foreach (Rodizio::RODADAS as $numero => $partidas) {
    Teste::igual(2, count($partidas), "rodada {$numero} tem 2 partidas");

    $jogadores = [];
    foreach ($partidas as $partida) {
        Teste::igual(2, count($partida), "rodada {$numero} tem 2 duplas por partida");
        foreach ($partida as $dupla) {
            Teste::igual(2, count($dupla), "rodada {$numero} tem duplas de 2 jogadores");
            $jogadores = array_merge($jogadores, $dupla);
        }
    }
    sort($jogadores);
    Teste::igual([1, 2, 3, 4, 5, 6, 7, 8], $jogadores, "rodada {$numero} usa as 8 posicoes uma vez");
    Teste::igual([1, 2, 3, 4, 5, 6, 7, 8], Rodizio::jogadoresDaRodada($numero), "jogadoresDaRodada({$numero}) devolve as 8 posicoes");
}

$duplas = Rodizio::todasAsDuplas();
Teste::igual(28, count($duplas), 'gera 28 duplas');
Teste::igual(28, count(array_unique($duplas)), 'as 28 duplas sao distintas');

$contagem = [];
foreach ($duplas as $dupla) {
    [$a, $b] = array_map('intval', explode('-', $dupla));
    $contagem[$a] = ($contagem[$a] ?? 0) + 1;
    $contagem[$b] = ($contagem[$b] ?? 0) + 1;
}
foreach (range(1, 8) as $posicao) {
    Teste::igual(7, $contagem[$posicao] ?? 0, "posicao {$posicao} e parceira exatamente 7 vezes");
}

exit(Teste::resumo());
