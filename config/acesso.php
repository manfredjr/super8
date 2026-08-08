<?php

/**
 * Controle de acesso da camada web.
 *
 * Fica em config/ e nao em src/ porque le sessao, escreve cabecalho e interrompe
 * a pagina. As classes de src/ precisam continuar rodando por linha de comando.
 */

require_once __DIR__ . '/sessao.php';

// Chama no momento do include, e nao dentro de cada funcao: sem isso, um
// ponto de entrada que esquecesse de iniciar a sessao antes de usar as
// funcoes deste arquivo cairia num redirecionamento infinito para o login
// (ou pior, num $_SESSION que nunca persiste entre requisicoes) sem erro
// nenhum avisando o motivo.
iniciarSessao();

/** Copia da sessao, so para exibicao. Nao revalida nada no banco. */
function usuarioLogado(): ?array
{
    $usuario = $_SESSION['usuario'] ?? null;
    return is_array($usuario) ? $usuario : null;
}

/**
 * Interrompe a pagina e manda para o login se a sessao nao tiver um usuario
 * valido, ou se o usuario que ela aponta nao existir mais ou tiver sido
 * desativado. Le o registro de novo no banco a cada chamada, e nao confia na
 * copia parada em $_SESSION: sem isso, desativar uma conta nao tira o acesso
 * de quem ja estava logado, porque a sessao continuaria valendo ate expirar
 * sozinha. Devolve a linha lida agora, entao um e_organizador revogado
 * tambem passa a valer na hora.
 */
function exigirLogin(PDO $pdo): array
{
    $sessao = $_SESSION['usuario'] ?? null;
    $idSessao = is_array($sessao) ? ($sessao['id'] ?? null) : null;
    // ctype_digit trabalha sobre a forma em string, e nao is_numeric: is_numeric
    // aceita "12.7", "1e3" e espaco em volta, formas que nao servem como id de
    // linha. ctype_digit so aceita uma sequencia de digitos decimais, que e o
    // que "um id inteiro utilizavel" quer dizer de verdade. Restrito a int/string
    // antes de converter para nao arriscar "Array to string conversion" se
    // alguem colocar um array ali.
    if (
        !is_array($sessao)
        || !(is_int($idSessao) || is_string($idSessao))
        || !ctype_digit((string) $idSessao)
    ) {
        header('Location: login.php');
        exit;
    }

    $busca = $pdo->prepare(
        'SELECT id, google_id, nome, email, foto_url, e_organizador, ativo, criado_em
         FROM users WHERE id = ?'
    );
    $busca->execute([(int) $idSessao]);
    $usuario = $busca->fetch();

    if ($usuario === false || (int) $usuario['ativo'] !== 1) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: login.php');
        exit;
    }

    // Escreve a linha fresca de volta na sessao: sem isso, usuarioLogado()
    // (que so le a sessao, sem reconsultar o banco) continuaria servindo a
    // copia velha depois que exigirLogin ja releu um usuario diferente, e as
    // duas funcoes discordariam sobre quem esta logado.
    $_SESSION['usuario'] = $usuario;

    return $usuario;
}

/** Confere que o campeonato existe e pertence a quem esta logado. */
function exigirDonoDoCampeonato(PDO $pdo, int $campeonatoId): array
{
    $usuario = exigirLogin($pdo);

    $busca = $pdo->prepare('SELECT * FROM campeonatos WHERE id = ? AND organizador_id = ?');
    $busca->execute([$campeonatoId, (int) $usuario['id']]);
    $campeonato = $busca->fetch();

    if ($campeonato === false) {
        http_response_code(404);
        exit('Campeonato nao encontrado.');
    }

    return $campeonato;
}
