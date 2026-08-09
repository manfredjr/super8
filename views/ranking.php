<?php
/** @var array $linhas */
/** @var string $periodo */
/** @var ?string $de */
/** @var ?string $ate */
/** @var bool $dataInvalida */
/** @var bool $intervaloInvertido */

// Posicao exibida: repete o numero da posicao anterior quando os games
// empatam com a linha de cima, em vez de contar 1, 2, 3 como se houvesse
// ordem real entre quem fez o mesmo tanto de games. Mesma ideia de
// views/classificacao.php, so que comparando apenas por games - o unico
// criterio que Ranking::acumulado usa antes do nome (ORDER BY games DESC,
// nome ASC): o motor nao calcula saldo, vitorias nem confronto direto entre
// eventos diferentes, entao nao ha o que reaproveitar alem do "games iguais
// = mesma posicao".
$posicoes = [];
$posicaoAtual = 0;
foreach ($linhas as $indice => $linha) {
    if ($indice === 0 || $linha['games'] !== $linhas[$indice - 1]['games']) {
        $posicaoAtual = $indice + 1;
    }
    $posicoes[$indice] = $posicaoAtual;
}
$contagemPorPosicao = array_count_values($posicoes);
?>
<form method="get" action="ranking.php">
  <label>Período
    <select name="periodo">
      <option value="mes"   <?= $periodo === 'mes' ? 'selected' : '' ?>>Mês atual</option>
      <option value="ano"   <?= $periodo === 'ano' ? 'selected' : '' ?>>Ano atual</option>
      <option value="tudo"  <?= $periodo === 'tudo' ? 'selected' : '' ?>>Tudo</option>
      <option value="livre" <?= $periodo === 'livre' ? 'selected' : '' ?>>Intervalo livre</option>
    </select>
  </label>
  <?php if ($periodo === 'livre'): ?>
    <label>De <input type="date" name="de" value="<?= e($de) ?>"></label>
    <label>Até <input type="date" name="ate" value="<?= e($ate) ?>"></label>
  <?php endif; ?>
  <button type="submit">Filtrar</button>
</form>

<?php if ($dataInvalida): ?>
  <p class="aviso">A data informada não é válida, por isso esse limite do período não foi aplicado.</p>
<?php endif; ?>

<?php marcaMt(false); ?>

<?php if ($linhas === [] && $intervaloInvertido): ?>
  <p class="aviso">O início do intervalo é depois do fim. Troque as duas datas para ver os campeonatos do período.</p>
<?php elseif ($linhas === []): ?>
  <p>Nenhum campeonato encerrado neste período.</p>
<?php else: ?>
  <div class="tabela-scroll">
  <table>
    <tr>
      <th>#</th>
      <th>Jogador</th>
      <th>Eventos</th>
      <th>Jogos</th>
      <th>Games</th>
      <th>Sofridos</th>
      <th>Saldo</th>
      <th>Média por evento</th>
    </tr>
    <?php foreach ($linhas as $indice => $linha): ?>
      <tr>
        <td>
          <?= (int) $posicoes[$indice] ?>
          <?php if ($contagemPorPosicao[$posicoes[$indice]] > 1): ?> <span class="marca-empate">empate</span><?php endif; ?>
        </td>
        <td><?= e($linha['nome']) ?></td>
        <td><?= (int) $linha['eventos'] ?></td>
        <td><?= (int) $linha['jogadas'] ?></td>
        <td class="games-destaque"><?= (int) $linha['games'] ?></td>
        <td><?= (int) $linha['sofridos'] ?></td>
        <td><?= (int) $linha['saldo'] ?></td>
        <td><?= e(number_format((float) $linha['media'], 1, ',', '.')) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
<?php endif; ?>

<p class="nota-rodape">
  Somente campeonatos encerrados entram neste ranking. Um campeonato em andamento não altera a posição de
  ninguém até ser encerrado.
</p>

<p class="nota-rodape">
  Competidor cadastrado só pelo nome, sem conta no sistema, aparece na classificação do próprio evento mas
  nunca entra aqui: sem conta não há como garantir que dois convidados com o mesmo nome, em eventos
  diferentes, são a mesma pessoa.
</p>

<p class="nota-rodape">
  O nome exibido aqui é o nome da conta, que pode ser diferente do nome de exibição usado na classificação
  de um evento específico. Um mesmo jogador pode aparecer com nomes distintos nas duas telas.
</p>
