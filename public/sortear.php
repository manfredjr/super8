<?php

require __DIR__ . '/cabecalho.php';

// O sorteio so aceita POST. Por GET, um link compartilhado no grupo do
// WhatsApp refaria o sorteio de quem clicasse - checagem antes de qualquer
// outra coisa, para nem gastar consulta com uma requisicao que ja vai ser
// recusada.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

csrf_conferir();

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);
$id = (int) ($_POST['id'] ?? 0);
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
exigirDonoDoCampeonato($pdo, $id, $usuario);

try {
    Campeonato::sortear($pdo, $id);
} catch (PDOException $excecaoBanco) {
    // Erro de banco nunca chega a tela de quem esta sorteando - PDOException
    // estende RuntimeException, entao esta captura tem que vir ANTES da
    // generica logo abaixo.
    error_log('sortear.php: falha de banco - ' . $excecaoBanco->getMessage());
    http_response_code(500);
    exit('Não foi possível concluir agora. Tente de novo.');
} catch (InvalidArgumentException | RuntimeException $excecao) {
    // Cobre tanto "nao tem 8 competidores" quanto "ja tem placar lancado" -
    // a tela ja esconde o botao nos dois casos, isso e o bloqueio para quem
    // forjar o POST mesmo assim.
    http_response_code(400);
    exit(e($excecao->getMessage()));
}

header('Location: chaveamento.php?id=' . $id);
exit;
