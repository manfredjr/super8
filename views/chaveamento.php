<?php
/** @var array $campeonato */
/** @var array $rodadas */
/** @var bool $encerrado */
/** @var string|null $erro */
/** @var string $erroClasse */
?>
<?php if ($erro !== null): ?><p class="<?= e($erroClasse) ?>"><?= e($erro) ?></p><?php endif; ?>

<?php if ($rodadas === []): ?>
  <p>As rodadas ainda não foram geradas. Cadastre os 8 competidores e faça o sorteio.</p>
  <p><a href="inscricoes.php?id=<?= (int) $campeonato['id'] ?>">Ir para os competidores</a></p>
<?php else: ?>
  <?php if ($encerrado): ?>
    <p class="aviso">Campeonato encerrado. O placar não pode mais ser alterado.</p>
  <?php endif; ?>

  <?php foreach ($rodadas as $rodada): ?>
    <h2>Rodada <?= (int) $rodada['numero'] ?></h2>
    <?php foreach ($rodada['partidas'] as $partida): ?>
      <div class="partida" id="partida-<?= (int) $partida['id'] ?>">
        <p class="partida-quadra">Quadra <?= (int) $partida['quadra'] ?></p>

        <?php if ($encerrado): ?>
          <p class="dupla">
            <span class="dupla-nome"><?= e($partida['a1']) ?> e <?= e($partida['a2']) ?></span>
            <strong><?= $partida['games_a'] !== null ? (int) $partida['games_a'] : '-' ?></strong>
          </p>
          <p class="versus">x</p>
          <p class="dupla">
            <span class="dupla-nome"><?= e($partida['b1']) ?> e <?= e($partida['b2']) ?></span>
            <strong><?= $partida['games_b'] !== null ? (int) $partida['games_b'] : '-' ?></strong>
          </p>
        <?php else: ?>
          <form class="placar" method="post" action="placar.php">
            <?= csrf_campo() ?>
            <input type="hidden" name="campeonato_id" value="<?= (int) $campeonato['id'] ?>">
            <input type="hidden" name="partida_id" value="<?= (int) $partida['id'] ?>">
            <label class="dupla">
              <span class="dupla-nome"><?= e($partida['a1']) ?> e <?= e($partida['a2']) ?></span>
              <input type="number" name="games_a" min="0" max="99" step="1" inputmode="numeric" required
                     value="<?= $partida['games_a'] !== null ? (int) $partida['games_a'] : '' ?>">
            </label>
            <p class="versus">x</p>
            <label class="dupla">
              <span class="dupla-nome"><?= e($partida['b1']) ?> e <?= e($partida['b2']) ?></span>
              <input type="number" name="games_b" min="0" max="99" step="1" inputmode="numeric" required
                     value="<?= $partida['games_b'] !== null ? (int) $partida['games_b'] : '' ?>">
            </label>
            <button type="submit"><?= (int) $partida['encerrada'] === 1 ? 'Corrigir placar' : 'Gravar placar' ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <p><a href="classificacao.php?id=<?= (int) $campeonato['id'] ?>">Ver classificação</a></p>

  <p class="nota-rodape">Semente do sorteio: <?= (int) $campeonato['seed_sorteio'] ?>. Guardar esse número permite refazer o mesmo sorteio e conferir o chaveamento.</p>
<?php endif; ?>
