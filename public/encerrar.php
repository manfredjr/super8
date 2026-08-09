<?php

require __DIR__ . '/cabecalho.php';

// O encerramento so aceita POST. Por GET, um link compartilhado (ou uma aba
// esquecida aberta na classificacao) encerraria o campeonato de quem
// clicasse - checagem logo depois do require, antes de qualquer outra
// coisa, para nem gastar consulta com uma requisicao que ja vai ser
// recusada de qualquer jeito. Mesma posicao de public/sortear.php e
// public/placar.php (achado "Menor 2" da rodada de revisao: uma versao
// anterior deste arquivo checava posse e estado antes do metodo, o que
// empurrava csrf_conferir() para o fim e deixava um POST sem token mexer na
// mensagem de sessao antes do token ser conferido - inofensivo aqui porque
// as duas mensagens sao fixas e a escrita de verdade fica atras do token,
// mas de graca para evitar).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

csrf_conferir();

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

// postInteiro(), nao (int) direto: mesma classe de furo documentada em
// config/csrf.php - um "id[]=x" ou "id=abc" forjado passaria reto por um
// (int) direto e caia num id de linha plausivel, embora nunca digitado de
// verdade.
$id = postInteiro('id', 0);

// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);

// Campeonato::partidasPendentes(), a mesma chamada que classificacao.php
// usa para decidir o que mostrar - nao uma consulta copiada aqui. Ver o
// docblock do metodo (src/Campeonato.php) para o motivo de existir como
// metodo unico (achado "Importante 2" da rodada de revisao).
$pendentes = Campeonato::partidasPendentes($pdo, $id);

$voltar = static function (string $classe, string $mensagem) use ($id): void {
    $_SESSION['avisoEncerramento'] = ['id' => $id, 'classe' => $classe, 'mensagem' => $mensagem];
    header('Location: classificacao.php?id=' . $id);
    exit;
};

// Recusa precoce com a MESMA condicao que esconde o botao em
// views/classificacao.php (status ja encerrado, ou ainda ha partida sem
// placar): quem manda um POST forjado sem passar pela tela recebe a mesma
// explicacao que o botao ausente ja da a quem esta olhando. Isso vale tanto
// para uma corrida real (segunda aba, segundo clique) quanto para um pedido
// forjado. Nao repete aqui a checagem de "campeonato nunca sorteado" (total
// de partidas zero): esse caso fica so dentro de Campeonato::encerrar, e
// quem chega la sem sorteio recebe a mensagem do proprio motor pelo catch
// abaixo - repetir a mesma logica aqui so arriscaria as duas divergirem.
// A trava de verdade continua sendo a de dentro de Campeonato::encerrar
// (SELECT ... FOR UPDATE); esta contagem, feita fora de qualquer trava,
// nunca substitui aquela - so evita gastar uma transacao inteira com um
// pedido que ja da pra saber, de graca, que vai ser recusado.
if ($campeonato['status'] === 'encerrado') {
    $voltar('aviso', 'Este campeonato já está encerrado.');
}
if ($pendentes > 0) {
    $voltar('aviso', 'Ainda há partida(s) sem placar lançado. Lance todos os resultados antes de encerrar.');
}

// Encerrar e irreversivel na pratica: depois desta chamada os dados do
// evento e o placar nao podem mais mudar (Campeonato::atualizar e
// Placar::gravar ja recusam campeonato encerrado), e o evento passa a
// contar no ranking acumulado. views/classificacao.php avisa isso ANTES do
// clique; aqui so resta executar.
try {
    Campeonato::encerrar($pdo, $id);
} catch (PDOException $excecaoBanco) {
    // Erro de banco nunca chega a tela de quem esta encerrando -
    // PDOException estende RuntimeException, entao esta captura tem que vir
    // ANTES da generica logo abaixo.
    error_log('encerrar.php: falha de banco - ' . $excecaoBanco->getMessage());
    $voltar('aviso', 'Não foi possível concluir agora. Tente de novo.');
} catch (RuntimeException $excecao) {
    // Cobre tanto "o campeonato ainda nao foi sorteado" (a contagem de cima
    // nunca checa isso, de proposito - ver comentario acima) quanto uma
    // corrida de verdade: uma partida recebeu placar (ou o proprio
    // campeonato foi encerrado por outra aba) DEPOIS da conferencia de
    // $pendentes/$campeonato['status'] acima e ANTES da trava de
    // Campeonato::encerrar.
    $voltar('aviso', $excecao->getMessage());
}

$voltar('sucesso', 'Campeonato encerrado. Os resultados já entraram no ranking acumulado.');
