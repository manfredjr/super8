<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

// postInteiro(), nao (int) direto: mesma classe de furo documentada em
// config/csrf.php - um "id[]=x" ou "id=abc" forjado passaria reto por um
// (int) direto e caia num id de linha plausivel, embora nunca digitado de
// verdade.
$id = postInteiro('id', 0);

// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato. Essa
// checagem de posse acontece ANTES de olhar o metodo da requisicao (mais
// abaixo): quem nao e dono do campeonato recebe a mesma recusa (404),
// GET ou POST forjado, sem depender de qual metodo a requisicao usou.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);

// Mesma condicao larga que Campeonato::encerrar usa por dentro da propria
// trava para decidir se pode fechar (contrato documentado la): partida nem
// encerrada nem com nenhum dos dois games preenchidos.
$contaPendentes = $pdo->prepare(
    'SELECT COUNT(*) FROM partidas p JOIN rodadas r ON r.id = p.rodada_id
     WHERE r.campeonato_id = ? AND p.encerrada = 0 AND p.games_a IS NULL AND p.games_b IS NULL'
);
$contaPendentes->execute([$id]);
$pendentes = (int) $contaPendentes->fetchColumn();

$voltar = static function (string $classe, string $mensagem) use ($id): void {
    $_SESSION['avisoEncerramento'] = ['id' => $id, 'classe' => $classe, 'mensagem' => $mensagem];
    header('Location: classificacao.php?id=' . $id);
    exit;
};

// Refusal state antes do metodo: a MESMA condicao que esconde o botao em
// views/classificacao.php (status ja encerrado, ou ainda ha partida sem
// placar) e o que recusa aqui, para quem manda um POST forjado sem passar
// pela tela receber a mesma explicacao que o botao ausente ja da a quem
// esta olhando. Isso vale tanto para uma corrida real (segunda aba,
// segundo clique) quanto para um pedido forjado com um id que nunca chegou
// a esse estado. A trava de verdade continua sendo a de dentro de
// Campeonato::encerrar (SELECT ... FOR UPDATE); esta contagem, feita fora
// de qualquer trava, nunca substitui aquela - so evita gastar uma
// transacao inteira com um pedido que ja da pra saber, de graca, que vai
// ser recusado.
if ($campeonato['status'] === 'encerrado') {
    $voltar('aviso', 'Este campeonato já está encerrado.');
}
if ($pendentes > 0) {
    $voltar('aviso', 'Ainda há partida(s) sem placar lançado. Lance todos os resultados antes de encerrar.');
}

// O encerramento so aceita POST. Por GET, um link compartilhado (ou uma aba
// esquecida aberta na classificacao) encerraria o campeonato de quem
// clicasse - checagem depois da posse e do estado, mas antes do CSRF, para
// nao gastar a conferencia de token com uma requisicao que ja vai ser
// recusada de qualquer jeito. O cabecalho Allow diz qual metodo e o certo,
// mesma forma de public/sortear.php e public/placar.php.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

csrf_conferir();

// Encerrar e irreversivel na pratica: depois desta chamada o placar e a
// data do evento nao podem mais mudar (Placar::gravar e Campeonato::atualizar
// ja recusam campeonato encerrado), e o evento passa a contar no ranking
// acumulado. views/classificacao.php avisa isso ANTES do clique; aqui so
// resta executar.
try {
    Campeonato::encerrar($pdo, $id);
} catch (PDOException $excecaoBanco) {
    // Erro de banco nunca chega a tela de quem esta encerrando -
    // PDOException estende RuntimeException, entao esta captura tem que vir
    // ANTES da generica logo abaixo.
    error_log('encerrar.php: falha de banco - ' . $excecaoBanco->getMessage());
    $voltar('aviso', 'Não foi possível concluir agora. Tente de novo.');
} catch (RuntimeException $excecao) {
    // So chega aqui numa corrida de verdade: uma partida recebeu placar (ou
    // o proprio campeonato foi encerrado por outra aba) DEPOIS da
    // conferencia de $pendentes/$campeonato['status'] acima e ANTES da
    // trava de Campeonato::encerrar. A contagem de cima nao pega isso
    // porque nao esta sob a mesma trava; o motor pega, porque so ele trava
    // a linha do campeonato antes de contar de novo.
    $voltar('aviso', $excecao->getMessage());
}

$voltar('sucesso', 'Campeonato encerrado. Os resultados já entraram no ranking acumulado.');
