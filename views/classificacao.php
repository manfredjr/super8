<?php
/** @var array $campeonato */
/** @var array $linhas */
/** @var int $pendentes */
/** @var bool $temPartidas */
/** @var string|null $erro */
/** @var string $erroClasse */

// Posicao exibida: cresce a cada linha, exceto quando a linha esta marcada
// empatado E o grupo (mesma chave_grupo da linha anterior) e o mesmo da
// linha anterior - nesse caso repete o numero da linha anterior, em vez de
// contar 3, 4, 5 como se houvesse uma ordem real entre elas. chave_grupo
// vem pronta de Placar::classificarLinhas (games|saldo|vitorias da propria
// linha) em vez de remontada aqui: assim um criterio novo no motor nunca
// pode ficar dessincronizado do agrupamento que esta view enxerga (achado
// "Menor 4" da rodada de revisao). Placar::classificarLinhas ja deixa o
// grupo empatado inteiro em posicoes consecutivas (o desempate por
// confronto so reordena DENTRO do mesmo grupo), entao comparar so com a
// linha imediatamente anterior basta.
$posicoes = [];
$posicaoAtual = 0;
$chaveAnterior = null;
foreach ($linhas as $indice => $linha) {
    $chave = $linha['chave_grupo'];
    if (!($linha['empatado'] && $chave === $chaveAnterior)) {
        $posicaoAtual = $indice + 1;
    }
    $posicoes[$indice] = $posicaoAtual;
    $chaveAnterior = $chave;
}
?>
<?php if ($erro !== null): ?><p class="<?= e($erroClasse) ?>"><?= e($erro) ?></p><?php endif; ?>

<?php if (!$temPartidas): ?>
  <p>Este campeonato ainda não foi sorteado. A classificação aparece depois que as rodadas forem geradas.</p>
  <p><a href="chaveamento.php?id=<?= (int) $campeonato['id'] ?>">Ir para o chaveamento</a></p>
<?php else: ?>
  <?php if ($pendentes > 0): ?>
    <p class="aviso">
      <?= $pendentes ?> partida(s) ainda sem placar lançado. A classificação abaixo mostra o que já se sabe até
      agora e muda conforme os resultados entram.
    </p>
  <?php endif; ?>

  <?php marcaMt(false); ?>

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
        <td class="games-destaque"><?= (int) $linha['games'] ?></td>
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
    Games ganhos decidem a posição, antes de vitórias de partida: por isso um jogador com quatro partidas
    vencidas pode aparecer abaixo de outro com uma só. É a regra do formato Super 8. A coluna Games fica em
    destaque por ser quem decide; Vitórias aparece em segundo plano, só para essa conta ficar visível, não
    para sugerir que é ela quem ordena a tabela.
  </p>
<?php endif; ?>

<?php if ($campeonato['status'] === 'encerrado'): ?>
  <p class="aviso">Este campeonato está encerrado. Os resultados entram no ranking acumulado.</p>
<?php elseif ($temPartidas && $pendentes === 0): ?>
  <p class="aviso">
    Encerrar torna os dados do evento e o placar fixos - nada disso pode mais mudar depois - e leva este
    evento para o ranking acumulado entre campeonatos. Não é possível desfazer o encerramento.
  </p>
  <form method="post" action="encerrar.php">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $campeonato['id'] ?>">
    <button type="submit">Encerrar o campeonato</button>
  </form>
<?php endif; ?>
