<?php

require_once __DIR__ . '/../config/sessao.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/acesso.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/renderizar.php';
// marcaMt() precisa existir ANTES de qualquer view rodar, nao so antes do
// layout: renderizar() executa a view (que pode chamar marcaMt() no meio do
// proprio conteudo) e so depois inclui views/layout.php. Se este require
// ficasse so dentro do layout, marcaMt() seria "Call to undefined function"
// para qualquer view que a chamasse antes do rodape - exatamente o que a
// tela de classificacao vai precisar fazer.
require_once __DIR__ . '/../views/marca.php';
require_once __DIR__ . '/../src/Validador.php';
require_once __DIR__ . '/../src/Rodizio.php';
require_once __DIR__ . '/../src/Sorteio.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Campeonato.php';
require_once __DIR__ . '/../src/Placar.php';
require_once __DIR__ . '/../src/Ranking.php';

// A sessao ja foi iniciada pelo include de acesso.php. Nenhum ponto de entrada
// deve chamar session_start por conta propria.

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
