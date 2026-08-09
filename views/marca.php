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
 * E funcao, e nao um require solto, porque uma unica tela pode precisar das
 * duas formas ao mesmo tempo - a classificacao, por exemplo, quer a marca
 * discreta no rodape de sempre E a marca destacada acima da tabela. Uma
 * variavel de escopo ambiente so da conta de uma forma por tela; a funcao
 * aceita o argumento em cada chamada.
 *
 * Enquanto nao houver arquivo de logotipo, a marca e texto. Trocar por imagem
 * e mudar so esta funcao.
 */
function marcaMt(bool $discreta = false): void
{
    if ($discreta) {
        ?>
        <p class="apoio-discreta">Apoio <strong>MT - Manfred Tecnologia</strong></p>
        <?php
        return;
    }
    ?>
    <div class="apoio-destacada">
      <span class="apoio-rotulo">Apoio e patrocínio</span>
      <strong class="apoio-nome">MT - Manfred Tecnologia</strong>
    </div>
    <?php
}
