<?php

require_once __DIR__ . '/config.php';

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Sem isso o PHP aceita um identificador de sessao que ele nunca emitiu,
    // que e o vetor classico de fixacao de sessao.
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => COOKIE_SEGURO,
    ]);

    session_start();
}
