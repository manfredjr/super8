<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Validador.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';
require __DIR__ . '/../src/Placar.php';

/**
 * Mapa id da inscricao => posicao_sorteio, ordenado pela chave. Serve para
 * comparar dois sorteios sem depender da ordem de exibicao de
 * listarInscricoes, que muda conforme as posicoes ja atribuidas.
 */
function mapaPosicoes(PDO $pdo, int $campeonatoId): array
{
    $mapa = [];
    foreach (Campeonato::listarInscricoes($pdo, $campeonatoId) as $inscricao) {
        $mapa[(int) $inscricao['id']] = (int) $inscricao['posicao_sorteio'];
    }
    ksort($mapa);

    return $mapa;
}

/** As 14 partidas, em ordem de rodada e quadra, com os 4 ids de inscricao de cada uma. */
function partidasBrutas(PDO $pdo, int $campeonatoId): array
{
    $busca = $pdo->prepare(
        'SELECT r.numero, p.quadra, p.dupla_a_j1, p.dupla_a_j2, p.dupla_b_j1, p.dupla_b_j2
         FROM partidas p
         JOIN rodadas r ON r.id = p.rodada_id
         WHERE r.campeonato_id = ?
         ORDER BY r.numero, p.quadra'
    );
    $busca->execute([$campeonatoId]);

    return array_map(
        static fn (array $linha): array => array_map('intval', $linha),
        $busca->fetchAll()
    );
}

echo "Campeonato\n";

$pdo = db();
$pdo->beginTransaction();

$organizadorId = Auth::cadastrar($pdo, 'Organizador', 'org' . random_int(1000, 9999) . '@exemplo.com', 'senhaforte123');

$campeonatoId = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Super 8 de teste',
    'data_evento' => '2026-09-01',
    'local'       => 'Arena Central',
    'custo'       => 50.00,
    'descricao'   => 'Evento de teste',
]);
Teste::verdade($campeonatoId > 0, 'criar devolve o id do campeonato');

$campeonato = Campeonato::buscar($pdo, $campeonatoId);
Teste::igual('rascunho', $campeonato['status'], 'nasce como rascunho');
Teste::igual(null, $campeonato['seed_sorteio'], 'nasce sem semente');

$erro = null;
try {
    Campeonato::sortear($pdo, $campeonatoId);
} catch (RuntimeException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa sortear sem os 8 inscritos');

foreach (range(1, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoId, "Jogador {$numero}", null);
}
Teste::igual(8, count(Campeonato::listarInscricoes($pdo, $campeonatoId)), 'tem 8 inscritos');

$erro = null;
try {
    Campeonato::inscrever($pdo, $campeonatoId, 'Jogador 9', null);
} catch (RuntimeException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa o nono inscrito');

$semente = Campeonato::sortear($pdo, $campeonatoId, 4242);
Teste::igual(4242, $semente, 'grava a semente informada');

$campeonato = Campeonato::buscar($pdo, $campeonatoId);
Teste::igual('sorteado', $campeonato['status'], 'muda o status para sorteado');
Teste::igual(4242, (int) $campeonato['seed_sorteio'], 'a semente fica no campeonato');

$posicoes = array_map(
    static fn (array $inscricao): int => (int) $inscricao['posicao_sorteio'],
    Campeonato::listarInscricoes($pdo, $campeonatoId)
);
sort($posicoes);
Teste::igual([1, 2, 3, 4, 5, 6, 7, 8], $posicoes, 'as 8 posicoes foram distribuidas');

$chaveamento = Campeonato::chaveamento($pdo, $campeonatoId);
Teste::igual(7, count($chaveamento), 'gera 7 rodadas');
foreach ($chaveamento as $rodada) {
    Teste::igual(2, count($rodada['partidas']), "a rodada {$rodada['numero']} tem 2 partidas");
}

$contaPartidas = $pdo->prepare(
    'SELECT COUNT(*) FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);

$contaPartidas->execute([$campeonatoId]);
Teste::igual(14, (int) $contaPartidas->fetchColumn(), 'gera 14 partidas');

// --- Chaveamento real, lido do banco, contra a tabela Rodizio::RODADAS ---
// As checagens acima (7 rodadas, 2 partidas por rodada, 14 partidas ao todo)
// nao provam que o chaveamento esta CORRETO: uma quadra errada, uma dupla
// A e B trocadas ou uma rodada duplicada tambem passariam por elas. As
// asserticoes daqui para baixo conferem a propriedade que o motor existe
// para produzir.
$porPosicao = [];
foreach (Campeonato::listarInscricoes($pdo, $campeonatoId) as $inscricao) {
    $porPosicao[(int) $inscricao['posicao_sorteio']] = (int) $inscricao['id'];
}

$partidasCompletas = $pdo->prepare(
    'SELECT p.id, r.numero, p.quadra, p.dupla_a_j1, p.dupla_a_j2, p.dupla_b_j1, p.dupla_b_j2
     FROM partidas p
     JOIN rodadas r ON r.id = p.rodada_id
     WHERE r.campeonato_id = ?
     ORDER BY r.numero, p.quadra'
);
$partidasCompletas->execute([$campeonatoId]);
$linhasPartidas = $partidasCompletas->fetchAll();

// Os numeros de rodada sao exatamente 1 a 7, sem repetir e sem faltar.
$numerosRodada = array_values(array_unique(
    array_map(static fn (array $linha): int => (int) $linha['numero'], $linhasPartidas)
));
sort($numerosRodada);
Teste::igual([1, 2, 3, 4, 5, 6, 7], $numerosRodada, 'os numeros de rodada sao exatamente 1 a 7');

$linhasPorRodada = [];
foreach ($linhasPartidas as $linha) {
    $linhasPorRodada[(int) $linha['numero']][] = $linha;
}

$idsInscritos = array_values($porPosicao);
sort($idsInscritos);

foreach ($linhasPorRodada as $numero => $linhasDaRodada) {
    $quadras = array_map(static fn (array $linha): int => (int) $linha['quadra'], $linhasDaRodada);
    sort($quadras);
    Teste::igual([1, 2], $quadras, "a rodada {$numero} usa exatamente as quadras 1 e 2");

    $competidoresDaRodada = [];
    foreach ($linhasDaRodada as $linha) {
        $competidoresDaRodada[] = (int) $linha['dupla_a_j1'];
        $competidoresDaRodada[] = (int) $linha['dupla_a_j2'];
        $competidoresDaRodada[] = (int) $linha['dupla_b_j1'];
        $competidoresDaRodada[] = (int) $linha['dupla_b_j2'];
    }
    sort($competidoresDaRodada);
    Teste::igual($idsInscritos, $competidoresDaRodada, "a rodada {$numero} tem os 8 competidores, cada um uma vez");
}

// As 14 partidas geram 28 parcerias (a dupla A e a dupla B de cada uma), e
// essas 28 parcerias sao exatamente todos os pares possiveis entre os 8
// competidores, cada um exatamente uma vez.
$parcerias = [];
foreach ($linhasPartidas as $linha) {
    foreach ([['dupla_a_j1', 'dupla_a_j2'], ['dupla_b_j1', 'dupla_b_j2']] as [$campo1, $campo2]) {
        $par = [(int) $linha[$campo1], (int) $linha[$campo2]];
        sort($par);
        $parcerias[] = implode('-', $par);
    }
}
Teste::igual(28, count($parcerias), 'as 14 partidas geram 28 parcerias ao todo');
Teste::igual(28, count(array_unique($parcerias)), 'as 28 parcerias sao todas distintas');

$parceriasEsperadas = [];
foreach ($idsInscritos as $i => $idA) {
    foreach ($idsInscritos as $j => $idB) {
        if ($j <= $i) {
            continue;
        }
        $par = [$idA, $idB];
        sort($par);
        $parceriasEsperadas[] = implode('-', $par);
    }
}
sort($parceriasEsperadas);
$parceriasObtidas = $parcerias;
sort($parceriasObtidas);
Teste::igual(
    $parceriasEsperadas,
    $parceriasObtidas,
    'as 28 parcerias sao exatamente todos os pares possiveis dos 8 competidores'
);

// A dupla A e a dupla B de cada partida batem, posicao por posicao, com
// Rodizio::RODADAS: reconstroi os 4 ids esperados a partir do mapa de
// posicoes e compara lado a lado com a linha gravada. Isso pega qualquer
// troca entre os membros das duplas, mesmo quando o conjunto de 28
// parcerias acima continuar correto (por exemplo as duas parcerias de uma
// mesma partida trocadas de posicao inteiras).
foreach ($linhasPartidas as $linha) {
    $numero = (int) $linha['numero'];
    $quadra = (int) $linha['quadra'];
    [$duplaA, $duplaB] = Rodizio::RODADAS[$numero][$quadra - 1];

    Teste::igual(
        [
            'dupla_a_j1' => $porPosicao[$duplaA[0]],
            'dupla_a_j2' => $porPosicao[$duplaA[1]],
            'dupla_b_j1' => $porPosicao[$duplaB[0]],
            'dupla_b_j2' => $porPosicao[$duplaB[1]],
        ],
        [
            'dupla_a_j1' => (int) $linha['dupla_a_j1'],
            'dupla_a_j2' => (int) $linha['dupla_a_j2'],
            'dupla_b_j1' => (int) $linha['dupla_b_j1'],
            'dupla_b_j2' => (int) $linha['dupla_b_j2'],
        ],
        "rodada {$numero} quadra {$quadra}: dupla A e dupla B batem com Rodizio::RODADAS"
    );
}

Teste::verdade(!Campeonato::temPlacarLancado($pdo, $campeonatoId), 'ainda nao tem placar lancado');

// Placar gravado sem a partida estar marcada como encerrada precisa ser
// enxergado do mesmo jeito, senao um redesenho de sorteio apaga esse placar
// em silencio.
$idPrimeiraPartida = (int) $linhasPartidas[0]['id'];
$marcaPlacarSemEncerrar = $pdo->prepare('UPDATE partidas SET games_a = ?, games_b = ? WHERE id = ?');
$marcaPlacarSemEncerrar->execute([6, 3, $idPrimeiraPartida]);

Teste::verdade(
    Campeonato::temPlacarLancado($pdo, $campeonatoId),
    'temPlacarLancado enxerga games preenchidos mesmo com encerrada = 0'
);

$erro = null;
try {
    Campeonato::sortear($pdo, $campeonatoId, 4242);
} catch (RuntimeException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa redesenhar o sorteio com placar lancado, mesmo sem encerrada = 1');

// Limpa o placar de teste: as checagens de reprodutibilidade a seguir
// precisam de um campeonato sem placar lancado para poder redesenhar.
$limpaPlacarTeste = $pdo->prepare('UPDATE partidas SET games_a = NULL, games_b = NULL WHERE id = ?');
$limpaPlacarTeste->execute([$idPrimeiraPartida]);

// Estado de auditoria: campeonato ja sorteado, com posicao_sorteio gravada
// para os 8 inscritos. E exatamente esse o estado em que alguem pediria para
// refazer o sorteio com a mesma semente e conferir que da o mesmo resultado.
$mapaPosicoesAntes = mapaPosicoes($pdo, $campeonatoId);
$partidasAntes = partidasBrutas($pdo, $campeonatoId);

Campeonato::sortear($pdo, $campeonatoId, 4242);

$contaPartidas->execute([$campeonatoId]);
Teste::igual(14, (int) $contaPartidas->fetchColumn(), 'refazer o sorteio nao duplica partidas');

$mapaPosicoesDepois = mapaPosicoes($pdo, $campeonatoId);
$partidasDepois = partidasBrutas($pdo, $campeonatoId);

Teste::igual(
    $mapaPosicoesAntes,
    $mapaPosicoesDepois,
    'refazer o sorteio com a mesma semente reproduz o mesmo mapeamento de posicoes (auditoria)'
);
Teste::igual(
    $partidasAntes,
    $partidasDepois,
    'refazer o sorteio com a mesma semente reproduz exatamente as mesmas 14 partidas (auditoria)'
);

$semente9999 = Campeonato::sortear($pdo, $campeonatoId, 9999);
Teste::igual(9999, $semente9999, 'sorteia de novo com outra semente');
$mapaPosicoesOutraSemente = mapaPosicoes($pdo, $campeonatoId);
Teste::verdade(
    $mapaPosicoesAntes !== $mapaPosicoesOutraSemente,
    'uma semente diferente muda o mapeamento de posicoes (a asserta acima nao passa a toa)'
);

// I7: nome de exibicao duplicado no mesmo campeonato vira excecao tipada,
// nao um PDOException cru vazando a UNIQUE KEY do schema. Usa um segundo
// campeonato porque o principal ja esta com os 8 competidores completos.
$campeonatoId2 = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Segundo campeonato de teste',
    'data_evento' => '2026-09-02',
    'local'       => 'Arena B',
    'custo'       => '',
    'descricao'   => '',
]);
Campeonato::inscrever($pdo, $campeonatoId2, 'Duplicado', null);
$erro = null;
try {
    Campeonato::inscrever($pdo, $campeonatoId2, 'Duplicado', null);
} catch (RuntimeException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::igual(
    'Já existe um competidor com esse nome.',
    $erro,
    'nome de exibicao duplicado gera RuntimeException com mensagem em portugues'
);

// Importante 1 (rodada de revisao): um jogador_id que nao existe esbarra na
// FOREIGN KEY fk_insc_jogador, uma SQLSTATE 23000 diferente da UNIQUE KEY de
// nome. Isso tem que subir como PDOException cru, nunca como a mensagem de
// nome duplicado (o nome usado aqui nem repete nenhum outro).
$erroTipoErrado = null;
$capturouPdoException = false;
try {
    Campeonato::inscrever($pdo, $campeonatoId2, 'Nome ainda livre', 999999999);
} catch (PDOException $excecao) {
    // PDOException extends RuntimeException no PHP, entao este catch tem
    // que vir ANTES do catch (RuntimeException) abaixo: na ordem inversa, o
    // catch mais generico capturaria a PDOException tambem, e o teste
    // passaria mesmo se inscrever() tivesse disfarcado o erro real.
    $capturouPdoException = true;
} catch (RuntimeException $excecao) {
    $erroTipoErrado = $excecao->getMessage();
}
Teste::verdade(
    $capturouPdoException,
    'jogador_id inexistente sobe como PDOException cru (fk_insc_jogador), nao foi engolido'
);
Teste::igual(
    null,
    $erroTipoErrado,
    'jogador_id inexistente nao produz a mensagem de nome duplicado'
);

// I6: removerInscricao so apaga a inscricao se ela pertencer ao campeonato
// informado. Passar o id de OUTRO campeonato nao pode remover nada: e
// exatamente o caminho que permitiria a um organizador apagar a inscricao
// de outro, so acertando o id.
$inscricaoRemovivel = Campeonato::inscrever($pdo, $campeonatoId2, 'Removivel', null);
Teste::igual(2, count(Campeonato::listarInscricoes($pdo, $campeonatoId2)), 'segundo campeonato tem 2 inscritos');

// Rodada de revisao da tarefa 12: antes, um DELETE que afetava zero linhas
// (id de outro campeonato, ou id que nao existe) devolvia void como se
// tivesse removido algo, e quem chama redirecionava como sucesso sem ter
// apagado nada. Agora vira RuntimeException tipada, do mesmo jeito que
// Campeonato::atualizar ja recusa "Campeonato nao encontrado." por
// rowCount() zero.
$erroRemocaoCampeonatoErrado = null;
try {
    Campeonato::removerInscricao($pdo, $campeonatoId, $inscricaoRemovivel);
} catch (RuntimeException $excecao) {
    $erroRemocaoCampeonatoErrado = $excecao->getMessage();
}
Teste::igual(
    'Competidor não encontrado.',
    $erroRemocaoCampeonatoErrado,
    'removerInscricao com o campeonato errado recusa com RuntimeException, em vez de responder como se tivesse apagado'
);
Teste::igual(
    2,
    count(Campeonato::listarInscricoes($pdo, $campeonatoId2)),
    'removerInscricao com o campeonato errado nao remove a inscricao de outro campeonato'
);

Campeonato::removerInscricao($pdo, $campeonatoId2, $inscricaoRemovivel);
Teste::igual(
    1,
    count(Campeonato::listarInscricoes($pdo, $campeonatoId2)),
    'removerInscricao com o campeonato certo remove a inscricao'
);

// I6: depois do sorteio, as partidas referenciam as inscricoes. Remover uma
// delas esbarra na FOREIGN KEY e precisa virar RuntimeException tipada, nao
// um PDOException cru.
$inscricoesFinais = Campeonato::listarInscricoes($pdo, $campeonatoId);
$erro = null;
try {
    Campeonato::removerInscricao($pdo, $campeonatoId, (int) $inscricoesFinais[0]['id']);
} catch (RuntimeException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::igual(
    'Não é possível remover um competidor depois do sorteio.',
    $erro,
    'remover um competidor depois do sorteio gera RuntimeException com mensagem em portugues'
);

// m3: redesenhar o sorteio nao pode rebaixar o status de um campeonato que
// ja avancou para em_andamento ou encerrado de volta para sorteado.
$pdo->prepare("UPDATE campeonatos SET status = 'em_andamento' WHERE id = ?")->execute([$campeonatoId]);
Campeonato::sortear($pdo, $campeonatoId, 4242);
$campeonatoAposRedesenho = Campeonato::buscar($pdo, $campeonatoId);
Teste::igual(
    'em_andamento',
    $campeonatoAposRedesenho['status'],
    'redesenhar o sorteio nao rebaixa o status de em_andamento para sorteado'
);

// Rodada de revisao (Task 9, Importante 3): o mesmo jogador_id nao pode
// ser inscrito duas vezes no mesmo campeonato, mesmo com nomes de exibicao
// diferentes - e exatamente o cenario de um organizador cadastrando a
// mesma pessoa como "Joao" e depois como "Joao S." sem perceber que e a
// mesma conta, o que inflava silenciosamente o ranking acumulado (Task 9)
// com um evento contando 14 partidas e o dobro dos games. Usa um
// campeonato novo para nao interferir na contagem dos blocos acima.
$campeonatoId3 = Campeonato::criar($pdo, $organizadorId, [
    'nome'        => 'Terceiro campeonato de teste (jogador duplicado)',
    'data_evento' => '2026-09-03',
    'local'       => 'Arena C',
    'custo'       => '',
    'descricao'   => '',
]);
$jogadorDuploId = Auth::cadastrar(
    $pdo,
    'Jogador Duplo',
    'jogadorduplo' . random_int(1000, 9999) . '@exemplo.com',
    'senhaforte123'
);
Campeonato::inscrever($pdo, $campeonatoId3, 'Joao', $jogadorDuploId);

$erroJogadorDuplicado = null;
try {
    Campeonato::inscrever($pdo, $campeonatoId3, 'Joao S.', $jogadorDuploId);
} catch (RuntimeException $excecao) {
    $erroJogadorDuplicado = $excecao->getMessage();
}
Teste::igual(
    'Este jogador já está inscrito neste campeonato.',
    $erroJogadorDuplicado,
    'inscrever o mesmo jogador_id duas vezes no mesmo campeonato, com nomes diferentes, gera RuntimeException com mensagem propria (nao a de nome duplicado)'
);
Teste::igual(
    1,
    count(Campeonato::listarInscricoes($pdo, $campeonatoId3)),
    'a tentativa recusada nao inseriu uma segunda inscricao para o mesmo jogador'
);

// Continua distinguindo da mensagem de nome duplicado (uk_camp_nome
// continua funcionando do jeito de sempre, so com jogador_id nulo, senao
// a UNIQUE KEY nova nem entraria em jogo para provar a diferenca).
$erroNomeDuplicadoJ3 = null;
try {
    Campeonato::inscrever($pdo, $campeonatoId3, 'Joao', null);
} catch (RuntimeException $excecao) {
    $erroNomeDuplicadoJ3 = $excecao->getMessage();
}
Teste::igual(
    'Já existe um competidor com esse nome.',
    $erroNomeDuplicadoJ3,
    'nome duplicado continua com a mensagem de sempre, diferente da mensagem de jogador duplicado'
);

// Rodada de revisao (Task 9, item 2 da segunda rodada): a mensagem do
// driver para "Duplicate entry" embute o VALOR duplicado, nao so o nome
// da chave - "Duplicate entry '<valor>' for key '<chave>'". Um
// str_contains() procurando 'uk_camp_jogador' EM QUALQUER PARTE dessa
// mensagem cairia na armadilha de um competidor literalmente chamado
// "uk_camp_jogador": a mensagem de nome duplicado (uk_camp_nome) ficaria
// "Duplicate entry '<id>-uk_camp_jogador' for key 'uk_camp_nome'", e o
// str_contains() acharia a substring dentro do VALOR e devolveria a
// mensagem de jogador duplicado por engano. inscrever() agora ancora no
// SUFIXO exato "for key 'uk_camp_jogador'" (str_ends_with), que so bate
// quando a CHAVE de verdade foi essa, nunca por causa do valor.
Campeonato::inscrever($pdo, $campeonatoId3, 'uk_camp_jogador', null);
$erroNomeAdversario = null;
try {
    Campeonato::inscrever($pdo, $campeonatoId3, 'uk_camp_jogador', null);
} catch (RuntimeException $excecao) {
    $erroNomeAdversario = $excecao->getMessage();
}
Teste::igual(
    'Já existe um competidor com esse nome.',
    $erroNomeAdversario,
    'um competidor chamado literalmente "uk_camp_jogador" ainda recebe a mensagem de NOME duplicado, nao a de jogador duplicado (a chave violada foi uk_camp_nome, o texto "uk_camp_jogador" so aparece dentro do valor)'
);

// NULL nao colide em UNIQUE KEY: dois convidados sem conta (jogador_id
// null) continuam podendo coexistir no mesmo campeonato - a UNIQUE KEY
// uk_camp_jogador nao pode impedir isso, senao um campeonato so poderia
// ter UM convidado sem conta no total.
Campeonato::inscrever($pdo, $campeonatoId3, 'Convidado Um', null);
Campeonato::inscrever($pdo, $campeonatoId3, 'Convidado Dois', null);
Teste::igual(
    4,
    count(Campeonato::listarInscricoes($pdo, $campeonatoId3)),
    'dois convidados sem conta (jogador_id null) coexistem no mesmo campeonato: NULL nunca colide em UNIQUE KEY'
);

// ============================================================================
// C2 (Critico, rodada de revisao): criar/atualizar so faziam trim antes
// desta correcao. Cada linha da tabela medida contra o banco real vira uma
// asserticao de recusa: sem Validador, cada uma destas seis chamadas
// gravava algo corrompido em silencio em vez de lancar.
// ============================================================================
function esperaRecusaCriar(PDO $pdo, int $organizadorId, array $dados, string $descricao): void
{
    $erro = null;
    try {
        Campeonato::criar($pdo, $organizadorId, $dados);
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, $descricao);
}

$dadosBaseValidos = [
    'nome'        => 'Validacao de entrada',
    'data_evento' => '2026-10-01',
    'local'       => 'Arena',
    'custo'       => '',
    'descricao'   => '',
];

esperaRecusaCriar($pdo, $organizadorId, ['nome' => str_repeat('a', 200)] + $dadosBaseValidos, 'C2: recusa nome com 200 caracteres (limite 160, antes gravava truncado)');
esperaRecusaCriar($pdo, $organizadorId, ['data_evento' => ''] + $dadosBaseValidos, 'C2: recusa data_evento vazia (antes gravava 0000-00-00)');
esperaRecusaCriar($pdo, $organizadorId, ['data_evento' => '31/12/2026'] + $dadosBaseValidos, 'C2: recusa data_evento em formato com barras (antes gravava 0000-00-00)');
esperaRecusaCriar($pdo, $organizadorId, ['nome' => '   '] + $dadosBaseValidos, 'C2: recusa nome so com espacos (antes gravava string vazia)');
esperaRecusaCriar($pdo, $organizadorId, ['custo' => 'de graca'] + $dadosBaseValidos, 'C2: recusa custo nao numerico (antes gravava 0.00)');

// Pequena 2 (segunda rodada de revisao): validarCusto so recusava
// nao-numerico. custo e DECIMAL(10,2), sem modo estrito: negativo passava
// (custo de evento nao existe abaixo de zero) e um valor como 99999999999
// gravava truncado em 99999999.99 em silencio, em vez de recusado.
esperaRecusaCriar($pdo, $organizadorId, ['custo' => '-50'] + $dadosBaseValidos, 'C2: recusa custo negativo');
esperaRecusaCriar($pdo, $organizadorId, ['custo' => '99999999999'] + $dadosBaseValidos, 'C2: recusa custo maior do que DECIMAL(10,2) comporta (antes gravava truncado em 99999999.99)');

// O limite exato ainda e aceito (o maior valor que DECIMAL(10,2) comporta).
$idCustoLimite = Campeonato::criar($pdo, $organizadorId, ['custo' => '99999999.99'] + $dadosBaseValidos);
$campeonatoCustoLimite = Campeonato::buscar($pdo, $idCustoLimite);
Teste::igual('99999999.99', $campeonatoCustoLimite['custo'], 'C2: o maior custo que a coluna comporta (99999999.99) continua sendo aceito');

$dadosSemDataEvento = $dadosBaseValidos;
unset($dadosSemDataEvento['data_evento']);
esperaRecusaCriar($pdo, $organizadorId, $dadosSemDataEvento, 'C2: chave data_evento ausente vira InvalidArgumentException, nao PDOException crua');

// Uma data valida continua sendo aceita e gravada igual: a correcao nao pode
// deixar de aceitar entrada correta.
$idValidacaoOk = Campeonato::criar($pdo, $organizadorId, $dadosBaseValidos);
$campeonatoValidacaoOk = Campeonato::buscar($pdo, $idValidacaoOk);
Teste::igual('2026-10-01', $campeonatoValidacaoOk['data_evento'], 'C2: uma data valida continua sendo aceita e gravada igual');
Teste::igual('Validacao de entrada', $campeonatoValidacaoOk['nome'], 'C2: nome valido continua gravado igual');

// atualizar() passa pela mesma validacao que criar().
$erroAtualizarCustoInvalido = null;
try {
    Campeonato::atualizar($pdo, $idValidacaoOk, $organizadorId, ['custo' => 'de graca'] + $dadosBaseValidos);
} catch (InvalidArgumentException $excecao) {
    $erroAtualizarCustoInvalido = $excecao->getMessage();
}
Teste::verdade($erroAtualizarCustoInvalido !== null, 'C2: atualizar tambem recusa custo nao numerico');

// ============================================================================
// C3 (Critico, rodada de revisao da tarefa 14): um campeonato sem sorteio
// nao tem partida nenhuma, entao a contagem de pendentes (que so conta
// partida que EXISTE e esta faltando placar) e zero por definicao vazia -
// sem uma guarda separada para "total de partidas zero", encerrar() aceitava
// um campeonato assim, gravava 'encerrado', e o evento ficava travado para
// sempre (atualizar() e Placar::gravar ja recusam qualquer coisa depois de
// encerrado, e nao ha caminho de volta a nao ser recriar o campeonato do
// zero e redigitar os 8 nomes).
// ============================================================================
$campeonatoSemSorteioId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Campeonato sem sorteio', 'data_evento' => '2026-10-04',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);

$erroEncerrarSemInscritos = null;
try {
    Campeonato::encerrar($pdo, $campeonatoSemSorteioId);
} catch (RuntimeException $excecao) {
    $erroEncerrarSemInscritos = $excecao->getMessage();
}
Teste::igual(
    'O campeonato ainda não foi sorteado; não há partidas para encerrar.',
    $erroEncerrarSemInscritos,
    'C3: encerrar recusa campeonato recem criado, sem nenhum inscrito, com a mensagem de "nao foi sorteado"'
);
Teste::igual(
    'rascunho',
    Campeonato::buscar($pdo, $campeonatoSemSorteioId)['status'],
    'C3: a recusa acima nao muda o status do campeonato'
);

foreach (range(1, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoSemSorteioId, "Sem sorteio {$numero}", null);
}
Teste::igual(
    0,
    Campeonato::partidasPendentes($pdo, $campeonatoSemSorteioId),
    'C3: partidasPendentes() de um campeonato com 8 inscritos mas sem sorteio e 0 (nao ha partida nenhuma para contar) - e exatamente esse o ponto cego que a guarda de total zero dentro de encerrar() cobre, porque a contagem de pendentes sozinha nao basta'
);

$erroEncerrarComInscritosSemSorteio = null;
try {
    Campeonato::encerrar($pdo, $campeonatoSemSorteioId);
} catch (RuntimeException $excecao) {
    $erroEncerrarComInscritosSemSorteio = $excecao->getMessage();
}
Teste::igual(
    'O campeonato ainda não foi sorteado; não há partidas para encerrar.',
    $erroEncerrarComInscritosSemSorteio,
    'C3: encerrar recusa mesmo com os 8 competidores completos, enquanto o sorteio nao rodar'
);
Teste::igual(
    'rascunho',
    Campeonato::buscar($pdo, $campeonatoSemSorteioId)['status'],
    'C3: o campeonato continua em rascunho, nao fica travado como encerrado sem partida nenhuma'
);

// ============================================================================
// C1 (Critico, rodada de revisao): Campeonato::encerrar e a UNICA transicao
// para o status 'encerrado'. Ranking::acumulado filtra exatamente por esse
// status - sem este metodo, nada no sistema fazia um evento contar no
// ranking acumulado.
// ============================================================================
$campeonatoEncerrarId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Campeonato para encerrar', 'data_evento' => '2026-10-05',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
foreach (range(1, 8) as $numero) {
    Campeonato::inscrever($pdo, $campeonatoEncerrarId, "Encerrar {$numero}", null);
}
Campeonato::sortear($pdo, $campeonatoEncerrarId, 5555);

Teste::igual(
    14,
    Campeonato::partidasPendentes($pdo, $campeonatoEncerrarId),
    'partidasPendentes() conta as 14 partidas recem-sorteadas, nenhuma com placar ainda'
);

$erroEncerrarPendente = null;
try {
    Campeonato::encerrar($pdo, $campeonatoEncerrarId);
} catch (RuntimeException $excecao) {
    $erroEncerrarPendente = $excecao->getMessage();
}
Teste::verdade($erroEncerrarPendente !== null, 'C1: encerrar recusa campeonato com partida pendente, com mensagem em portugues');

$buscaPartidasEncerrar = $pdo->prepare(
    'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
);
$buscaPartidasEncerrar->execute([$campeonatoEncerrarId]);
$idsPartidasEncerrar = $buscaPartidasEncerrar->fetchAll(PDO::FETCH_COLUMN);
foreach ($idsPartidasEncerrar as $indicePartida => $partidaId) {
    Placar::gravar($pdo, $campeonatoEncerrarId, (int) $partidaId, 6, 3, $organizadorId);

    $restantes = count($idsPartidasEncerrar) - $indicePartida - 1;
    Teste::igual(
        $restantes,
        Campeonato::partidasPendentes($pdo, $campeonatoEncerrarId),
        "partidasPendentes() desce para {$restantes} depois de gravar o placar da partida " . ($indicePartida + 1)
    );
}

Campeonato::encerrar($pdo, $campeonatoEncerrarId);
$campeonatoEncerrado = Campeonato::buscar($pdo, $campeonatoEncerrarId);
Teste::igual('encerrado', $campeonatoEncerrado['status'], 'C1: encerrar com as 14 partidas lancadas muda o status para encerrado');

// Encerrar duas vezes nao e erro.
Campeonato::encerrar($pdo, $campeonatoEncerrarId);
$campeonatoEncerradoDeNovo = Campeonato::buscar($pdo, $campeonatoEncerrarId);
Teste::igual('encerrado', $campeonatoEncerradoDeNovo['status'], 'C1: encerrar um campeonato ja encerrado nao lanca e mantem o status');

$erroEncerrarInexistente = null;
try {
    Campeonato::encerrar($pdo, 999999999);
} catch (RuntimeException $excecao) {
    $erroEncerrarInexistente = $excecao->getMessage();
}
Teste::verdade($erroEncerrarInexistente !== null, 'C1: encerrar com id inexistente lanca RuntimeException');

// ============================================================================
// I3 (Importante, rodada de revisao): depois de encerrado, o placar
// historico e a data do evento nao podem mais mudar.
// ============================================================================
$erroGravarDepoisEncerrado = null;
try {
    Placar::gravar($pdo, $campeonatoEncerrarId, (int) $idsPartidasEncerrar[0], 6, 2, $organizadorId);
} catch (RuntimeException $excecao) {
    $erroGravarDepoisEncerrado = $excecao->getMessage();
}
Teste::verdade($erroGravarDepoisEncerrado !== null, 'I3: gravar placar depois de encerrado e recusado');

$erroAtualizarDepoisEncerrado = null;
try {
    Campeonato::atualizar($pdo, $campeonatoEncerrarId, $organizadorId, [
        'nome' => 'Tentando editar depois de encerrado', 'data_evento' => '2026-10-06',
        'local' => 'Arena', 'custo' => '', 'descricao' => '',
    ]);
} catch (RuntimeException $excecao) {
    $erroAtualizarDepoisEncerrado = $excecao->getMessage();
}
Teste::verdade($erroAtualizarDepoisEncerrado !== null, 'I3: atualizar o campeonato depois de encerrado e recusado');

// ============================================================================
// I2 (Importante, rodada de revisao): atualizar() agora filtra por dono, do
// mesmo jeito que removerInscricao ja fazia.
// ============================================================================
$campeonatoAtualizarId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Antes de atualizar', 'data_evento' => '2026-10-10',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);
Campeonato::atualizar($pdo, $campeonatoAtualizarId, $organizadorId, [
    'nome' => 'Depois de atualizar', 'data_evento' => '2026-10-11',
    'local' => 'Arena Nova', 'custo' => '75.50', 'descricao' => 'Atualizado',
]);
$campeonatoAtualizado = Campeonato::buscar($pdo, $campeonatoAtualizarId);
Teste::igual('Depois de atualizar', $campeonatoAtualizado['nome'], 'I2: atualizar grava o nome novo quando o dono confere');
Teste::igual('2026-10-11', $campeonatoAtualizado['data_evento'], 'I2: atualizar grava a data nova');

$outroOrganizadorId = Auth::cadastrar($pdo, 'Outro Organizador', 'outroorg' . random_int(1000, 9999) . '@exemplo.com', 'senhaforte123');
$erroAtualizarDonoErrado = null;
try {
    Campeonato::atualizar($pdo, $campeonatoAtualizarId, $outroOrganizadorId, [
        'nome' => 'Tentativa de intruso', 'data_evento' => '2026-10-12',
        'local' => '', 'custo' => '', 'descricao' => '',
    ]);
} catch (RuntimeException $excecao) {
    $erroAtualizarDonoErrado = $excecao->getMessage();
}
Teste::verdade($erroAtualizarDonoErrado !== null, 'I2: atualizar com o organizador errado e recusado, nao grava por cima');
$campeonatoAposIntruso = Campeonato::buscar($pdo, $campeonatoAtualizarId);
Teste::igual('Depois de atualizar', $campeonatoAposIntruso['nome'], 'I2: o nome nao muda quando quem tenta atualizar nao e o dono');

$erroAtualizarIdInexistente = null;
try {
    Campeonato::atualizar($pdo, 999999999, $organizadorId, [
        'nome' => 'Nao existe', 'data_evento' => '2026-10-13',
        'local' => '', 'custo' => '', 'descricao' => '',
    ]);
} catch (RuntimeException $excecao) {
    $erroAtualizarIdInexistente = $excecao->getMessage();
}
Teste::verdade($erroAtualizarIdInexistente !== null, 'I2: atualizar com id inexistente lanca RuntimeException');

// ============================================================================
// Critico (revisao da tarefa 15): antes desta correcao, o unico chamador de
// producao de inscrever() (public/inscricoes.php) passava jogador_id fixo
// em nulo - toda inscricao real nascia convidada, e Ranking::acumulado, que
// exige jogador_id preenchido, nunca tinha ninguem para mostrar, por mais
// campeonatos que fossem encerrados. inscreverComEmail() e o metodo que
// public/inscricoes.php passa a chamar de verdade; os tres casos abaixo
// cobrem o contrato inteiro dele.
// ============================================================================
$campeonatoEmailId = Campeonato::criar($pdo, $organizadorId, [
    'nome' => 'Campeonato com vinculo por e-mail', 'data_evento' => '2026-10-20',
    'local' => 'Arena', 'custo' => '', 'descricao' => '',
]);

// Caso 1: sem e-mail continua convidado, jogador_id nulo, do jeito que
// inscrever() sempre fez.
$inscricaoConvidadoId = Campeonato::inscreverComEmail($pdo, $campeonatoEmailId, 'Convidado Email Vazio', '');
$buscaConvidado = $pdo->prepare('SELECT jogador_id FROM inscricoes WHERE id = ?');
$buscaConvidado->execute([$inscricaoConvidadoId]);
Teste::igual(
    null,
    $buscaConvidado->fetch()['jogador_id'],
    'inscreverComEmail: e-mail vazio inscreve como convidado (jogador_id nulo), igual a inscrever() direto'
);

// Caso 2: e-mail de conta ativa vincula o jogador_id certo. Digitado com
// espacos e maiusculas de proposito, para provar que passa pela mesma
// normalizacao que Auth::autenticar ja aplica (Auth::buscarPorEmailAtivo).
$emailJogadorVinculado = 'jogadorvinculado' . random_int(1000, 9999) . '@exemplo.com';
$jogadorEmailId = Auth::cadastrar($pdo, 'Jogador Vinculado', $emailJogadorVinculado, 'senhaforte123');

$inscricaoVinculadaId = Campeonato::inscreverComEmail(
    $pdo,
    $campeonatoEmailId,
    'Apelido na Quadra',
    '  ' . strtoupper($emailJogadorVinculado) . '  '
);
$buscaVinculada = $pdo->prepare('SELECT jogador_id FROM inscricoes WHERE id = ?');
$buscaVinculada->execute([$inscricaoVinculadaId]);
Teste::igual(
    $jogadorEmailId,
    (int) $buscaVinculada->fetch()['jogador_id'],
    'inscreverComEmail: e-mail de conta ativa vincula o jogador_id certo, mesmo com espacos e maiusculas'
);

// Caso 3: e-mail que nao bate com conta nenhuma recusa com
// InvalidArgumentException, em vez de cadastrar como convidado em
// silencio - um vinculo pedido que falha sem avisar e pior que uma recusa
// clara.
$totalAntesRecusa = count(Campeonato::listarInscricoes($pdo, $campeonatoEmailId));
$erroEmailInexistente = null;
try {
    Campeonato::inscreverComEmail(
        $pdo,
        $campeonatoEmailId,
        'Tentativa Sem Conta',
        'naoexisteconta' . random_int(1000, 9999) . '@exemplo.com'
    );
} catch (InvalidArgumentException $excecao) {
    $erroEmailInexistente = $excecao->getMessage();
}
Teste::verdade(
    $erroEmailInexistente !== null,
    'inscreverComEmail: e-mail sem conta ativa recusa com InvalidArgumentException, nao cadastra como convidado em silencio'
);
Teste::igual(
    $totalAntesRecusa,
    count(Campeonato::listarInscricoes($pdo, $campeonatoEmailId)),
    'inscreverComEmail: a recusa por e-mail inexistente nao inseriu nenhuma inscricao nova'
);

// Bonus: conta DESATIVADA tambem recusa - a mesma checagem "ativa = 1" que
// Auth::autenticar ja usa para bloquear login bloqueia o vinculo aqui.
$emailInativoVinculo = 'inativovinculo' . random_int(1000, 9999) . '@exemplo.com';
$jogadorInativoId = Auth::cadastrar($pdo, 'Jogador Inativo Vinculo', $emailInativoVinculo, 'senhaforte123');
$pdo->prepare('UPDATE users SET ativo = 0 WHERE id = ?')->execute([$jogadorInativoId]);

$erroContaInativa = null;
try {
    Campeonato::inscreverComEmail($pdo, $campeonatoEmailId, 'Tentativa Conta Inativa', $emailInativoVinculo);
} catch (InvalidArgumentException $excecao) {
    $erroContaInativa = $excecao->getMessage();
}
Teste::verdade(
    $erroContaInativa !== null,
    'inscreverComEmail: e-mail de conta desativada tambem recusa, nao so e-mail nunca cadastrado'
);

// O mesmo jogador_id inscrito duas vezes no mesmo campeonato via e-mail
// continua caindo na RuntimeException de sempre (UNIQUE KEY
// uk_camp_jogador), dentro de inscrever() - inscreverComEmail nao abre
// nenhum atalho por fora dessa regra.
$erroEmailDuplicado = null;
try {
    Campeonato::inscreverComEmail($pdo, $campeonatoEmailId, 'Apelido Repetido', $emailJogadorVinculado);
} catch (RuntimeException $excecao) {
    $erroEmailDuplicado = $excecao->getMessage();
}
Teste::igual(
    'Este jogador já está inscrito neste campeonato.',
    $erroEmailDuplicado,
    'inscreverComEmail: vincular o mesmo jogador duas vezes no mesmo campeonato continua com a mensagem de sempre'
);

$pdo->rollBack();

exit(Teste::resumo());
