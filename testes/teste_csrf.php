<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/csrf.php';

// Precisa vir antes de qualquer saida (echo etc.), senao o PHP recusa iniciar
// a sessao e as verificacoes de token abaixo passariam a testar um array comum,
// nao uma sessao de verdade.
session_start();

echo "CSRF e escape\n";

Teste::igual('&lt;script&gt;', e('<script>'), 'escapa sinal de menor e maior');
Teste::igual('&quot;aspas&quot;', e('"aspas"'), 'escapa aspas retas');
Teste::igual('&#039;', e("'"), 'escapa apostrofo');
Teste::igual('', e(null), 'nulo vira string vazia');
Teste::igual('Joao &amp; Maria', e('Joao & Maria'), 'escapa e comercial');

$token = csrf_token();
Teste::igual(64, strlen($token), 'o token tem 64 caracteres');
Teste::igual($token, csrf_token(), 'o token se mantem na mesma sessao');
Teste::verdade(str_contains(csrf_campo(), $token), 'o campo escondido carrega o token');

exit(Teste::resumo());
