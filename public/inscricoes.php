<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);
$erro = null;
$erroClasse = 'erro';
$nomeDigitado = '';

// public/sortear.php e um endpoint proprio, sem tela: quando ele recusa um
// sorteio (ja tem placar, por exemplo), volta pra ca com a mensagem numa
// leitura de sessao de uso unico, em vez de responder uma frase solta sem
// layout nem caminho de volta. So aceita a mensagem se for deste mesmo
// campeonato - sem essa checagem, abrir duas abas em campeonatos diferentes
// podia mostrar o aviso de um na tela do outro. unset() sempre, tenha
// batido o id ou nao, pra nao deixar sobra apontando pro campeonato errado.
if (isset($_SESSION['avisoSorteio']) && ($_SESSION['avisoSorteio']['id'] ?? null) === $id) {
    $erro = $_SESSION['avisoSorteio']['mensagem'];
    $erroClasse = 'aviso';
}
unset($_SESSION['avisoSorteio']);

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
// $temPlacar ja implica $jaSorteado (o && esta na propria atribuicao), a
// view nao precisa repetir a checagem.
$temPlacar = $jaSorteado && Campeonato::temPlacarLancado($pdo, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_conferir();
    try {
        if (($_POST['acao'] ?? '') === 'remover') {
            if ($jaSorteado) {
                throw new RuntimeException('Não é possível remover um competidor depois do sorteio.');
            }
            // ?? 0, nao so (int) direto: sem o valor padrao, forjar
            // acao=remover sem inscricao_id lanca "Undefined array key",
            // que vaza o caminho do servidor no aviso do PHP antes mesmo
            // de chegar no catch.
            Campeonato::removerInscricao($pdo, $id, (int) ($_POST['inscricao_id'] ?? 0));
        } else {
            // postTexto(), nao (string) ($_POST[...] ?? ''): um campo
            // enviado como array ("nome_exibicao[]=x") faz (string) emitir
            // "Array to string conversion" e devolver o literal "Array",
            // que passa reto pela validacao de tamanho e cadastra esse
            // texto errado de verdade. postTexto() (config/csrf.php) fecha
            // isso devolvendo string vazia pra qualquer tipo que nao seja
            // string, e a validacao de campo obrigatorio recusa do jeito
            // certo.
            //
            // Repovoa o campo com o que foi de fato enviado, para quem
            // esbarrar num nome repetido nao precisar redigitar - mesma
            // ideia de campeonato.php ao repovoar $dados antes do try.
            $nomeDigitado = postTexto('nome_exibicao');
            Campeonato::inscrever($pdo, $id, $nomeDigitado, null);
        }
        header('Location: inscricoes.php?id=' . $id);
        exit;
    } catch (PDOException $excecaoBanco) {
        // Erro de banco nunca chega a tela de quem esta cadastrando
        // competidor - PDOException estende RuntimeException, entao esta
        // captura tem que vir ANTES das duas de baixo.
        error_log('inscricoes.php: falha de banco - ' . $excecaoBanco->getMessage());
        $erro = 'Não foi possível concluir agora. Tente de novo.';
        $erroClasse = 'aviso';
    } catch (InvalidArgumentException $excecao) {
        // Valor que o proprio formulario podia ter conferido antes de
        // enviar (nome vazio, nome grande demais) - erro de quem digitou.
        $erro = $excecao->getMessage();
        $erroClasse = 'erro';
    } catch (RuntimeException $excecao) {
        // Estado que o banco ja guardava e a operacao esbarrou nele (limite
        // de 8, nome duplicado, sorteio ja feito) - nao e engano de quem
        // digitou, e estado do campeonato. Mesma distincao que
        // campeonato_form.php ja faz para "campeonato encerrado".
        $erro = $excecao->getMessage();
        $erroClasse = 'aviso';
    }
}

$inscricoes = Campeonato::listarInscricoes($pdo, $id);

renderizar('inscricoes', 'Competidores de ' . $campeonato['nome'], [
    'campeonato'   => $campeonato,
    'inscricoes'   => $inscricoes,
    'erro'         => $erro,
    'erroClasse'   => $erroClasse,
    'jaSorteado'   => $jaSorteado,
    'temPlacar'    => $temPlacar,
    'nomeDigitado' => $nomeDigitado,
]);
