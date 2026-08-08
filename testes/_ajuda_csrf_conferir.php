<?php

// Ajudante de teste, nao um arquivo de teste em si: o nome nao comeca com
// "teste_" de proposito, para o testes/executar.php nao tentar rodar isto
// sozinho. csrf_conferir() termina o processo com exit() no caminho de
// rejeicao, entao a unica forma de observar isso de fora e rodando este
// arquivo em um processo separado e conferindo saida e codigo de saida.
//
// Uso: php _ajuda_csrf_conferir.php <certo|errado|array>

require __DIR__ . '/../config/csrf.php';

$modo = $argv[1] ?? 'errado';

session_start();

$tokenReal = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';
$_SESSION['csrf'] = $tokenReal;

if ($modo === 'array') {
    $_POST['csrf'] = ['nao', 'e', 'uma', 'string'];
} elseif ($modo === 'certo') {
    $_POST['csrf'] = $tokenReal;
} else {
    // Token errado de proposito, do mesmo formato do real, nao vazio.
    $_POST['csrf'] = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
}

$rejeitado = true;
register_shutdown_function(function () use (&$rejeitado) {
    if ($rejeitado) {
        // csrf_conferir() rejeita chamando exit() com uma string, e o PHP
        // sempre devolve codigo de saida 0 quando exit() recebe uma string.
        // Forcamos um codigo diferente de zero aqui para o processo que
        // chamou este ajudante conseguir enxergar a rejeicao pelo codigo
        // de saida, e nao so pelo texto impresso.
        exit(9);
    }
});

csrf_conferir();

$rejeitado = false;
echo "aceito\n";
