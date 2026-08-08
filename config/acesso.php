<?php

/**
 * Controle de acesso da camada web.
 *
 * Fica em config/ e nao em src/ porque le sessao, escreve cabecalho e interrompe
 * a pagina. As classes de src/ precisam continuar rodando por linha de comando.
 */

function usuarioLogado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** Interrompe a pagina e manda para o login se nao houver sessao. */
function exigirLogin(): array
{
    $usuario = usuarioLogado();
    if ($usuario === null) {
        header('Location: login.php');
        exit;
    }
    return $usuario;
}

/** Confere que o campeonato existe e pertence a quem esta logado. */
function exigirDonoDoCampeonato(PDO $pdo, int $campeonatoId): array
{
    $usuario = exigirLogin();

    $busca = $pdo->prepare('SELECT * FROM campeonatos WHERE id = ? AND organizador_id = ?');
    $busca->execute([$campeonatoId, (int) $usuario['id']]);
    $campeonato = $busca->fetch();

    if ($campeonato === false) {
        http_response_code(404);
        exit('Campeonato nao encontrado.');
    }

    return $campeonato;
}
