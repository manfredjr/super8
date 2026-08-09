<?php
/** @var array $campeonato */
/** @var array $inscricoes */
/** @var string|null $erro */
/** @var string $erroClasse */
/** @var bool $jaSorteado */
/** @var bool $temPlacar */
/** @var string $nomeDigitado */
$total = count($inscricoes);
?>
<?php if ($erro !== null): ?><p class="<?= e($erroClasse) ?>"><?= e($erro) ?></p><?php endif; ?>

<p><?= $total ?> de 8 competidores.</p>

<div class="tabela-scroll">
<table>
  <tr><th>Posição</th><th>Nome</th><?php if (!$jaSorteado): ?><th></th><?php endif; ?></tr>
  <?php foreach ($inscricoes as $inscricao): ?>
    <tr>
      <td><?= $inscricao['posicao_sorteio'] !== null ? (int) $inscricao['posicao_sorteio'] : '-' ?></td>
      <td><?= e($inscricao['nome_exibicao']) ?></td>
      <?php if (!$jaSorteado): ?>
        <td>
          <form method="post" style="display:inline">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="remover">
            <input type="hidden" name="inscricao_id" value="<?= (int) $inscricao['id'] ?>">
            <button type="submit" class="secundario">Tirar</button>
          </form>
        </td>
      <?php endif; ?>
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
<?php elseif ($temPlacar): ?>
  <p class="aviso">O sorteio já foi feito e já há placar lançado neste campeonato. Não é possível refazer o sorteio agora.</p>
<?php else: ?>
  <form method="post" action="sortear.php">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $campeonato['id'] ?>">
    <button type="submit">
      <?= $jaSorteado ? 'Refazer o sorteio, apagando as rodadas atuais' : 'Sortear e gerar as rodadas' ?>
    </button>
  </form>
<?php endif; ?>

<p class="aviso">
  O nome do competidor aparece no chaveamento, na classificação e no ranking, junto da marca da MT como
  apoiadora do evento. Quem entra só pelo nome, sem conta, não passa por cadastro nem aceita o termo de uso:
  cabe a você informar essa pessoa de que o nome dela vai aparecer nessas telas. Sem conta, o competidor
  também não acumula pontos no ranking entre eventos.
</p>
