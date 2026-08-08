<?php

// Ajudante de teste, nao um arquivo de teste em si: o nome nao comeca com
// "teste_" de proposito, para o testes/executar.php nao tentar rodar isto
// sozinho. exigirDonoDoCampeonato() (por dentro de exigirLogin()) termina o
// processo com exit() no caminho de rejeicao, e exit() com uma string ou sem
// argumento sempre devolve codigo de saida 0 no PHP, entao a unica forma de
// observar a rejeicao de fora e rodar isto em processo separado e conferir
// saida e codigo de saida, do mesmo jeito que testes/teste_sessao.php ja faz
// para csrf_conferir().
//
// Uso: php _ajuda_dono_campeonato.php <idUsuarioLogado> <idCampeonato>

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/acesso.php';

$idUsuario = (int) ($argv[1] ?? 0);
$idCampeonato = (int) ($argv[2] ?? 0);

iniciarSessao();
$_SESSION['usuario'] = ['id' => $idUsuario];

$terminouDireito = false;
register_shutdown_function(function () use (&$terminouDireito) {
    echo "codigo_http=" . (http_response_code() ?: 'nenhum') . "\n";
    if (!$terminouDireito) {
        exit(9);
    }
});

$pdo = db();
$campeonato = exigirDonoDoCampeonato($pdo, $idCampeonato);

$terminouDireito = true;
echo "dono confirmado id=" . $campeonato['id'] . "\n";
