<?php
/** @var array|null $campeonato */
/** @var string|null $erro */
/** @var bool|null $somenteLeitura */
$somenteLeitura = $somenteLeitura ?? false;
?>
<?php if ($erro !== null): ?><p class="erro"><?= e($erro) ?></p><?php endif; ?>

<form method="post">
  <?= csrf_campo() ?>
  <label>Nome do campeonato
    <input type="text" name="nome" required maxlength="160" value="<?= e($campeonato['nome'] ?? '') ?>"></label>
  <label>Data do evento
    <input type="date" name="data_evento" required value="<?= e($campeonato['data_evento'] ?? '') ?>"></label>
  <label>Local
    <input type="text" name="local" maxlength="160" value="<?= e($campeonato['local'] ?? '') ?>"></label>
  <label>Custo por jogador, em reais. Deixe vazio se for gratuito
    <input type="number" name="custo" step="0.01" min="0" value="<?= e($campeonato['custo'] ?? '') ?>"></label>
  <label>Descrição
    <textarea name="descricao" rows="4"><?= e($campeonato['descricao'] ?? '') ?></textarea></label>
  <?php if (!$somenteLeitura): ?>
    <button type="submit">Salvar</button>
  <?php endif; ?>
</form>
