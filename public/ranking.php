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

// Usa Validador::dataValida diretamente, a mesma checagem que
// Ranking::acumulado ja aplica por dentro (formato AAAA-MM-DD E calendario
// real via checkdate) - nao uma expressao regular local que so olhasse o
// formato. So a forma (regex) nao bastava: "2026-02-31" bate no formato mas
// nao existe no calendario, e sem o checkdate esse valor passava intacto
// para o motor, que rejeitava por dentro e devolvia a consulta SEM aquele
// lado do filtro - a tela mostrava o historico inteiro sem avisar que a
// data digitada foi ignorada. $dataInvalida vira aviso na view exatamente
// por isso: usuario digita algo, tela precisa dizer o que fez com aquilo.
$dataInvalida = false;
foreach (['de', 'ate'] as $campo) {
    $valor = $$campo;
    if ($valor !== null && $valor !== '' && !Validador::dataValida($valor)) {
        $$campo = null;
        $dataInvalida = true;
    }
}

// Comeco depois do fim nunca pode filtrar nada (Ranking::acumulado exige
// data_evento >= de E <= ate ao mesmo tempo), mas devolver a lista vazia
// sem dizer o motivo aponta a causa para "nao ha campeonato no periodo"
// quando o problema de verdade e o intervalo trocado. Calculado ANTES de
// consultar o motor, e nao depois de ver a lista vazia: uma consulta que
// legitimamente nao acha nada (periodo correto, so que sem evento) nao pode
// acionar este aviso.
$intervaloInvertido = $de !== null && $ate !== null && $de > $ate;

$linhas = Ranking::acumulado($pdo, $de, $ate);

// A tela circula como a classificacao circula (ver views/marca.php): marca
// discreta sempre no rodape (por isso o `true` aqui) e a destacada embutida
// perto da tabela, chamada dentro da propria view com marcaMt(false).
renderizar('ranking', 'Ranking acumulado', [
    'linhas'             => $linhas,
    'periodo'            => $periodo,
    'de'                 => $de,
    'ate'                => $ate,
    'dataInvalida'       => $dataInvalida,
    'intervaloInvertido' => $intervaloInvertido,
], true);
