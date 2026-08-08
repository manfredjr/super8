<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/csrf.php';

// M3 (rodada de revisao): so incluir csrf.php ja tem que deixar a sessao
// ativa, sem exigir uma chamada explicita de session_start()/iniciarSessao()
// - senao um ponto de entrada que esquecesse dessa chamada leria e
// escreveria em $_SESSION num array comum, que nunca persiste entre
// requisicoes, sem erro nenhum apontando o motivo. Nenhuma chamada de
// session_start() acontece neste arquivo: se esta asserticao passar, foi
// o require de csrf.php quem iniciou a sessao sozinho.
Teste::igual(
    PHP_SESSION_ACTIVE,
    session_status(),
    'M3: so incluir config/csrf.php ja inicia a sessao sozinho, sem exigir chamada explicita'
);

echo "CSRF e escape\n";

Teste::igual(PHP_SESSION_ACTIVE, session_status(), 'a sessao continua ativa mais adiante no script, nao e um array solto');
Teste::verdade(session_id() !== '', 'a sessao tem um id de verdade, nao esta vazia');

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
