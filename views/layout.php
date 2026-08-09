<?php
/** @var string $titulo */
/** @var string $conteudo */
/** @var bool|null $marcaDiscreta */
require_once __DIR__ . '/marca.php';
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> - Super 8</title>
<link rel="stylesheet" href="css/estilo.css?v=<?= e(VERSAO_ESTATICO) ?>">
</head>
<body>
<header class="topo">
  <a class="marca" href="index.php">Super 8</a>
  <?php if (usuarioLogado() !== null): ?>
    <nav>
      <a href="index.php">Campeonatos</a>
      <a href="ranking.php">Ranking</a>
      <a href="logout.php">Sair</a>
    </nav>
  <?php endif; ?>
</header>
<main>
  <h1><?= e($titulo) ?></h1>
  <?= $conteudo ?>
</main>
<footer class="rodape">
  <?php marcaMt($marcaDiscreta ?? false); ?>
  <p class="rodape-links">
    <a href="termo.php">Termo de uso</a>
    <a href="privacidade.php">Política de privacidade</a>
  </p>
</footer>
</body>
</html>
