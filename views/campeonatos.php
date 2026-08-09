<?php
/** @var array $campeonatos */
$rotulos = [
    'rascunho'     => 'Rascunho',
    'sorteado'     => 'Sorteado',
    'em_andamento' => 'Em andamento',
    'encerrado'    => 'Encerrado',
];
?>
<p><a href="campeonato.php"><button type="button">Novo campeonato</button></a></p>

<?php if ($campeonatos === []): ?>
  <p>Você ainda não criou nenhum campeonato.</p>
<?php else: ?>
  <div class="tabela-scroll">
    <table>
      <tr><th>Nome</th><th>Data</th><th>Local</th><th>Situação</th><th></th></tr>
      <?php foreach ($campeonatos as $campeonato): ?>
        <tr>
          <td><?= e($campeonato['nome']) ?></td>
          <td><?= e(date('d/m/Y', strtotime($campeonato['data_evento']))) ?></td>
          <td><?= e($campeonato['local']) ?></td>
          <td><?= e($rotulos[$campeonato['status']]) ?></td>
          <td>
            <a href="campeonato.php?id=<?= (int) $campeonato['id'] ?>">Editar</a>
            <a href="inscricoes.php?id=<?= (int) $campeonato['id'] ?>">Competidores</a>
            <a href="chaveamento.php?id=<?= (int) $campeonato['id'] ?>">Chaveamento</a>
            <a href="classificacao.php?id=<?= (int) $campeonato['id'] ?>">Classificação</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>
