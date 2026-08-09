<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

$id = getInteiro('id');
// Passa $usuario, ja carregado por exigirLogin() ali em cima: evita uma
// segunda consulta identica a users dentro de exigirDonoDoCampeonato.
$campeonato = exigirDonoDoCampeonato($pdo, $id, $usuario);

$linhas = Placar::classificacao($pdo, $id);

// Campeonato::partidasPendentes(), e nao uma consulta copiada aqui: e a
// mesma contagem que Campeonato::encerrar faz sob a propria trava para
// decidir se pode fechar o campeonato, so que sem trava (esta tela so
// precisa saber o que MOSTRAR). Existir como metodo unico, chamado tanto
// aqui quanto em encerrar.php, e o que impede as duas copias de divergirem
// se a regra mudar um dia (achado "Importante 2" da rodada de revisao).
$pendentes = Campeonato::partidasPendentes($pdo, $id);

// Um campeonato sem sorteio nao tem partida nenhuma, entao $pendentes acima
// da zero por definicao vazia (nao ha nada para contar) - nao pode ser lido
// como "pronto para encerrar". seed_sorteio nao nulo e o mesmo sinal que
// inscricoes.php ja usa como $jaSorteado: o sorteio grava a semente e cria
// as 14 partidas na mesma transacao, entao os dois nascem e continuam
// juntos. $temPartidas tira o botao da tela antes de oferecer uma acao que
// Campeonato::encerrar ja recusa por dentro (achado "Critico" da rodada de
// revisao: sem esta guarda em algum nivel, um campeonato de 8 inscritos sem
// sorteio encerrava e ficava travado para sempre).
$temPartidas = $campeonato['seed_sorteio'] !== null;

// public/encerrar.php e um endpoint proprio, sem tela: quando ele recusa o
// encerramento (sem sorteio, partida pendente, campeonato ja encerrado) ou
// confirma um sucesso, volta pra ca com a mensagem numa leitura de sessao
// de uso unico. lerAviso() (config/renderizar.php) e o ajudante comum a
// esta tela e a chaveamento.php/inscricoes.php.
['erro' => $erro, 'erroClasse' => $erroClasse] = lerAviso('avisoEncerramento', $id);

// Tela que circula fora da quadra, no grupo, depois do torneio - e onde a
// publicidade de fato trabalha (ver views/marca.php). marca.php documenta
// que esta tela quer as DUAS formas ao mesmo tempo: a discreta no rodape de
// sempre (por isso a flag `true` aqui, igual a chaveamento.php e
// placar.php) E a destacada acima da tabela, que a propria view chama
// embutida com marcaMt(false).
renderizar('classificacao', 'Classificação de ' . $campeonato['nome'], [
    'campeonato'   => $campeonato,
    'linhas'       => $linhas,
    'pendentes'    => $pendentes,
    'temPartidas'  => $temPartidas,
    'erro'         => $erro,
    'erroClasse'   => $erroClasse,
], true);
