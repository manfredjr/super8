<?php

/**
 * Controle de acesso da camada web.
 *
 * Fica em config/ e nao em src/ porque le sessao, escreve cabecalho e interrompe
 * a pagina. As classes de src/ precisam continuar rodando por linha de comando.
 */

require_once __DIR__ . '/sessao.php';

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
    if (!is_array($sessao) || !isset($sessao['id']) || !is_numeric($sessao['id'])) {
        header('Location: login.php');
        exit;
    }

    $busca = $pdo->prepare(
        'SELECT id, google_id, nome, email, foto_url, e_organizador, ativo, criado_em
         FROM users WHERE id = ?'
    );
    $busca->execute([(int) $sessao['id']]);
    $usuario = $busca->fetch();

    if ($usuario === false || (int) $usuario['ativo'] !== 1) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        header('Location: login.php');
        exit;
    }

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
