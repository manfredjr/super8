<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);
$erro = null;
$nomeDigitado = '';

// Uma vez sorteado, toda inscricao passa a ser referenciada por alguma
// partida (dupla_a_j1, dupla_a_j2, dupla_b_j1, dupla_b_j2), e
// removerInscricao ja recusa por violacao de chave estrangeira - o motor
// bloqueia sozinho. $jaSorteado calculado aqui, antes do metodo, e o que
// tira o botao "Tirar" da tela antes de oferecer uma acao que o motor vai
// recusar de qualquer jeito (mesma ideia de $somenteLeitura em
// campeonato.php), e da a uma requisicao forjada a mesma barreira que o
// botao escondido.
$jaSorteado = $campeonato['seed_sorteio'] !== null;

// Mesmo raciocinio para o sorteio: refazer com placar ja lancado apaga
// rodadas e partidas com resultado gravado, e Campeonato::sortear ja
// recusa isso. So faz sentido perguntar se ha placar quando ja houve
// sorteio - antes disso nao existe partida nenhuma para ter placar.
$temPlacar = $jaSorteado && Campeonato::temPlacarLancado($pdo, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_conferir();
    try {
        if (($_POST['acao'] ?? '') === 'remover') {
            if ($jaSorteado) {
                throw new RuntimeException('Não é possível remover um competidor depois do sorteio.');
            }
            Campeonato::removerInscricao($pdo, $id, (int) $_POST['inscricao_id']);
        } else {
            // Repovoa o campo com o que foi de fato enviado, para quem
            // esbarrar num nome repetido nao precisar redigitar - mesma
            // ideia de campeonato.php ao repovoar $dados antes do try.
            $nomeDigitado = (string) ($_POST['nome_exibicao'] ?? '');
            Campeonato::inscrever($pdo, $id, $nomeDigitado, null);
        }
        header('Location: inscricoes.php?id=' . $id);
        exit;
    } catch (PDOException $excecaoBanco) {
        // Erro de banco nunca chega a tela de quem esta cadastrando
        // competidor - PDOException estende RuntimeException, entao esta
        // captura tem que vir ANTES da generica logo abaixo.
        error_log('inscricoes.php: falha de banco - ' . $excecaoBanco->getMessage());
        $erro = 'Não foi possível concluir agora. Tente de novo.';
    } catch (InvalidArgumentException | RuntimeException $excecao) {
        $erro = $excecao->getMessage();
    }
}

$inscricoes = Campeonato::listarInscricoes($pdo, $id);

renderizar('inscricoes', 'Competidores de ' . $campeonato['nome'], [
    'campeonato'    => $campeonato,
    'inscricoes'    => $inscricoes,
    'erro'          => $erro,
    'jaSorteado'    => $jaSorteado,
    'temPlacar'     => $temPlacar,
    'nomeDigitado'  => $nomeDigitado,
]);
