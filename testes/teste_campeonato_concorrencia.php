<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';

echo "Campeonato concorrencia\n";

// Este arquivo NAO roda dentro de uma unica transacao com rollback no fim,
// diferente de teste_campeonato.php: os dois cenarios abaixo precisam de
// commits de verdade (de uma segunda conexao, ou de um processo filho de
// verdade) para reproduzir a janela de concorrencia. A limpeza e manual,
// feita no register_shutdown_function abaixo, que tambem forca saida
// diferente de zero se o script nao chegar ao proprio fim (mesma tecnica de
// testes/teste_acesso.php: sem isso, um exit() inesperado no meio do
// caminho passaria como sucesso para testes/executar.php).

$pdo = db();

$idsCampeonatosCriados = [];
$idsUsuariosCriados = [];
$terminouDireito = false;

register_shutdown_function(function () use (&$terminouDireito, &$idsCampeonatosCriados, &$idsUsuariosCriados, $pdo) {
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
    'Concorrencia',
    'concorrencia' . random_int(1000, 9999) . '@exemplo.com',
    'senhaforte123'
);
$idsUsuariosCriados[] = $organizadorId;

// --- Cenario 1 (I5): corrida de inscricao ---------------------------------
// Duas conexoes tentam inscrever o 8o e o 9o competidor ao mesmo tempo. A
// que perde a corrida trava no SELECT ... FOR UPDATE de
// Campeonato::inscrever ate a outra comitar; so entao ve a contagem
// atualizada e recusa. Sem essa trava, as duas passariam pela contagem de 7
// ao mesmo tempo e o campeonato terminaria com 9 competidores, uma tabela
// que o sorteio nunca mais aceita (Rodizio exige exatamente 8 posicoes) -
// dai a falha ser irreversivel e esta cobertura precisar ser permanente, e
// nao so uma checagem manual feita uma vez.

$campeonatoCorrida = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Corrida de inscricao',
    'data_evento' => '2026-09-04',
    'local'       => 'X',
    'custo'       => '',
    'descricao'   => '',
]);
$idsCampeonatosCriados[] = $campeonatoCorrida;
foreach (range(1, 7) as $n) {
    Campeonato::inscrever($pdo, $campeonatoCorrida, "Concorrente {$n}", null);
}

$descritores = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$processo = proc_open(
    [PHP_BINARY, __DIR__ . '/_ajuda_corrida_inscricao.php', (string) $campeonatoCorrida, '2'],
    $descritores,
    $canos
);
Teste::verdade(is_resource($processo), 'consegue iniciar o processo auxiliar da corrida de inscricao');

// Bloqueia ate o auxiliar avisar que ja segura a trava (ou ate ele fechar o
// pipe, se algo der errado) - nao um sleep as cegas do lado de fora.
$marcador = fgets($canos[1]);
Teste::igual("TRAVA_OK\n", $marcador, 'o processo auxiliar avisa quando ja segura a trava do campeonato');

$inicio = microtime(true);
$erroCorrida = null;
try {
    Campeonato::inscrever($pdo, $campeonatoCorrida, 'Concorrente 9 (bloqueado)', null);
} catch (RuntimeException $excecao) {
    $erroCorrida = $excecao->getMessage();
}
$duracaoBloqueio = microtime(true) - $inicio;

Teste::verdade(
    $duracaoBloqueio >= 1.0,
    "a segunda conexao ficou bloqueada esperando a trava (esperou {$duracaoBloqueio}s, o auxiliar segura por 2s)"
);
Teste::igual(
    'O campeonato ja tem 8 competidores.',
    $erroCorrida,
    'depois de destravar, a segunda conexao ve a contagem ja atualizada e recusa a 9a inscricao'
);

$saidaAuxiliar = stream_get_contents($canos[1]);
$erroAuxiliar = stream_get_contents($canos[2]);
fclose($canos[0]);
fclose($canos[1]);
fclose($canos[2]);
$codigoAuxiliar = proc_close($processo);
Teste::igual(0, $codigoAuxiliar, 'o processo auxiliar da corrida termina sem erro (stderr: ' . $erroAuxiliar . ')');
Teste::verdade(
    str_contains($saidaAuxiliar, 'COMMITOU'),
    'o processo auxiliar chegou a comitar o 8o competidor antes da segunda conexao ser destravada'
);

$contaFinal = $pdo->prepare('SELECT COUNT(*) FROM inscricoes WHERE campeonato_id = ?');
$contaFinal->execute([$campeonatoCorrida]);
Teste::igual(
    8,
    (int) $contaFinal->fetchColumn(),
    'o campeonato termina com exatamente 8 competidores, nunca 9'
);

// --- Cenario 2 (Importante 2): guarda de placar sob REPEATABLE READ ------
// $pdo abre a propria transacao (papel de quem chama sortear()) e faz uma
// leitura comum qualquer antes de qualquer coisa mudar - por exemplo, para
// exibir a tela do campeonato. Depois, uma SEGUNDA conexao grava um placar
// e comita de verdade. Sob REPEATABLE READ, a leitura comum de $pdo
// continua enxergando o retrato de antes do commit alheio; so uma leitura
// com FOR UPDATE ve o commit. sortear() precisa usar leitura travada nas
// duas guardas (8 competidores, sem placar) para nao redesenhar por cima
// do placar que acabou de ser gravado.

$campeonatoPlacar = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Guarda de placar sob REPEATABLE READ',
    'data_evento' => '2026-09-05',
    'local'       => 'X',
    'custo'       => '',
    'descricao'   => '',
]);
$idsCampeonatosCriados[] = $campeonatoPlacar;
foreach (range(1, 8) as $n) {
    Campeonato::inscrever($pdo, $campeonatoPlacar, "Jogador placar {$n}", null);
}
Campeonato::sortear($pdo, $campeonatoPlacar, 5150);

$buscaPrimeiraPartida = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ? ORDER BY r.numero, p.quadra LIMIT 1'
);
$buscaPrimeiraPartida->execute([$campeonatoPlacar]);
$idPrimeiraPartida = (int) $buscaPrimeiraPartida->fetchColumn();

$pdo->beginTransaction();
// Leitura comum qualquer, dentro da transacao que sortear() vai herdar mais
// abaixo: simula uma tela que ja abriu a transacao e olhou o campeonato
// antes de qualquer coisa mudar.
Campeonato::buscar($pdo, $campeonatoPlacar);

$dsnSegunda = 'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4';
$pdo2 = new PDO($dsnSegunda, DB_USER, DB_SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// $pdo2 fica em autocommit: este UPDATE ja fica commitado de verdade assim
// que executa, fora do alcance do rollBack() de $pdo mais abaixo.
$pdo2->prepare('UPDATE partidas SET games_a = ?, games_b = ? WHERE id = ?')->execute([6, 3, $idPrimeiraPartida]);

$erroPlacar = null;
try {
    Campeonato::sortear($pdo, $campeonatoPlacar, 5150);
} catch (RuntimeException $excecao) {
    $erroPlacar = $excecao->getMessage();
}
Teste::igual(
    'Nao da para refazer o sorteio com placar ja lancado.',
    $erroPlacar,
    'sortear recusa o redesenho mesmo com uma leitura comum anterior na mesma transacao (leitura travada ve o commit alheio)'
);

$pdo->rollBack();
// O rollBack() acima so desfaz o que $pdo fez (nada, ja que sortear()
// recusou). O placar gravado por $pdo2 precisa ser limpo manualmente,
// porque foi commitado fora desta transacao.
$pdo->prepare('UPDATE partidas SET games_a = NULL, games_b = NULL WHERE id = ?')->execute([$idPrimeiraPartida]);

$terminouDireito = true;
exit(Teste::resumo());
