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

$campeonatoId = (int) ($_POST['campeonato_id'] ?? 0);
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
exigirDonoDoCampeonato($pdo, $campeonatoId, $usuario);

$partidaId = (int) ($_POST['partida_id'] ?? 0);
// -1, e nao 0: 0 games e um placar valido de verdade (6x0 acontece), entao
// um valor padrao de 0 para um campo ausente gravaria silenciosamente um
// resultado plausivel para um POST forjado sem o campo. -1 cai fora da
// faixa 0-99 que Placar::gravar aceita, e garante que a ausencia do campo
// seja sempre recusada, nunca interpretada como "zero games".
$gamesA = (int) ($_POST['games_a'] ?? -1);
$gamesB = (int) ($_POST['games_b'] ?? -1);

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
try {
    Placar::gravar($pdo, $campeonatoId, $partidaId, $gamesA, $gamesB, (int) $usuario['id']);
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

$pdo->prepare("UPDATE campeonatos SET status = 'em_andamento' WHERE id = ? AND status = 'sorteado'")
    ->execute([$campeonatoId]);

header('Location: chaveamento.php?id=' . $campeonatoId . '#partida-' . $partidaId);
exit;
