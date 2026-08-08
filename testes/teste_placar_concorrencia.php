<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Validador.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';
require __DIR__ . '/../src/Placar.php';

echo "Placar concorrencia\n";

// Este arquivo NAO roda dentro de uma unica transacao com rollback no fim,
// diferente de teste_placar_persistencia.php: o cenario abaixo precisa de um
// processo filho de verdade segurando uma trava, para reproduzir a janela de
// concorrencia. A limpeza e manual, feita no register_shutdown_function
// abaixo, que tambem forca saida diferente de zero se o script nao chegar ao
// proprio fim (mesma tecnica de testes/teste_campeonato_concorrencia.php).

$pdo = db();

$idsCampeonatosCriados = [];
$idsUsuariosCriados = [];
$terminouDireito = false;

register_shutdown_function(function () use (&$terminouDireito, &$idsCampeonatosCriados, &$idsUsuariosCriados, $pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($idsCampeonatosCriados as $campeonatoId) {
        $pdo->prepare('DELETE p FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?')
            ->execute([$campeonatoId]);
        $pdo->prepare('DELETE FROM rodadas WHERE campeonato_id = ?')->execute([$campeonatoId]);
        $pdo->prepare('DELETE FROM inscricoes WHERE campeonato_id = ?')->execute([$campeonatoId]);
        $pdo->prepare('DELETE FROM campeonatos WHERE id = ?')->execute([$campeonatoId]);
    }
    foreach ($idsUsuariosCriados as $usuarioId) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$usuarioId]);
    }
    if (!$terminouDireito) {
        exit(9);
    }
});

$organizadorId = Auth::cadastrar(
    $pdo,
    'Organizador Placar Concorrencia',
    'placarconcorrencia' . random_int(1000, 9999) . '@exemplo.com',
    'senhaforte123'
);
$idsUsuariosCriados[] = $organizadorId;

// --- gravar() trava a linha do campeonato antes de escrever ---------------
// Task 7 documentou o contrato no docblock de Campeonato::sortear: QUALQUER
// codigo que grave um placar precisa travar a mesma linha do campeonato (o
// SELECT ... FOR UPDATE) antes de escrever, na mesma ordem que sortear(),
// inscrever() e removerInscricao() ja usam. Placar::gravar grava um placar,
// entao tem que seguir esse contrato. Prova disso: um processo auxiliar
// segura a trava do campeonato por 2 segundos; Placar::gravar, chamado nesta
// conexao enquanto isso, tem que ficar bloqueado ate o auxiliar soltar a
// trava, e so entao gravar o placar.

$campeonatoId = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Trava de placar sob concorrencia',
    'data_evento' => '2026-09-11',
    'local'       => 'X',
    'custo'       => '',
    'descricao'   => '',
]);
$idsCampeonatosCriados[] = $campeonatoId;
foreach (range(1, 8) as $n) {
    Campeonato::inscrever($pdo, $campeonatoId, "Jogador concorrencia {$n}", null);
}
Campeonato::sortear($pdo, $campeonatoId, 3131);

$buscaPrimeiraPartida = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ? ORDER BY r.numero, p.quadra LIMIT 1'
);
$buscaPrimeiraPartida->execute([$campeonatoId]);
$idPrimeiraPartida = (int) $buscaPrimeiraPartida->fetchColumn();

$descritores = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$processo = proc_open(
    [PHP_BINARY, __DIR__ . '/_ajuda_segura_trava_campeonato.php', (string) $campeonatoId, '2'],
    $descritores,
    $canos
);
Teste::verdade(is_resource($processo), 'consegue iniciar o processo auxiliar que segura a trava do campeonato');

// Bloqueia ate o auxiliar avisar que ja segura a trava (ou ate ele fechar o
// pipe, se algo der errado) - nao um sleep as cegas do lado de fora.
$marcador = fgets($canos[1]);
Teste::igual("TRAVA_OK\n", $marcador, 'o processo auxiliar avisa quando ja segura a trava do campeonato');

$inicio = microtime(true);
Placar::gravar($pdo, $campeonatoId, $idPrimeiraPartida, 6, 2, $organizadorId);
$duracaoBloqueio = microtime(true) - $inicio;

// Mesma ressalva do teste analogo em Campeonato: o tempo sozinho nao isola
// qual trava causou o bloqueio (o UPDATE final tambem esbarraria num lock
// implicito de linha, se o auxiliar tivesse tocado na propria partida). A
// prova de que E a trava do campeonato (SELECT ... FOR UPDATE) que serializa
// aqui, e nao um lock incidental de outra coisa, esta no fato de que o
// auxiliar SO toca na linha de campeonatos - ele nunca le nem escreve em
// partidas. Se Placar::gravar nao travasse essa mesma linha, nada aqui
// deveria bloquear, e a chamada acima voltaria quase instantanea.
Teste::verdade(
    $duracaoBloqueio >= 1.0,
    "Placar::gravar demorou para retornar, evidencia de que travou a mesma linha do campeonato que o auxiliar segura (esperou {$duracaoBloqueio}s, o auxiliar segura por 2s)"
);

$saidaAuxiliar = stream_get_contents($canos[1]);
$erroAuxiliar = stream_get_contents($canos[2]);
fclose($canos[0]);
fclose($canos[1]);
fclose($canos[2]);
$codigoAuxiliar = proc_close($processo);
Teste::igual(0, $codigoAuxiliar, 'o processo auxiliar termina sem erro (stderr: ' . $erroAuxiliar . ')');
Teste::verdade(
    str_contains($saidaAuxiliar, 'COMMITOU'),
    'o processo auxiliar chegou a comitar (soltar a trava) antes de Placar::gravar retornar'
);

$lida = $pdo->prepare('SELECT games_a, games_b, encerrada FROM partidas WHERE id = ?');
$lida->execute([$idPrimeiraPartida]);
$linhaLida = $lida->fetch();
Teste::igual(6, (int) $linhaLida['games_a'], 'depois de destravar, o placar foi gravado corretamente (games_a)');
Teste::igual(2, (int) $linhaLida['games_b'], 'depois de destravar, o placar foi gravado corretamente (games_b)');
Teste::igual(1, (int) $linhaLida['encerrada'], 'depois de destravar, a partida ficou marcada como encerrada');

// --- Cenario 2: gravar() com um retrato (snapshot) anterior a criacao da
// partida ainda assim trava, nunca escreve destravado ------------------------
// Achado original da revisao (Important 3, agora reconfirmado apos gravar()
// passar a receber $campeonatoId como parametro, em vez de resolve-lo a
// partir da partida): tanto a trava do campeonato (passo 1) quanto a
// confirmacao de que a partida pertence a ele (passo 2, com JOIN partidas ->
// rodadas) sao leituras TRAVADAS (FOR UPDATE), incondicionais - nenhuma das
// duas fica atras de um "if" que possa deixar de disparar. Isso importa
// porque sob REPEATABLE READ, uma transacao de quem chama cujo retrato seja
// anterior a CRIACAO do campeonato/sorteio nunca enxergaria essas linhas com
// uma leitura comum - o retrato fica congelado no inicio da transacao, mesmo
// que as linhas ja existam de verdade no banco (comitadas por outra conexao
// depois). Na versao com o defeito original (antes desta tarefa), isso fazia
// a trava do campeonato nem disparar, e o UPDATE final (que E uma leitura
// corrente, enxerga a linha igual) gravava o placar mesmo assim - sem
// NENHUMA trava. O revisor reproduziu isso ao vivo: gravar() voltando em
// 0.001s enquanto outra conexao segurava a trava do campeonato por 3s.

$pdo->beginTransaction();
// Leitura comum qualquer, ANTES do campeonato/sorteio existirem: fixa o
// retrato REPEATABLE READ de $pdo num momento em que esta partida ainda nao
// existe (mesma tecnica da Task 7, cenario 2 de
// teste_campeonato_concorrencia.php: Campeonato::buscar antes da escrita
// alheia).
$pdo->query('SELECT COUNT(*) FROM campeonatos');

$dsnSegunda = 'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4';
$pdo2 = new PDO($dsnSegunda, DB_USER, DB_SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// $pdo2 fica em autocommit: tudo que criarmos aqui fica comitado de verdade
// assim que executa, DEPOIS do retrato que $pdo acabou de fixar.

$organizadorId2 = Auth::cadastrar(
    $pdo2,
    'Organizador Retrato Antigo',
    'retratoantigo' . random_int(1000, 9999) . '@exemplo.com',
    'senhaforte123'
);
$idsUsuariosCriados[] = $organizadorId2;

$campeonatoRetratoAntigo = Campeonato::criar($pdo2, $organizadorId2, [
    'nome'        => 'Retrato antigo sob REPEATABLE READ',
    'data_evento' => '2026-09-12',
    'local'       => 'X',
    'custo'       => '',
    'descricao'   => '',
]);
$idsCampeonatosCriados[] = $campeonatoRetratoAntigo;
foreach (range(1, 8) as $n) {
    Campeonato::inscrever($pdo2, $campeonatoRetratoAntigo, "Retrato antigo {$n}", null);
}
Campeonato::sortear($pdo2, $campeonatoRetratoAntigo, 8888);

$buscaPartidaRetratoAntigo = $pdo2->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ? ORDER BY r.numero, p.quadra LIMIT 1'
);
$buscaPartidaRetratoAntigo->execute([$campeonatoRetratoAntigo]);
$idPartidaRetratoAntigo = (int) $buscaPartidaRetratoAntigo->fetchColumn();

// Confirma a premissa do cenario: sob o retrato antigo de $pdo, uma leitura
// COMUM nao enxerga a partida que $pdo2 acabou de criar e comitar. Sem esta
// asserticao, se algum dia o isolamento do servidor mudar (ou este teste for
// copiado para outro ambiente), o resto do cenario ficaria testando uma
// premissa que nao vale mais, em silencio.
$confereRetratoAntigo = $pdo->prepare('SELECT COUNT(*) FROM partidas WHERE id = ?');
$confereRetratoAntigo->execute([$idPartidaRetratoAntigo]);
Teste::igual(
    0,
    (int) $confereRetratoAntigo->fetchColumn(),
    'premissa do cenario: uma leitura comum de $pdo nao enxerga a partida criada por $pdo2 depois do retrato'
);

// Um segundo processo auxiliar segura a trava do campeonato.
$descritores2 = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$processo2 = proc_open(
    [PHP_BINARY, __DIR__ . '/_ajuda_segura_trava_campeonato.php', (string) $campeonatoRetratoAntigo, '2'],
    $descritores2,
    $canos2
);
Teste::verdade(is_resource($processo2), 'consegue iniciar o segundo processo auxiliar (retrato antigo)');
$marcador2 = fgets($canos2[1]);
Teste::igual("TRAVA_OK\n", $marcador2, 'o segundo processo auxiliar avisa quando ja segura a trava do campeonato');

$inicio2 = microtime(true);
Placar::gravar($pdo, $campeonatoRetratoAntigo, $idPartidaRetratoAntigo, 6, 1, $organizadorId2);
$duracao2 = microtime(true) - $inicio2;

// Esta e a asserticao central do cenario: mesmo com o retrato antigo (que
// esconderia a partida de uma leitura comum), gravar() ainda assim bloqueou
// na trava do campeonato, em vez de escrever destravado em ~0.001s como o
// revisor demonstrou contra o codigo anterior a este round de correcoes.
Teste::verdade(
    $duracao2 >= 1.0,
    "gravar() com retrato antigo ainda assim bloqueou na trava do campeonato (esperou {$duracao2}s), nao escreveu sem travar"
);

$saidaAuxiliar2 = stream_get_contents($canos2[1]);
$erroAuxiliar2 = stream_get_contents($canos2[2]);
fclose($canos2[0]);
fclose($canos2[1]);
fclose($canos2[2]);
$codigoAuxiliar2 = proc_close($processo2);
Teste::igual(0, $codigoAuxiliar2, 'o segundo processo auxiliar termina sem erro (stderr: ' . $erroAuxiliar2 . ')');
Teste::verdade(
    str_contains($saidaAuxiliar2, 'COMMITOU'),
    'o segundo processo auxiliar chegou a comitar (soltar a trava) antes de gravar() retornar'
);

$pdo->commit();

$confereEscrita = $pdo2->prepare('SELECT games_a, games_b, encerrada FROM partidas WHERE id = ?');
$confereEscrita->execute([$idPartidaRetratoAntigo]);
$linhaEscrita = $confereEscrita->fetch();
Teste::igual(
    6,
    (int) $linhaEscrita['games_a'],
    'depois de destravar e comitar, o placar foi gravado corretamente mesmo com retrato antigo (games_a)'
);
Teste::igual(
    1,
    (int) $linhaEscrita['games_b'],
    'depois de destravar e comitar, o placar foi gravado corretamente mesmo com retrato antigo (games_b)'
);
Teste::igual(
    1,
    (int) $linhaEscrita['encerrada'],
    'depois de destravar e comitar, a partida ficou marcada como encerrada mesmo com retrato antigo'
);

$terminouDireito = true;
exit(Teste::resumo());
