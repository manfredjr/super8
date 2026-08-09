<?php
/**
 * Marca da MT como apoiadora do evento.
 *
 * O sistema e gratuito porque a MT figura como apoiadora de cada campeonato
 * criado nele. A marca nao e enfeite: e a contrapartida do uso gratuito, e por
 * isso aparece em toda tela que o competidor ve.
 *
 * Duas formas, e a escolha vem do tempo que a pessoa passa na tela:
 *
 * - discreta: chaveamento e placar, usados em pe na beira da quadra entre
 *   partidas. Ali o placar tem que ganhar. Marca que rouba espaco faz o
 *   organizador voltar para o papel, e ai a publicidade nao alcanca ninguem.
 * - destacada: classificacao e ranking, que circulam no grupo depois do
 *   torneio. E onde a publicidade de fato trabalha.
 *
 * Enquanto nao houver arquivo de logotipo, a marca e texto. Trocar por imagem
 * e mudar so este arquivo.
 *
 * @var bool $marcaDiscreta definido pelo ponto de entrada; ausente vale destacada
 */
$discreta = isset($marcaDiscreta) && $marcaDiscreta === true;
?>
<?php if ($discreta): ?>
  <p class="marca-discreta">Apoio <strong>MT - Manfred Tecnologia</strong></p>
<?php else: ?>
  <div class="marca-apoio">
    <span class="marca-rotulo">Apoio e patrocinio</span>
    <strong class="marca-nome">MT - Manfred Tecnologia</strong>
  </div>
<?php endif; ?>
