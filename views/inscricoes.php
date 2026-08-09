<?php
/** @var array $campeonato */
/** @var array $inscricoes */
/** @var string|null $erro */
/** @var bool $jaSorteado */
/** @var bool $temPlacar */
/** @var string $nomeDigitado */
$total = count($inscricoes);
?>
<?php if ($erro !== null): ?><p class="erro"><?= e($erro) ?></p><?php endif; ?>

<p><?= $total ?> de 8 competidores.</p>

<div class="tabela-scroll">
<table>
  <tr><th>Posição</th><th>Nome</th><th></th></tr>
  <?php foreach ($inscricoes as $inscricao): ?>
    <tr>
      <td><?= $inscricao['posicao_sorteio'] !== null ? (int) $inscricao['posicao_sorteio'] : '-' ?></td>
      <td><?= e($inscricao['nome_exibicao']) ?></td>
      <td>
        <?php if (!$jaSorteado): ?>
          <form method="post" style="display:inline">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="remover">
            <input type="hidden" name="inscricao_id" value="<?= (int) $inscricao['id'] ?>">
            <button type="submit" class="secundario">Tirar</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</div>

<?php if ($total < 8): ?>
  <form method="post">
    <?= csrf_campo() ?>
    <label>Nome do competidor
      <input type="text" name="nome_exibicao" required maxlength="120" value="<?= e($nomeDigitado) ?>"></label>
    <button type="submit">Adicionar</button>
  </form>
  <p class="aviso">
    O nome do competidor aparece no chaveamento, na classificação e no ranking, junto da marca da MT como
    apoiadora do evento. Quem entra só pelo nome, sem conta, não passa por cadastro nem aceita o termo de uso:
    cabe a você informar essa pessoa de que o nome dela vai aparecer nessas telas. Sem conta, o competidor
    também não acumula pontos no ranking entre eventos.
  </p>
<?php elseif ($jaSorteado && $temPlacar): ?>
  <p class="aviso">O sorteio já foi feito e já há placar lançado neste campeonato. Não é possível refazer o sorteio agora.</p>
<?php else: ?>
  <form method="post" action="sortear.php">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $campeonato['id'] ?>">
    <button type="submit"><?= $jaSorteado ? 'Refazer o sorteio' : 'Sortear e gerar as rodadas' ?></button>
  </form>
<?php endif; ?>
