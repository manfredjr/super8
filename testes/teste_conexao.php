<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';

echo "Conexao\n";

$pdo = db();
Teste::verdade($pdo instanceof PDO, 'db() devolve um PDO');
Teste::verdade(db() === $pdo, 'db() reaproveita a mesma conexao');

$tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach (['users', 'tentativas_login', 'campeonatos', 'inscricoes', 'rodadas', 'partidas'] as $tabela) {
    Teste::verdade(in_array($tabela, $tabelas, true), "a tabela {$tabela} existe");
}

exit(Teste::resumo());
