<?php

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_SENHA, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

/**
 * Versao de db() para um ponto de entrada que precisa da conexao logo no
 * inicio da pagina, antes de ter um try proprio no ar: banco fora do ar ou
 * credencial errada em config/config.php faz o new PDO() dentro de db()
 * lancar PDOException ali mesmo, e sem captura o servidor mostra a mensagem
 * do driver na tela (este XAMPP roda com display_errors ligado) - o mesmo
 * furo que public/login.php ja fecha para PDOException lancada depois de
 * conectar. Grava o erro real no log e interrompe a pagina com mensagem
 * generica, igual ao padrao de public/login.php.
 *
 * Nao e chamada de forma incondicional em public/cabecalho.php, antes de
 * cada ponto de entrada saber se precisa dela: termo.php e privacidade.php
 * nao usam banco nenhum, e o termo de uso - que por lei tem que continuar
 * legivel mesmo com o banco fora do ar - passaria a depender do banco por
 * nada. Cada ponto de entrada que de fato precisa do banco chama esta
 * funcao no lugar de db().
 */
function dbSeguro(): PDO
{
    try {
        return db();
    } catch (PDOException $excecao) {
        error_log('conexao com o banco: ' . $excecao->getMessage());
        http_response_code(500);
        exit('Não foi possível concluir agora. Tente de novo.');
    }
}
