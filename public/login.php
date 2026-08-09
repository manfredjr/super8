<?php

require __DIR__ . '/cabecalho.php';

if (usuarioLogado() !== null) {
    header('Location: index.php');
    exit;
}

$erro = null;
$versao = TERMO_VERSAO;
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_conferir();
    $acao = $_POST['acao'] ?? '';
    $email = (string) ($_POST['email'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');

    try {
        if ($acao === 'cadastrar') {
            if (($_POST['aceite'] ?? '') !== '1') {
                throw new InvalidArgumentException('E preciso aceitar o termo de uso para criar a conta.');
            }

            // A conta e o aceite comitam juntos. Sem isso pode existir conta real
            // sem o registro que sustenta a base legal do modelo de negocio.
            $pdo->beginTransaction();
            try {
                $novoId = Auth::cadastrar($pdo, (string) ($_POST['nome'] ?? ''), $email, $senha);
                Auth::registrarAceite($pdo, $novoId, $versao, $_SERVER['REMOTE_ADDR'] ?? null);
                $pdo->commit();
            } catch (Throwable $erroCadastro) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $erroCadastro;
            }
        }

        $bloqueio = Auth::bloqueadoAte($pdo, $email);
        if ($bloqueio !== null) {
            $erro = 'Muitas tentativas. Tente de novo depois das ' . substr($bloqueio, 11, 5) . '.';
        } else {
            $usuario = Auth::autenticar($pdo, $email, $senha);
            if ($usuario === null) {
                Auth::registrarFalha($pdo, $email);
                $erro = 'E-mail ou senha invalidos.';
            } else {
                Auth::limparFalhas($pdo, $email);
                session_regenerate_id(true);
                $_SESSION['usuario'] = $usuario;
                header('Location: index.php');
                exit;
            }
        }
    } catch (InvalidArgumentException | RuntimeException $excecao) {
        // InvalidArgumentException e valor que o formulario podia ter conferido antes.
        // RuntimeException e o estado guardado recusando, como e-mail ja cadastrado.
        $erro = $excecao->getMessage();
    }
}

$titulo = 'Entrar';
ob_start();
require __DIR__ . '/../views/login.php';
$conteudo = ob_get_clean();
require __DIR__ . '/../views/layout.php';
