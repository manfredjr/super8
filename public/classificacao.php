<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = getInteiro('id');
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);

$linhas = Placar::classificacao($pdo, $id);

// Partida pendente: nem encerrada nem com nenhum dos dois games
// preenchidos - a mesma condicao larga que Campeonato::encerrar usa, sob a
// propria trava, para decidir se pode fechar o campeonato (contrato
// documentado no docblock de Campeonato::encerrar). Contar do mesmo jeito
// aqui e o que faz esta tela e o motor concordarem sobre o que falta: se a
// conta divergisse (por exemplo so olhando encerrada = 0), a mensagem de
// "faltam X partidas" podia dizer um numero e o motor recusar por outro
// motivo.
$contaPendentes = $pdo->prepare(
    'SELECT COUNT(*) FROM partidas p JOIN rodadas r ON r.id = p.rodada_id
     WHERE r.campeonato_id = ? AND p.encerrada = 0 AND p.games_a IS NULL AND p.games_b IS NULL'
);
$contaPendentes->execute([$id]);
$pendentes = (int) $contaPendentes->fetchColumn();

// public/encerrar.php e um endpoint proprio, sem tela: quando ele recusa o
// encerramento (partida pendente, campeonato ja encerrado) ou confirma um
// sucesso, volta pra ca com a mensagem numa leitura de sessao de uso unico.
// lerAviso() (config/renderizar.php) e o ajudante comum a esta tela e a
// chaveamento.php/inscricoes.php.
['erro' => $erro, 'erroClasse' => $erroClasse] = lerAviso('avisoEncerramento', $id);

// Tela que circula fora da quadra, no grupo, depois do torneio - e onde a
// publicidade de fato trabalha (ver views/marca.php). Por isso renderizar()
// e chamado sem a flag de marca discreta (o padrao ja e a marca destacada),
// diferente de chaveamento.php e placar.php.
renderizar('classificacao', 'Classificação de ' . $campeonato['nome'], [
    'campeonato' => $campeonato,
    'linhas'     => $linhas,
    'pendentes'  => $pendentes,
    'erro'       => $erro,
    'erroClasse' => $erroClasse,
]);
