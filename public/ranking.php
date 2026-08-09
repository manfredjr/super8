<?php

require __DIR__ . '/cabecalho.php';

$pdo = dbSeguro();
$usuario = exigirLogin($pdo);

// Ranking e acumulado entre jogadores, nao entre campeonatos de um
// organizador: nao ha exigirDonoDoCampeonato aqui de proposito. E o que a
// especificacao registra na secao 7 ("mesma soma, agora entre campeonatos
// encerrados") sem nenhuma restricao por organizador - $usuario serve so
// para confirmar que ha sessao valida, igual a toda outra tela.

// Le do proprio $_GET, e nao de $_POST, porque esta e a unica tela do
// projeto sem caminho de escrita: o filtro de periodo e navegavel por link
// (favoritar, compartilhar um periodo especifico), nao uma acao que precise
// de CSRF. getTexto() (config/csrf.php) garante que um valor forjado como
// array em vez de string nunca chega ao switch abaixo como "Array".
$periodo = getTexto('periodo');

switch ($periodo) {
    case 'mes':
        $de = date('Y-m-01');
        $ate = date('Y-m-t');
        break;
    case 'livre':
        $de = getTexto('de');
        $ate = getTexto('ate');
        break;
    case 'tudo':
        $de = null;
        $ate = null;
        break;
    default:
        $periodo = 'ano';
        $de = date('Y-01-01');
        $ate = date('Y-12-31');
}

// Confere o formato antes de repassar a Ranking::acumulado. O motor
// (Validador::dataValida) ja trata qualquer coisa fora do formato AAAA-MM-DD
// como "sem filtro" - inclusive uma data de calendario impossivel, tipo
// 31 de fevereiro - e nunca deixa o valor cru chegar ao SQL fora de um
// parametro preparado. Esta checagem aqui e so a mesma cautela que todo
// campo vindo de fora do sistema recebe neste projeto: normalizar para nulo
// o quanto antes, em vez de confiar que a camada seguinte vai lidar com
// lixo.
foreach (['de', 'ate'] as $campo) {
    $valor = $$campo;
    if ($valor !== null && $valor !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
        $$campo = null;
    }
}

$linhas = Ranking::acumulado($pdo, $de, $ate);

// A tela circula como a classificacao circula (ver views/marca.php): marca
// discreta sempre no rodape (por isso o `true` aqui) e a destacada embutida
// perto da tabela, chamada dentro da propria view com marcaMt(false).
renderizar('ranking', 'Ranking acumulado', [
    'linhas'  => $linhas,
    'periodo' => $periodo,
    'de'      => $de,
    'ate'     => $ate,
], true);
