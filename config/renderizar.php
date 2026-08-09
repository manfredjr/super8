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

/**
 * Le um aviso de sessao de uso unico deixado por um endpoint sem tela
 * propria (sortear.php, placar.php) que precisou recusar algo, ou confirmar
 * um sucesso, e voltar para a tela de origem em vez de responder uma frase
 * solta sem layout nem caminho de volta. So aceita o aviso se ele foi
 * guardado para o MESMO $id - sem essa checagem, abrir duas abas em
 * campeonatos diferentes podia mostrar o aviso de um na tela do outro.
 * unset() sempre, tenha batido o id ou nao, pra nao deixar sobra apontando
 * pro campeonato errado.
 *
 * Devolve sempre as duas chaves 'erro' (string|null) e 'erroClasse'
 * (string), prontas para entrar direto no array de $dados de renderizar().
 * 'classe' ausente no aviso guardado (o caso de avisoSorteio, que sempre
 * foi recusa de estado, nunca sucesso) vira 'aviso'.
 */
function lerAviso(string $chave, int $id): array
{
    $erro = null;
    $erroClasse = 'erro';

    $aviso = $_SESSION[$chave] ?? null;
    if (is_array($aviso) && ($aviso['id'] ?? null) === $id) {
        $erro = $aviso['mensagem'] ?? null;
        $erroClasse = $aviso['classe'] ?? 'aviso';
    }
    unset($_SESSION[$chave]);

    return ['erro' => $erro, 'erroClasse' => $erroClasse];
}
