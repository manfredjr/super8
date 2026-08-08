<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../src/Sorteio.php';

echo "Sorteio\n";

$ids = [11, 22, 33, 44, 55, 66, 77, 88];

$primeira = Sorteio::ordenar($ids, 12345);
$segunda = Sorteio::ordenar($ids, 12345);
Teste::igual($primeira, $segunda, 'mesma semente devolve sempre a mesma ordem');

$outra = Sorteio::ordenar($ids, 999);
Teste::verdade($primeira !== $outra, 'sementes diferentes devolvem ordens diferentes');

$conferencia = $primeira;
sort($conferencia);
Teste::igual($ids, $conferencia, 'mantem exatamente os mesmos ids');
Teste::igual(8, count($primeira), 'devolve 8 posicoes');

Teste::verdade($ids !== $primeira, 'a ordem sorteada difere da ordem de entrada');

$semente = Sorteio::gerarSemente();
Teste::verdade($semente >= 1 && $semente <= 2147483647, 'a semente cabe em inteiro sem sinal de 32 bits');

Sorteio::ordenar($ids, 12345);
$randApos1 = mt_rand();
Sorteio::ordenar($ids, 12345);
$randApos2 = mt_rand();
Teste::verdade($randApos1 !== $randApos2, 'nao deixa o gerador global preso a semente do sorteio');

exit(Teste::resumo());
