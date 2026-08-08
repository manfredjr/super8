<?php

require_once __DIR__ . '/sessao.php';

// Chama no momento do include, pelo mesmo motivo de config/acesso.php: este
// arquivo le e escreve $_SESSION (csrf_token/csrf_conferir) sem exigir
// sessao.php antes, e um ponto de entrada que esquecesse de iniciar a sessao
// sozinho falharia em silencio, sem erro nenhum apontando o motivo.
iniciarSessao();

function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_conferir(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(403);
        exit('Pedido invalido. Recarregue a pagina e tente de novo.');
    }
}
