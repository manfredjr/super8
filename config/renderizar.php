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
 */
function renderizar(string $view, string $titulo, array $dados = [], bool $marcaDiscreta = false): void
{
    extract($dados);

    ob_start();
    try {
        require __DIR__ . '/../views/' . $view . '.php';
    } finally {
        $conteudo = ob_get_clean();
    }

    require __DIR__ . '/../views/layout.php';
}
