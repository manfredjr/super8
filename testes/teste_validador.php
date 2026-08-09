<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../src/Validador.php';

echo "Validador\n";

// --- dataValida --------------------------------------------------------
Teste::verdade(Validador::dataValida('2026-08-07'), 'data no formato certo e no calendario e valida');
Teste::verdade(!Validador::dataValida(null), 'nulo nao e valido');
Teste::verdade(!Validador::dataValida(''), 'string vazia nao e valida');
Teste::verdade(!Validador::dataValida('31/12/2026'), 'formato com barras nao e valido');
Teste::verdade(!Validador::dataValida('2026-13-01'), 'mes 13 (fora do calendario) nao e valido, mesmo batendo no formato');
Teste::verdade(!Validador::dataValida('2026-02-30'), 'dia 30 de fevereiro (nao existe) nao e valido');
Teste::verdade(!Validador::dataValida('2026-04-31'), 'dia 31 de abril (abril so tem 30 dias) nao e valido');
Teste::verdade(Validador::dataValida('2028-02-29'), '29 de fevereiro de 2028 (ano bissexto) e valido');
Teste::verdade(!Validador::dataValida('2027-02-29'), '29 de fevereiro de 2027 (nao bissexto) nao e valido');
Teste::verdade(!Validador::dataValida('2026-8-7'), 'mes ou dia sem os dois digitos nao bate no formato AAAA-MM-DD');

// --- textoObrigatorio ----------------------------------------------------
Teste::igual('Ola', Validador::textoObrigatorio('Ola', 10, 'o campo'), 'aceita e devolve texto dentro do limite');
Teste::igual('Ola', Validador::textoObrigatorio('  Ola  ', 10, 'o campo'), 'faz trim no texto');

$erro = null;
try {
    Validador::textoObrigatorio('', 10, 'o nome do campeonato');
} catch (InvalidArgumentException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa string vazia');
Teste::igual('Informe o nome do campeonato.', $erro, 'a mensagem de campo vazio usa o rotulo recebido, para dizer qual campo');

$erro = null;
try {
    Validador::textoObrigatorio('   ', 10, 'o campo');
} catch (InvalidArgumentException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa string so com espacos (vira vazia depois do trim)');

$erro = null;
try {
    Validador::textoObrigatorio(null, 10, 'o campo');
} catch (InvalidArgumentException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa nulo');

$erro = null;
try {
    Validador::textoObrigatorio(str_repeat('a', 11), 10, 'o local');
} catch (InvalidArgumentException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'recusa texto maior que o limite');
Teste::igual('O local pode ter no máximo 10 caracteres.', $erro, 'a mensagem de limite usa o rotulo recebido, com a primeira letra maiuscula');

Teste::igual(str_repeat('a', 10), Validador::textoObrigatorio(str_repeat('a', 10), 10, 'o campo'), 'aceita texto no limite exato (nao e "maior que", entao passa)');

// Limite e de CARACTERES, nao de bytes: acento nao pode custar mais que 1
// caractere no limite, senao um nome com acento seria recusado com menos
// caracteres do que o limite realmente permite.
$textoAcentuado = str_repeat('a', 9) . 'a';
Teste::igual(10, mb_strlen('áéíóúãõêôç'), 'checagem do proprio teste: 10 caracteres acentuados tem 10 caracteres (mb_strlen)');
Teste::igual('áéíóúãõêôç', Validador::textoObrigatorio('áéíóúãõêôç', 10, 'o campo'), 'limite conta caracteres (mb_strlen), nao bytes: 10 caracteres acentuados passam no limite 10');

// --- textoOpcional ---------------------------------------------------------
Teste::igual(null, Validador::textoOpcional('', 10, 'o campo'), 'string vazia vira nulo, nao erro');
Teste::igual(null, Validador::textoOpcional('   ', 10, 'o campo'), 'string so com espacos vira nulo');
Teste::igual(null, Validador::textoOpcional(null, 10, 'o campo'), 'nulo continua nulo');
Teste::igual('Ola', Validador::textoOpcional('  Ola  ', 10, 'o campo'), 'texto presente e limpo (trim) e devolvido');

$erro = null;
try {
    Validador::textoOpcional(str_repeat('a', 11), 10, 'o local');
} catch (InvalidArgumentException $excecao) {
    $erro = $excecao->getMessage();
}
Teste::verdade($erro !== null, 'texto opcional presente ainda recusa quando passa do limite');
Teste::igual('O local pode ter no máximo 10 caracteres.', $erro, 'textoOpcional tambem usa o rotulo recebido na mensagem de limite');

exit(Teste::resumo());
