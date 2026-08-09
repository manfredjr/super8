<?php
/** @var string|null $erro */
/** @var string $versao */
?>
<?php if ($erro !== null): ?><p class="erro"><?= e($erro) ?></p><?php endif; ?>

<h2>Entrar</h2>
<form method="post" action="login.php">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="entrar">
  <label>E-mail <input type="email" name="email" required autocomplete="email"></label>
  <label>Senha <input type="password" name="senha" required autocomplete="current-password"></label>
  <button type="submit">Entrar</button>
</form>

<h2>Criar conta de organizador</h2>
<form method="post" action="login.php">
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="cadastrar">
  <input type="hidden" name="termo_versao" value="<?= e($versao) ?>">
  <label>Nome <input type="text" name="nome" required></label>
  <label>E-mail <input type="email" name="email" required autocomplete="email"></label>
  <label>Senha <input type="password" name="senha" required minlength="8" autocomplete="new-password"></label>
  <label class="linha">
    <input type="checkbox" name="aceite" value="1" required>
    <span>Li e aceito o <a href="termo.php" target="_blank">termo de uso</a> e a
    <a href="privacidade.php" target="_blank">politica de privacidade</a>. Entendo que o sistema
    e gratuito porque a MT - Manfred Tecnologia figura como apoiadora dos campeonatos que eu criar,
    com a marca dela nas telas do evento.</span>
  </label>
  <button type="submit">Criar conta</button>
</form>
