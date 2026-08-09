<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);
$rodadas = Campeonato::chaveamento($pdo, $id);

// Uma vez encerrado, Placar::gravar recusa qualquer gravacao (o motor).
// $encerrado, calculado aqui antes da view rodar, e o que tira o formulario
// de placar da tela antes de oferecer um botao que o motor ja vai recusar -
// mesma ideia de $somenteLeitura em campeonato.php e $jaSorteado em
// inscricoes.php.
$encerrado = $campeonato['status'] === 'encerrado';

// public/placar.php e um endpoint proprio, sem tela: quando ele recusa um
// placar (partida de outro campeonato, games fora da faixa, campeonato
// encerrado), volta pra ca com a mensagem numa leitura de sessao de uso
// unico, a mesma ideia do avisoSorteio de inscricoes.php/sortear.php. So
// aceita o aviso se for deste mesmo campeonato - sem essa checagem, abrir
// duas abas em campeonatos diferentes podia mostrar o aviso de um na tela
// do outro. unset() sempre, tenha batido o id ou nao, pra nao deixar sobra
// apontando pro campeonato errado.
$erro = null;
$erroClasse = 'erro';
if (isset($_SESSION['avisoPlacar']) && ($_SESSION['avisoPlacar']['id'] ?? null) === $id) {
    $erro = $_SESSION['avisoPlacar']['mensagem'];
    $erroClasse = $_SESSION['avisoPlacar']['classe'] ?? 'aviso';
}
unset($_SESSION['avisoPlacar']);

// Tela de operacao, usada em pe na beira da quadra. A marca fica discreta
// aqui para nao roubar espaco do placar (ver views/marca.php).
renderizar('chaveamento', 'Chaveamento de ' . $campeonato['nome'], [
    'campeonato' => $campeonato,
    'rodadas'    => $rodadas,
    'encerrado'  => $encerrado,
    'erro'       => $erro,
    'erroClasse' => $erroClasse,
], true);
