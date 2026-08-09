<?php

require __DIR__ . '/cabecalho.php';

if (usuarioLogado() !== null) {
    header('Location: index.php');
    exit;
}

$erroEntrar = null;
$erroCadastro = null;
$emailEntrar = '';
$nomeCadastro = '';
$emailCadastro = '';
$versao = TERMO_VERSAO;
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_conferir();
    $acao = $_POST['acao'] ?? '';
    $email = (string) ($_POST['email'] ?? '');
    $senha = (string) ($_POST['senha'] ?? '');

    // Repovoa o formulario que foi de fato enviado, para quem errar um campo
    // nao precisar redigitar os outros. Nunca a senha, e nunca marcar o
    // aceite de volta: aceite pre-marcado nao e aceite.
    if ($acao === 'cadastrar') {
        $nomeCadastro = (string) ($_POST['nome'] ?? '');
        $emailCadastro = $email;
    } else {
        $emailEntrar = $email;
    }

    try {
        if ($acao === 'cadastrar') {
            if (($_POST['aceite'] ?? '') !== '1') {
                throw new InvalidArgumentException('É preciso aceitar o termo de uso para criar a conta.');
            }

            // A conta e o aceite comitam juntos. Sem isso pode existir conta real
            // sem o registro que sustenta a base legal do modelo de negocio.
            $pdo->beginTransaction();
            try {
                $novoId = Auth::cadastrar($pdo, $nomeCadastro, $emailCadastro, $senha);
                Auth::registrarAceite($pdo, $novoId, $versao, $_SERVER['REMOTE_ADDR'] ?? null);
                $pdo->commit();
            } catch (Throwable $erroTransacao) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $erroTransacao;
            }

            // Quem acabou de provar que e dono do e-mail, criando a conta agora,
            // nao pode herdar um bloqueio de tentativa de login anterior contra
            // esse mesmo e-mail - por exemplo, de quem tentou entrar antes de
            // perceber que ainda nao tinha cadastro. Sem isso a conta e o aceite
            // sao gravados e a pessoa sai da propria tela de cadastro bloqueada
            // da tela de login.
            Auth::limparFalhas($pdo, $emailCadastro);
        }

        $bloqueio = Auth::bloqueadoAte($pdo, $email);
        if ($bloqueio !== null) {
            $mensagemBloqueio = 'Muitas tentativas. Tente de novo depois das ' . substr($bloqueio, 11, 5) . '.';
            if ($acao === 'cadastrar') {
                $erroCadastro = $mensagemBloqueio;
            } else {
                $erroEntrar = $mensagemBloqueio;
            }
        } else {
            $usuario = Auth::autenticar($pdo, $email, $senha);
            if ($usuario === null) {
                Auth::registrarFalha($pdo, $email);
                $erroEntrar = 'E-mail ou senha inválidos.';
            } else {
                Auth::limparFalhas($pdo, $email);
                session_regenerate_id(true);
                $_SESSION['usuario'] = $usuario;
                header('Location: index.php');
                exit;
            }
        }
    } catch (PDOException $excecaoBanco) {
        // Erro de banco e detalhe interno - nome de tabela, SQLSTATE, texto do
        // driver - que nunca deve chegar a tela de quem esta tentando entrar
        // ou se cadastrar: nao ajuda em nada e ainda revela estrutura do banco.
        // PDOException estende RuntimeException, entao esta captura tem que vir
        // ANTES da generica logo abaixo - o PHP casa catch na ordem em que
        // aparecem no codigo, e a mais especifica perde se vier depois.
        error_log('login.php: falha de banco - ' . $excecaoBanco->getMessage());
        $mensagemGenerica = 'Não foi possível concluir agora. Tente de novo.';
        if ($acao === 'cadastrar') {
            $erroCadastro = $mensagemGenerica;
        } else {
            $erroEntrar = $mensagemGenerica;
        }
    } catch (InvalidArgumentException | RuntimeException $excecao) {
        // InvalidArgumentException e valor que o formulario podia ter conferido antes.
        // RuntimeException e o estado guardado recusando, como e-mail ja cadastrado.
        if ($acao === 'cadastrar') {
            $erroCadastro = $excecao->getMessage();
        } else {
            $erroEntrar = $excecao->getMessage();
        }
    }
}

renderizar('login', 'Entrar', [
    'erroEntrar'    => $erroEntrar,
    'erroCadastro'  => $erroCadastro,
    'emailEntrar'   => $emailEntrar,
    'nomeCadastro'  => $nomeCadastro,
    'emailCadastro' => $emailCadastro,
    'versao'        => $versao,
]);
