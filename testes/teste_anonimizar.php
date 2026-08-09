<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Rodizio.php';
require __DIR__ . '/../src/Sorteio.php';
require __DIR__ . '/../src/Validador.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Campeonato.php';
require __DIR__ . '/../src/Placar.php';
require __DIR__ . '/../src/Ranking.php';

echo "Auth::anonimizarPorEmail\n";

$pdo = db();
$pdo->beginTransaction();

$sufixo = random_int(1000, 9999);

/**
 * Monta um campeonato completo (8 inscritos, sorteado, com as 14 partidas
 * gravadas) e o encerra. Devolve o id do campeonato. Mesmo formato usado em
 * teste_ranking.php, reduzido a uma funcao porque este arquivo monta mais de
 * um campeonato encerrado.
 */
function montarCampeonatoEncerrado(
    PDO $pdo,
    int $organizadorId,
    string $nomeCampeonato,
    string $dataEvento,
    int $semente,
    string $nomeTitular,
    ?int $jogadorTitularId,
    string $prefixoConvidado
): int {
    $campeonatoId = Campeonato::criar($pdo, $organizadorId, [
        'nome' => $nomeCampeonato, 'data_evento' => $dataEvento,
        'local' => 'Arena', 'custo' => '', 'descricao' => '',
    ]);
    Campeonato::inscrever($pdo, $campeonatoId, $nomeTitular, $jogadorTitularId);
    foreach (range(2, 8) as $numero) {
        Campeonato::inscrever($pdo, $campeonatoId, "{$prefixoConvidado} {$numero}", null);
    }
    Campeonato::sortear($pdo, $campeonatoId, $semente);

    $buscaPartidas = $pdo->prepare(
        'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
    );
    $buscaPartidas->execute([$campeonatoId]);
    foreach ($buscaPartidas->fetchAll(PDO::FETCH_COLUMN) as $partidaId) {
        Placar::gravar($pdo, $campeonatoId, (int) $partidaId, 6, 3, $organizadorId);
    }
    Campeonato::encerrar($pdo, $campeonatoId);

    return $campeonatoId;
}

/** Soma bruta de games_a/games_b (o lado que for) de todas as partidas de uma inscricao, direto do banco. */
function gamesTotaisDaInscricao(PDO $pdo, int $inscricaoId): int
{
    $busca = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN dupla_a_j1 = ? OR dupla_a_j2 = ? THEN games_a ELSE games_b END) AS total
         FROM partidas
         WHERE dupla_a_j1 = ? OR dupla_a_j2 = ? OR dupla_b_j1 = ? OR dupla_b_j2 = ?'
    );
    $busca->execute([$inscricaoId, $inscricaoId, $inscricaoId, $inscricaoId, $inscricaoId, $inscricaoId]);

    return (int) $busca->fetchColumn();
}

$organizadorId = Auth::cadastrar($pdo, 'Organizador Anon', "orgnaon{$sufixo}@exemplo.com", 'senhaforte123');

// ============================================================================
// Cenario central: um titular com conta joga em DOIS campeonatos diferentes,
// os dois ja ENCERRADOS - o caso normal desde que inscreverComEmail passou a
// vincular conta de verdade (tarefa 15). Cobre exatamente o que a
// especificacao, o termo de uso e a politica de privacidade prometem juntos:
// o nome de exibicao de cada inscricao vira um identificador anonimo, o
// titular sai do ranking acumulado, os placares de cada campeonato ja
// encerrado ficam exatamente iguais, e nada disso viola a UNIQUE KEY
// uk_camp_jogador (campeonato_id, jogador_id) ao zerar jogador_id nos dois
// campeonatos na mesma transacao.
// ============================================================================
$emailTitular = "titularanon{$sufixo}@exemplo.com";
$jogadorTitularId = Auth::cadastrar($pdo, 'Titular Original', $emailTitular, 'senhaforte123');

$campeonatoAId = montarCampeonatoEncerrado(
    $pdo, $organizadorId, 'Etapa Anon A', '2026-09-01', 9101,
    'Apelido na Quadra A', $jogadorTitularId, "Convidado AnonA {$sufixo}"
);
$campeonatoBId = montarCampeonatoEncerrado(
    $pdo, $organizadorId, 'Etapa Anon B', '2026-09-08', 9102,
    'Apelido na Quadra B', $jogadorTitularId, "Convidado AnonB {$sufixo}"
);

$buscaInscricaoA = $pdo->prepare('SELECT id, nome_exibicao FROM inscricoes WHERE campeonato_id = ? AND jogador_id = ?');
$buscaInscricaoA->execute([$campeonatoAId, $jogadorTitularId]);
$inscricaoA = $buscaInscricaoA->fetch();
$buscaInscricaoB = $pdo->prepare('SELECT id, nome_exibicao FROM inscricoes WHERE campeonato_id = ? AND jogador_id = ?');
$buscaInscricaoB->execute([$campeonatoBId, $jogadorTitularId]);
$inscricaoB = $buscaInscricaoB->fetch();

Teste::verdade($inscricaoA !== false && $inscricaoB !== false, 'checagem do proprio teste: o titular esta inscrito e vinculado nos dois campeonatos antes da exclusao');

$gamesAntesA = gamesTotaisDaInscricao($pdo, (int) $inscricaoA['id']);
$gamesAntesB = gamesTotaisDaInscricao($pdo, (int) $inscricaoB['id']);
Teste::verdade($gamesAntesA > 0 && $gamesAntesB > 0, 'checagem do proprio teste: as duas inscricoes tem games gravados antes da exclusao');

// O titular aparece no ranking acumulado antes da exclusao, com os dois eventos somados.
$linhasAntes = Ranking::acumulado($pdo, null, null);
$linhaAntes = null;
foreach ($linhasAntes as $linha) {
    if ((int) $linha['jogador_id'] === $jogadorTitularId) {
        $linhaAntes = $linha;
    }
}
Teste::verdade($linhaAntes !== null, 'checagem do proprio teste: o titular aparece no ranking acumulado antes da exclusao');
Teste::igual(2, (int) $linhaAntes['eventos'], 'checagem do proprio teste: os 2 campeonatos encerrados contam para o titular antes da exclusao');

// Uma tentativa de login errada antes da exclusao, para provar que a linha de
// tentativas_login (que guarda o e-mail do titular em TEXTO PURO) some junto -
// sem isso ela sobrevive a exclusao inteira e so some se uma falha de login
// FUTURA contra o mesmo e-mail disparar a limpeza por idade, o que numa conta
// que acabou de ser desativada pode ser nunca.
Auth::registrarFalha($pdo, $emailTitular);
$buscaTentativaAntes = $pdo->prepare('SELECT COUNT(*) FROM tentativas_login WHERE email = ?');
$buscaTentativaAntes->execute([$emailTitular]);
Teste::igual(1, (int) $buscaTentativaAntes->fetchColumn(), 'checagem do proprio teste: existe uma linha de tentativas_login para o e-mail do titular antes da exclusao');

// --- A exclusao em si -------------------------------------------------------
$idAnonimizado = Auth::anonimizarPorEmail($pdo, '  ' . strtoupper($emailTitular) . '  ');
Teste::igual($jogadorTitularId, $idAnonimizado, 'anonimizarPorEmail devolve o id da conta, mesmo com espacos e maiusculas no e-mail (mesma normalizacao de autenticar/buscarPorEmailAtivo)');

$buscaTentativaDepois = $pdo->prepare('SELECT COUNT(*) FROM tentativas_login WHERE email = ?');
$buscaTentativaDepois->execute([$emailTitular]);
Teste::igual(0, (int) $buscaTentativaDepois->fetchColumn(), 'a linha de tentativas_login com o e-mail do titular em texto puro some junto com a exclusao');

// --- inscricoes.jogador_id foi zerado nos DOIS campeonatos, sem violar a UNIQUE KEY ---
$buscaJogadorIdA = $pdo->prepare('SELECT jogador_id FROM inscricoes WHERE id = ?');
$buscaJogadorIdA->execute([$inscricaoA['id']]);
Teste::igual(null, $buscaJogadorIdA->fetch()['jogador_id'], 'a inscricao do campeonato A perde o vinculo com a conta (jogador_id NULL)');

$buscaJogadorIdB = $pdo->prepare('SELECT jogador_id FROM inscricoes WHERE id = ?');
$buscaJogadorIdB->execute([$inscricaoB['id']]);
Teste::igual(null, $buscaJogadorIdB->fetch()['jogador_id'], 'a inscricao do campeonato B tambem perde o vinculo (as duas linhas em NULL nao colidem entre si na UNIQUE KEY)');

// --- nome_exibicao de cada campeonato VIRA o identificador anonimo: e o que
// o competidor de fato ve no chaveamento e na classificacao, nao users.nome ---
$apelidoEsperado = 'Jogador removido ' . $jogadorTitularId;

$buscaNomeA = $pdo->prepare('SELECT nome_exibicao FROM inscricoes WHERE id = ?');
$buscaNomeA->execute([$inscricaoA['id']]);
Teste::igual(
    $apelidoEsperado,
    $buscaNomeA->fetch()['nome_exibicao'],
    'o nome de exibicao no campeonato A vira o identificador anonimo, do jeito que o termo de uso promete'
);

$buscaNomeB = $pdo->prepare('SELECT nome_exibicao FROM inscricoes WHERE id = ?');
$buscaNomeB->execute([$inscricaoB['id']]);
Teste::igual(
    $apelidoEsperado,
    $buscaNomeB->fetch()['nome_exibicao'],
    'o nome de exibicao no campeonato B tambem vira o identificador anonimo'
);

// --- Os placares sobrevivem exatamente iguais ------------------------------
Teste::igual(
    $gamesAntesA,
    gamesTotaisDaInscricao($pdo, (int) $inscricaoA['id']),
    'os games do campeonato A sao exatamente os mesmos de antes da exclusao'
);
Teste::igual(
    $gamesAntesB,
    gamesTotaisDaInscricao($pdo, (int) $inscricaoB['id']),
    'os games do campeonato B sao exatamente os mesmos de antes da exclusao'
);

// --- A conta foi anonimizada e desativada -----------------------------------
$buscaUsuario = $pdo->prepare(
    'SELECT nome, email, senha_hash, google_id, foto_url, ativo FROM users WHERE id = ?'
);
$buscaUsuario->execute([$jogadorTitularId]);
$usuarioDepois = $buscaUsuario->fetch();

Teste::igual('Jogador removido ' . $jogadorTitularId, $usuarioDepois['nome'], 'o nome da conta vira o apelido anonimo com o id');
Teste::igual(null, $usuarioDepois['email'], 'o e-mail da conta e limpo');
Teste::igual(null, $usuarioDepois['senha_hash'], 'o hash de senha e limpo (login nunca mais funciona com essa conta)');
Teste::igual(null, $usuarioDepois['google_id'], 'o google_id e limpo');
Teste::igual(null, $usuarioDepois['foto_url'], 'a foto e limpa');
Teste::igual(0, (int) $usuarioDepois['ativo'], 'a conta fica desativada');

// --- O titular sai do ranking acumulado, nos dois eventos -------------------
$linhasDepois = Ranking::acumulado($pdo, null, null);
$linhaDepois = null;
foreach ($linhasDepois as $linha) {
    if ((int) $linha['jogador_id'] === $jogadorTitularId) {
        $linhaDepois = $linha;
    }
}
Teste::verdade($linhaDepois === null, 'o titular anonimizado desaparece do ranking acumulado (Ranking::acumulado exige jogador_id preenchido)');

// --- Os outros 14 convidados dos dois campeonatos nao foram tocados ---------
$totalInscricoesA = $pdo->prepare('SELECT COUNT(*) FROM inscricoes WHERE campeonato_id = ?');
$totalInscricoesA->execute([$campeonatoAId]);
Teste::igual(8, (int) $totalInscricoesA->fetchColumn(), 'o campeonato A continua com os 8 inscritos de sempre, nenhuma linha foi apagada');

$totalInscricoesB = $pdo->prepare('SELECT COUNT(*) FROM inscricoes WHERE campeonato_id = ?');
$totalInscricoesB->execute([$campeonatoBId]);
Teste::igual(8, (int) $totalInscricoesB->fetchColumn(), 'o campeonato B continua com os 8 inscritos de sempre, nenhuma linha foi apagada');

// ============================================================================
// E-mail sem conta nenhuma: nao muda nada no banco, devolve nulo.
// ============================================================================
$totalUsersAntes = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$resultadoInexistente = Auth::anonimizarPorEmail($pdo, "naoexisteconta{$sufixo}@exemplo.com");
Teste::igual(null, $resultadoInexistente, 'e-mail sem conta nenhuma devolve nulo, sem lancar excecao');
Teste::igual(
    $totalUsersAntes,
    (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'e-mail sem conta nenhuma nao insere nem altera nenhuma linha de users'
);

// ============================================================================
// Rodar a exclusao de novo com o e-mail ORIGINAL (ja anonimizado, portanto ja
// nao existe mais esse e-mail em users): devolve nulo, e nao ha segunda conta
// "Jogador removido X" duplicada por engano.
// ============================================================================
$resultadoRepetido = Auth::anonimizarPorEmail($pdo, $emailTitular);
Teste::igual(null, $resultadoRepetido, 'repetir a exclusao com o e-mail original (ja limpo da conta) nao acha ninguem e devolve nulo');

// ============================================================================
// Guarda de titular sem NENHUMA inscricao vinculada: so a conta e anonimizada,
// nenhum UPDATE em inscricoes acha linha nenhuma, sem erro.
// ============================================================================
$emailSemInscricao = "semvinculo{$sufixo}@exemplo.com";
$jogadorSemInscricaoId = Auth::cadastrar($pdo, 'Sem Vinculo Nenhum', $emailSemInscricao, 'senhaforte123');
$idAnonimizadoSemInscricao = Auth::anonimizarPorEmail($pdo, $emailSemInscricao);
Teste::igual($jogadorSemInscricaoId, $idAnonimizadoSemInscricao, 'titular sem nenhuma inscricao vinculada tambem e anonimizado normalmente');

$pdo->rollBack();

exit(Teste::resumo());
