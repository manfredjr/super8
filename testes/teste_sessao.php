<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/sessao.php';
require __DIR__ . '/../config/csrf.php';

// Precisa vir antes de qualquer saida (echo etc.), mesmo motivo do
// teste_csrf.php: se a sessao comecar depois de qualquer saida, o PHP
// recusa iniciar de verdade.
iniciarSessao();

echo "Sessao\n";

Teste::igual(PHP_SESSION_ACTIVE, session_status(), 'iniciarSessao deixa a sessao realmente ativa');

$parametros = session_get_cookie_params();
Teste::verdade($parametros['httponly'], 'o cookie de sessao e httponly');
Teste::igual('Strict', $parametros['samesite'], 'o cookie de sessao usa samesite Strict');
Teste::igual(COOKIE_SEGURO, $parametros['secure'], 'o cookie de sessao segue a constante COOKIE_SEGURO');
Teste::igual(0, $parametros['lifetime'], 'o cookie de sessao dura o tempo do navegador aberto (lifetime 0)');
Teste::igual('/', $parametros['path'], 'o cookie de sessao vale para o site inteiro (path /)');

Teste::igual('1', ini_get('session.use_strict_mode'), 'o modo estrito de sessao esta ligado, contra fixacao de sessao');

// A partir daqui, se csrf_conferir() rejeitar por engano um token correto,
// ela termina o script chamando exit() com uma string, e o PHP sempre
// devolve codigo de saida 0 nesse caso. Um shutdown function garante que
// essa falha nao fique invisivel para o testes/executar.php, que so olha
// o codigo de saida do processo, nao o texto impresso.
$terminouDireito = false;
register_shutdown_function(function () use (&$terminouDireito) {
    if (!$terminouDireito) {
        exit(1);
    }
});

// csrf_conferir(): caminho feliz. Nao termina o script quando o token esta
// certo, entao da para testar no mesmo processo.
$_POST['csrf'] = csrf_token();
csrf_conferir();
Teste::verdade(true, 'csrf_conferir aceita o token correto e deixa o script continuar');

// csrf_conferir(): caminho de rejeicao. Termina o script com exit(), entao
// precisa rodar em outro processo, do mesmo jeito que testes/executar.php
// ja faz para rodar cada arquivo de teste separadamente.
function rodarAjudanteCsrf(string $modo): array
{
    $arquivo = __DIR__ . '/_ajuda_csrf_conferir.php';
    $saida = [];
    $codigo = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($arquivo) . ' ' . escapeshellarg($modo), $saida, $codigo);
    return [$codigo, implode("\n", $saida)];
}

[$codigo, $saida] = rodarAjudanteCsrf('errado');
Teste::verdade($codigo !== 0, 'token errado faz csrf_conferir rejeitar o pedido (saida do ajudante diferente de zero)');
Teste::verdade(str_contains($saida, 'Pedido invalido'), 'a rejeicao mostra a mensagem de pedido invalido');

[$codigo, $saida] = rodarAjudanteCsrf('array');
Teste::verdade($codigo !== 0, 'token enviado como array tambem e rejeitado, sem travar o script');
Teste::verdade(str_contains($saida, 'Pedido invalido'), 'a rejeicao do array mostra a mensagem de pedido invalido');

[$codigo, $saida] = rodarAjudanteCsrf('certo');
Teste::igual(0, $codigo, 'token correto no ajudante nao aciona a rejeicao (saida zero)');

$resultado = Teste::resumo();
$terminouDireito = true;
exit($resultado);
