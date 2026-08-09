<?php

require_once __DIR__ . '/sessao.php';

// Chama no momento do include, pelo mesmo motivo de config/acesso.php: este
// arquivo le e escreve $_SESSION (csrf_token/csrf_conferir) sem exigir
// sessao.php antes, e um ponto de entrada que esquecesse de iniciar a sessao
// sozinho falharia em silencio, sem erro nenhum apontando o motivo.
iniciarSessao();

function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Le um campo de $_POST como texto, nunca como array. `?? ''` sozinho cobre
 * a chave ausente, mas nao cobre o tipo errado: um formulario aceita
 * "campo[]=x" por construcao (varios campos com o mesmo name), e um (string)
 * direto sobre um array emite "Array to string conversion" - alem de vazar
 * o caminho do servidor no aviso, o valor devolvido e o literal "Array", que
 * passa reto pela validacao de tamanho e cadastra o texto errado de verdade.
 * is_string() aqui fecha isso: qualquer coisa que nao seja string vira
 * string vazia, e a validacao de campo obrigatorio recusa do jeito certo.
 */
function postTexto(string $chave): string
{
    $valor = $_POST[$chave] ?? '';
    return is_string($valor) ? $valor : '';
}

/**
 * Le um campo de $_POST como inteiro, aceitando so uma string de digitos.
 * (int) direto sobre o valor nao chega nem perto de bastar: (int) sobre um
 * array nao vazio vira 1 (nunca a quantidade de elementos, um detalhe facil
 * de esquecer, e sem aviso nenhum do PHP), e (int) sobre uma string que nao
 * comeca com digito ("abc", uma string vazia) vira 0 tambem em silencio -
 * as duas conversoes produzem um numero plausivel para um campo que na
 * verdade veio errado ou ausente, e "games_a[]=9" ou "games_a=abc" seriam
 * gravados como placar de verdade (1 e 0 games) sem nenhum aviso apontando
 * o motivo. $padrao (fora da faixa de qualquer campo numerico real deste
 * projeto, por isso o -1 default) e o que volta quando o valor enviado nao
 * e uma string de digitos, forcando a validacao de faixa que cada chamador
 * ja faz a recusar, em vez de aceitar um valor que nunca foi digitado de
 * verdade. Nao aceita sinal nem ponto decimal: todo campo numerico deste
 * projeto (id de linha, games) e inteiro nao negativo.
 */
function postInteiro(string $chave, int $padrao = -1): int
{
    $valor = $_POST[$chave] ?? null;
    if (!is_string($valor) || !ctype_digit(trim($valor))) {
        return $padrao;
    }

    return (int) trim($valor);
}

/**
 * Le um campo de $_GET como inteiro, aceitando so uma string de digitos.
 * Mesmo motivo de postInteiro(), a mesma classe de furo: (int) direto sobre
 * um array nao vazio vira 1, e (int) direto sobre uma string que nao comeca
 * com digito vira 0, as duas em silencio - um "id[]=x" ou "id=abc" forjado
 * na URL passaria reto por um (int) direto e caia num id de linha plausivel,
 * embora nunca digitado de verdade. $padrao default e 0, e nao -1 como em
 * postInteiro(): todo id lido por aqui e id de linha (campeonato, partida),
 * nunca um placar, e 0 ja e um id que nunca existe de verdade - basta para
 * exigirDonoDoCampeonato()/o motor recusarem do jeito certo mais abaixo, sem
 * precisar de uma faixa negativa reservada como a de postInteiro().
 */
function getInteiro(string $chave, int $padrao = 0): int
{
    $valor = $_GET[$chave] ?? null;
    if (!is_string($valor) || !ctype_digit(trim($valor))) {
        return $padrao;
    }

    return (int) trim($valor);
}

/**
 * Le um campo de $_GET como texto, nunca como array. Mesmo motivo de
 * postTexto(): "periodo[]=x" e aceito pela sintaxe de query string por
 * construcao, e um (string) direto sobre esse array emitiria "Array to
 * string conversion" e devolveria o literal "Array", que passaria reto
 * pelo switch de periodo em ranking.php e caberia sem erro num valor que
 * ninguem digitou. is_string() fecha isso: qualquer coisa que nao seja
 * string vira string vazia, e o switch cai no default do mesmo jeito que
 * cairia para um periodo desconhecido de verdade.
 */
function getTexto(string $chave): string
{
    $valor = $_GET[$chave] ?? '';
    return is_string($valor) ? $valor : '';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_conferir(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(403);
        exit('Pedido invalido. Recarregue a pagina e tente de novo.');
    }
}
