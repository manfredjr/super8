<?php
/** @var array $campeonato */
/** @var array $linhas */
/** @var int $pendentes */
/** @var string|null $erro */
/** @var string $erroClasse */

// Posicao exibida: cresce a cada linha, exceto quando a linha esta marcada
// empatado E o grupo (mesmo games/saldo/vitorias da linha anterior) e o
// mesmo da linha anterior - nesse caso repete o numero da linha anterior,
// em vez de contar 3, 4, 5 como se houvesse uma ordem real entre elas.
// Placar::classificarLinhas ja deixa o grupo empatado inteiro em posicoes
// consecutivas (o desempate por confronto so reordena DENTRO do mesmo
// grupo de games/saldo/vitorias), entao comparar so com a linha
// imediatamente anterior basta.
$posicoes = [];
$posicaoAtual = 0;
$chaveAnterior = null;
foreach ($linhas as $indice => $linha) {
    $chave = $linha['games'] . '|' . $linha['saldo'] . '|' . $linha['vitorias'];
    if (!($linha['empatado'] && $chave === $chaveAnterior)) {
        $posicaoAtual = $indice + 1;
    }
    $posicoes[$indice] = $posicaoAtual;
    $chaveAnterior = $chave;
}
?>
<?php if ($erro !== null): ?><p class="<?= e($erroClasse) ?>"><?= e($erro) ?></p><?php endif; ?>

<?php if ($pendentes > 0): ?>
  <p class="aviso">
    <?= $pendentes ?> partida(s) ainda sem placar lançado. A classificação abaixo mostra o que já se sabe até
    agora e muda conforme os resultados entram.
  </p>
<?php endif; ?>

<?php marcaMt(false); ?>

<?php if ($linhas === []): ?>
  <p>Ainda não há competidor inscrito neste campeonato.</p>
<?php else: ?>
  <div class="tabela-scroll">
  <table>
    <tr>
      <th>#</th>
      <th>Jogador</th>
      <th>Games</th>
      <th>Sofridos</th>
      <th>Saldo</th>
      <th>Vitórias</th>
      <th>Jogos</th>
    </tr>
    <?php foreach ($linhas as $indice => $linha): ?>
      <tr>
        <td><?= (int) $posicoes[$indice] ?><?php if ($linha['empatado']): ?> <span class="marca-empate">empate</span><?php endif; ?></td>
        <td><?= e($linha['nome']) ?></td>
        <td><?= (int) $linha['games'] ?></td>
        <td><?= (int) $linha['sofridos'] ?></td>
        <td><?= (int) $linha['saldo'] ?></td>
        <td class="vitorias-destaque"><?= (int) $linha['vitorias'] ?></td>
        <td><?= (int) $linha['jogadas'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <p class="nota-rodape">
    Critérios de desempate, nesta ordem: games ganhos, saldo de games, vitórias de partida e confronto direto
    entre os empatados. Quando esses quatro critérios não separam um grupo por completo - por exemplo três
    jogadores em que um venceu o segundo, o segundo venceu o terceiro e o terceiro venceu o primeiro - a tela
    marca esse grupo como empate, em vez de mostrar uma ordem que o próprio confronto direto não sustenta.
  </p>

  <p class="nota-rodape">
    Games ganhos decidem antes de vitórias de partida: por isso um jogador com quatro partidas vencidas pode
    aparecer abaixo de outro com uma só. É a regra do formato Super 8, e a coluna Vitórias fica em destaque
    aqui para essa conta ficar visível na tela, em vez de alguém precisar somar no papel na beira da quadra.
  </p>
<?php endif; ?>

<?php if ($campeonato['status'] === 'encerrado'): ?>
  <p class="sucesso">Campeonato encerrado. Os resultados já entraram no ranking acumulado.</p>
<?php elseif ($pendentes === 0 && $linhas !== []): ?>
  <p class="aviso">
    Encerrar torna o placar e a data do evento fixos - nenhum dos dois pode mais mudar depois - e leva este
    evento para o ranking acumulado entre campeonatos. Não é possível desfazer o encerramento.
  </p>
  <form method="post" action="encerrar.php">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $campeonato['id'] ?>">
    <button type="submit">Encerrar o campeonato</button>
  </form>
<?php endif; ?>
