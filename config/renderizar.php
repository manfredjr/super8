<?php

/**
 * Monta uma tela dentro do layout padrao. Substitui os quatro passos que
 * cada ponto de entrada repetia (ob_start, require da view, ob_get_clean,
 * require do layout): eram quatro linhas identicas em cada arquivo, e ja
 * sao varios pontos de entrada. O bloco try/finally garante que o buffer
 * fecha mesmo se a view lancar excecao - sem isso, uma excecao no meio da
 * view deixaria ob_start() aberto e o buffer vazando para a proxima saida.
 *
 * Cada chave de $dados vira uma variavel local dentro da view, pelo mesmo
 * nome. $marcaDiscreta segue para o layout (que decide qual forma da marca
 * da MT desenhar no rodape); a view pode tambem chamar marcaMt() diretamente
 * no meio do conteudo quando precisar das duas formas na mesma tela.
 *
 * extract() roda com EXTR_SKIP, e nao no modo padrao: sem isso, uma tela que
 * passasse ['titulo' => ...] ou ['marcaDiscreta' => ...] dentro de $dados
 * sobrescreveria em silencio o argumento correspondente desta funcao, que ja
 * existe no escopo local antes do extract rodar. EXTR_SKIP preserva a
 * variavel que ja existe e ignora a chave de $dados nesse caso. $conteudo e
 * excecao a essa protecao: ele ainda nao existe no momento do extract (so
 * nasce mais abaixo, depois do ob_get_clean), entao uma chave 'conteudo' em
 * $dados passaria por cima dele aqui - mas a atribuicao seguinte sempre
 * sobrescreve de volta com o buffer de verdade, entao o resultado final
 * continua correto de qualquer forma.
 */
function renderizar(string $view, string $titulo, array $dados = [], bool $marcaDiscreta = false): void
{
    extract($dados, EXTR_SKIP);

    ob_start();
    try {
        require __DIR__ . '/../views/' . $view . '.php';
    } finally {
        $conteudo = ob_get_clean();
    }

    require __DIR__ . '/../views/layout.php';
}
