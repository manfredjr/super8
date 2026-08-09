<?php

require_once __DIR__ . '/config.php';

// O servidor nunca define fuso proprio, entao PHP cai no padrao do sistema
// operacional onde roda - Europe/Berlin neste XAMPP, UTC de verdade num VPS
// comum. Isso muda o resultado de date('Y-m-01')/date('Y-m-t') que
// public/ranking.php usa para os atalhos "Mes atual"/"Ano atual": entre 21h
// e meia-noite do ultimo dia do mes, um servidor em UTC ja acha que o mes
// seguinte comecou, e um campeonato encerrado naquele dia some do atalho
// "Mes atual" sem nenhuma mudanca no campeonato. Fixar aqui, no arquivo que
// toda tela carrega antes de qualquer date(), faz todas concordarem com o
// fuso de quem realmente usa o sistema.
date_default_timezone_set('America/Sao_Paulo');

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
