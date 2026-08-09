<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = getInteiro('id');
$campeonato = null;
$erro = null;
$somenteLeitura = false;

if ($id > 0) {
    // Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
    // segunda consulta identica a users dentro de exigirDonoDoCampeonato.
    $campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);

    // Campeonato::atualizar ja recusa gravar um campeonato encerrado, mas
    // recusar so no motor deixa a tela oferecer um formulario editavel e um
    // botao Salvar que nunca vai funcionar: quem preenche os 5 campos so
    // descobre a recusa depois de mandar, sem outra saida a nao ser
    // abandonar a pagina. $somenteLeitura tira o botao da view antes disso,
    // e cobre tanto quem chega pelo link da lista quanto quem entra direto
    // pela URL com o id.
    if ($campeonato['status'] === 'encerrado') {
        $somenteLeitura = true;
        $erro = 'Este campeonato está encerrado e não pode mais ser editado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$somenteLeitura) {
    csrf_conferir();

    // postTexto(), nao (string) ($_POST[...] ?? ''): o `??` cobre chave
    // ausente, mas nao cobre um campo enviado como array
    // ("nome[]=x", por exemplo) - (string) sobre array emite "Array to
    // string conversion", vaza o caminho do servidor no aviso, e devolve o
    // literal "Array", que passaria reto pela validacao de tamanho.
    $dados = [
        'nome'        => postTexto('nome'),
        'data_evento' => postTexto('data_evento'),
        'local'       => postTexto('local'),
        'custo'       => postTexto('custo'),
        'descricao'   => postTexto('descricao'),
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

if ($somenteLeitura) {
    $titulo = 'Campeonato encerrado';
} elseif ($id > 0) {
    $titulo = 'Editar campeonato';
} else {
    $titulo = 'Novo campeonato';
}

renderizar('campeonato_form', $titulo, [
    'campeonato'     => $campeonato,
    'erro'           => $erro,
    'somenteLeitura' => $somenteLeitura,
]);
