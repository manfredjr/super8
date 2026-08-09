<?php

require __DIR__ . '/cabecalho.php';

// O placar so aceita POST. Por GET, um link compartilhado no grupo do
// WhatsApp gravaria placar de quem clicasse - checagem antes de qualquer
// outra coisa, para nem gastar consulta com uma requisicao que ja vai ser
// recusada. O cabecalho Allow diz qual metodo e o certo, em vez de deixar
// quem recebeu o 405 adivinhar. Mesma forma de public/sortear.php.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

csrf_conferir();

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

// postInteiro(), nao (int) direto: (int) sobre um array nao vazio vira 1, e
// (int) sobre uma string que nao comeca com digito vira 0, as duas em
// silencio - um "campeonato_id[]=x" ou "partida_id=abc" forjado passaria
// reto por um (int) direto e caia num id de linha plausivel, embora nunca
// digitado de verdade. postInteiro() (config/csrf.php) so aceita string de
// digitos; qualquer outra coisa vira o padrao, aqui 0 - um id que nunca
// existe, entao exigirDonoDoCampeonato()/Placar::gravar recusam do jeito
// certo mais abaixo.
$campeonatoId = postInteiro('campeonato_id', 0);
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
exigirDonoDoCampeonato($pdo, $campeonatoId, $usuario);

$partidaId = postInteiro('partida_id', 0);
// -1, e nao 0: 0 games e um placar valido de verdade (6x0 acontece), entao
// um padrao de 0 para um campo ausente ou do tipo errado ("games_a[]=9",
// "games_a=abc") gravaria silenciosamente um resultado plausivel em vez de
// recusar. -1 cai fora da faixa 0-99 que Placar::gravar aceita, e garante
// que a ausencia (ou o tipo errado) do campo seja sempre recusada, nunca
// interpretada como "zero games".
$gamesA = postInteiro('games_a');
$gamesB = postInteiro('games_b');

// Guarda a mensagem numa leitura de sessao de uso unico e volta para o
// chaveamento, em vez de responder uma frase solta sem layout nem caminho
// de volta - mesma ideia de public/sortear.php ao recusar um sorteio. O
// fragmento #partida-ID leva de volta exatamente para a partida que falhou.
$voltar = static function (string $classe, string $mensagem) use ($campeonatoId, $partidaId): void {
    $_SESSION['avisoPlacar'] = ['id' => $campeonatoId, 'classe' => $classe, 'mensagem' => $mensagem];
    header('Location: chaveamento.php?id=' . $campeonatoId . '#partida-' . $partidaId);
    exit;
};

// A conferencia de que a partida pertence a ESTE campeonato acontece dentro
// de Placar::gravar, sob a trava da linha do campeonato - repetir a
// conferencia aqui, fora da trava, nao acrescenta nada. A mensagem de
// recusa nao diz se a partida existe em outro campeonato.
//
// O UPDATE de promocao de status mora DENTRO deste mesmo try, depois de
// Placar::gravar: e uma escrita no banco como qualquer outra, e uma
// PDOException lancada ali (deadlock, conexao caida) tem exatamente o
// mesmo risco de vazar o driver pra tela que o motivo desta funcao existir
// - so que pior, porque acontece DEPOIS do placar ja ter sido gravado com
// sucesso, e sem essa captura o organizador ficaria numa pagina de erro
// crua sem saber se o placar entrou ou nao.
try {
    Placar::gravar($pdo, $campeonatoId, $partidaId, $gamesA, $gamesB, (int) $usuario['id']);

    $pdo->prepare("UPDATE campeonatos SET status = 'em_andamento' WHERE id = ? AND status = 'sorteado'")
        ->execute([$campeonatoId]);
} catch (PDOException $excecaoBanco) {
    // Erro de banco nunca chega a tela de quem esta lancando placar -
    // PDOException estende RuntimeException, entao esta captura tem que vir
    // ANTES das duas de baixo.
    error_log('placar.php: falha de banco - ' . $excecaoBanco->getMessage());
    $voltar('aviso', 'Não foi possível concluir agora. Tente de novo.');
} catch (InvalidArgumentException $excecao) {
    // Games fora de 0-99 - valor que o proprio formulario podia ter
    // conferido antes de enviar (so chega aqui por POST forjado, o campo
    // number do formulario ja limita min/max no navegador).
    $voltar('erro', $excecao->getMessage());
} catch (RuntimeException $excecao) {
    // Campeonato encerrado ou partida que nao pertence a este campeonato -
    // estado que o banco ja guardava recusando, nao engano de quem digitou.
    $voltar('aviso', $excecao->getMessage());
}

// Confirmacao curta e neutra do sucesso: sem ela, a unica diferenca visivel
// depois de gravar e o rotulo do botao trocar de "Gravar placar" para
// "Corrigir placar" - confirmacao fraca na decima quarta partida, no sol,
// na beira da quadra.
$voltar('sucesso', 'Placar gravado.');
