<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = getInteiro('id');
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);
$nomeDigitado = '';
$emailDigitado = '';

// public/sortear.php e um endpoint proprio, sem tela: quando ele recusa um
// sorteio (ja tem placar, por exemplo), volta pra ca com a mensagem numa
// leitura de sessao de uso unico. lerAviso() (config/renderizar.php) e o
// ajudante comum a esta tela e a chaveamento.php.
['erro' => $erro, 'erroClasse' => $erroClasse] = lerAviso('avisoSorteio', $id);

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
            // postInteiro(), nao (int) direto: mesma classe de furo do id de
            // campeonato la em cima, so que em POST - "inscricao_id[]=x" ou
            // "inscricao_id=abc" forjado passaria reto por um (int) direto e
            // caia num id de linha plausivel, embora nunca digitado de verdade.
            Campeonato::removerInscricao($pdo, $id, postInteiro('inscricao_id', 0));
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
            // Repovoa os campos com o que foi de fato enviado, para quem
            // esbarrar num nome repetido ou num e-mail sem conta nao
            // precisar redigitar tudo - mesma ideia de campeonato.php ao
            // repovoar $dados antes do try.
            $nomeDigitado = postTexto('nome_exibicao');
            $emailDigitado = postTexto('email_jogador');
            // inscreverComEmail() (src/Campeonato.php) e quem resolve o
            // jogador_id a partir do e-mail: em branco vira convidado, do
            // jeito que sempre foi; preenchido sem conta ativa correspondente
            // lanca InvalidArgumentException, capturada abaixo do mesmo jeito
            // que qualquer outro valor mal digitado neste formulario.
            Campeonato::inscreverComEmail($pdo, $id, $nomeDigitado, $emailDigitado);
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
    'jaSorteado'    => $jaSorteado,
    'temPlacar'     => $temPlacar,
    'nomeDigitado'  => $nomeDigitado,
    'emailDigitado' => $emailDigitado,
]);
