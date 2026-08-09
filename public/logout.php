<?php

require __DIR__ . '/cabecalho.php';

// Expira o cookie de sessao no navegador, com os MESMOS parametros com que
// session_set_cookie_params (config/sessao.php) o criou, antes de destruir a
// sessao no servidor. session_destroy() so apaga o arquivo de sessao no
// servidor; sem isto o navegador continua guardando o cookie antigo (ja
// inutil, mas presente) ate ele expirar sozinho.
if (ini_get('session.use_cookies')) {
    $parametrosCookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $parametrosCookie['path'],
        'domain'   => $parametrosCookie['domain'],
        'secure'   => $parametrosCookie['secure'],
        'httponly' => $parametrosCookie['httponly'],
        'samesite' => $parametrosCookie['samesite'],
    ]);
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
