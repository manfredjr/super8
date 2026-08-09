<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$campeonato = null;
$erro = null;

if ($id > 0) {
    $campeonato = exigirDonoDoCampeonato($pdo, $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_conferir();

    $dados = [
        'nome'        => (string) ($_POST['nome'] ?? ''),
        'data_evento' => (string) ($_POST['data_evento'] ?? ''),
        'local'       => (string) ($_POST['local'] ?? ''),
        'custo'       => (string) ($_POST['custo'] ?? ''),
        'descricao'   => (string) ($_POST['descricao'] ?? ''),
    ];

    // Repovoa o formulario com o que foi de fato enviado, para quem errar um
    // campo nao precisar redigitar os outros quatro. Campeonato::criar e
    // atualizar ja validam nome, data, local e custo e lancam com a
    // mensagem certa; nao ha checagem duplicada aqui.
    $campeonato = $dados;

    try {
        if ($id > 0) {
            Campeonato::atualizar($pdo, $id, (int) $usuario['id'], $dados);
        } else {
            $id = Campeonato::criar($pdo, (int) $usuario['id'], $dados);
        }

        header('Location: inscricoes.php?id=' . $id);
        exit;
    } catch (PDOException $excecaoBanco) {
        // Erro de banco nunca chega a tela de quem esta criando ou editando
        // um campeonato - PDOException estende RuntimeException, entao esta
        // captura tem que vir ANTES da generica logo abaixo.
        error_log('campeonato.php: falha de banco - ' . $excecaoBanco->getMessage());
        $erro = 'Não foi possível concluir agora. Tente de novo.';
    } catch (InvalidArgumentException | RuntimeException $excecao) {
        $erro = $excecao->getMessage();
    }
}

$titulo = $id > 0 ? 'Editar campeonato' : 'Novo campeonato';

renderizar('campeonato_form', $titulo, [
    'campeonato' => $campeonato,
    'erro'       => $erro,
]);
