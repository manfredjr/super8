<?php
/** @var array $linhas */
/** @var string $periodo */
/** @var ?string $de */
/** @var ?string $ate */
?>
<form method="get" action="ranking.php">
  <label>Periodo
    <select name="periodo">
      <option value="mes"   <?= $periodo === 'mes' ? 'selected' : '' ?>>Mes atual</option>
      <option value="ano"   <?= $periodo === 'ano' ? 'selected' : '' ?>>Ano atual</option>
      <option value="tudo"  <?= $periodo === 'tudo' ? 'selected' : '' ?>>Tudo</option>
      <option value="livre" <?= $periodo === 'livre' ? 'selected' : '' ?>>Intervalo livre</option>
    </select>
  </label>
  <?php if ($periodo === 'livre'): ?>
    <label>De <input type="date" name="de" value="<?= e($de) ?>"></label>
    <label>Ate <input type="date" name="ate" value="<?= e($ate) ?>"></label>
  <?php endif; ?>
  <button type="submit">Filtrar</button>
</form>

<?php marcaMt(false); ?>

<?php if ($linhas === []): ?>
  <p>Nenhum campeonato encerrado neste periodo.</p>
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
      <th>Media por evento</th>
    </tr>
    <?php foreach ($linhas as $posicao => $linha): ?>
      <tr>
        <td><?= $posicao + 1 ?></td>
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
  Somente campeonatos encerrados entram neste ranking. Um campeonato em andamento nao altera a posicao de
  ninguem ate ser encerrado.
</p>

<p class="nota-rodape">
  Competidor cadastrado so pelo nome, sem conta no sistema, aparece na classificacao do proprio evento mas
  nunca entra aqui: sem conta nao ha como garantir que dois convidados com o mesmo nome, em eventos
  diferentes, sao a mesma pessoa.
</p>
