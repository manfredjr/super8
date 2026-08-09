<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/acesso.php';
require __DIR__ . '/../src/Auth.php';

echo "Acesso\n";

// M3 (rodada de revisao): so incluir acesso.php ja tem que deixar a sessao
// ativa, sem exigir uma chamada explicita de iniciarSessao() - um ponto de
// entrada que esquecesse dessa chamada cairia num redirecionamento infinito
// para o login sem erro nenhum apontando o motivo, porque exigirLogin()
// nunca enxergaria um usuario gravado numa sessao que nunca chegou a
// comecar de verdade.
Teste::igual(
    PHP_SESSION_ACTIVE,
    session_status(),
    'M3: so incluir config/acesso.php ja inicia a sessao sozinho, sem exigir chamada explicita'
);

$pdo = db();

/**
 * O caminho de nao-dono (e o de sessao invalida/desativada) chama exit(),
 * entao roda em outro processo. Ver o comentario no topo do arquivo
 * ajudante.
 */
function rodarAjudanteDono(string $ajudante, int $idUsuario, int $idCampeonato): array
{
    $saida = [];
    $codigo = 0;
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ajudante) . ' '
            . escapeshellarg((string) $idUsuario) . ' ' . escapeshellarg((string) $idCampeonato),
        $saida,
        $codigo
    );
    return [$codigo, implode("\n", $saida)];
}

// As linhas criadas aqui nao ficam dentro de uma transacao: o ajudante roda
// em outro processo, com sua propria conexao, e precisa enxergar os dados
// ja gravados.
$idOrganizador1 = null;
$idOrganizador2 = null;
$idCampeonato = null;

// exigirDonoDoCampeonato() (por dentro de exigirLogin()) roda EM PROCESSO no
// caminho do dono, mais abaixo, e o caminho de rejeicao dela termina com
// exit('Campeonato não encontrado.'). exit() com string devolve codigo de
// saida 0, e testes/executar.php so olha o codigo de saida: se a checagem de
// dono der errado por engano (por exemplo, um id trocado), o script para no
// meio, sem rodar o resto das asserticoes, e o executar.php ainda assim
// imprime TUDO PASSOU. Pior: PHP nao roda finally quando exit() e chamado,
// entao um try/finally comum para limpar as linhas criadas aqui NAO seria
// suficiente. A solucao para os dois problemas de uma vez e a mesma usada
// no ajudante: um register_shutdown_function, que roda sempre, mesmo depois
// de exit(), e que so deixa o processo terminar com codigo zero se o script
// chegou ao fim de verdade.
$terminouDireito = false;
register_shutdown_function(function () use (&$terminouDireito, &$idOrganizador1, &$idOrganizador2, &$idCampeonato, $pdo) {
    if ($idCampeonato !== null) {
        $pdo->prepare('DELETE FROM campeonatos WHERE id = ?')->execute([$idCampeonato]);
    }
    if ($idOrganizador2 !== null) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$idOrganizador2]);
    }
    if ($idOrganizador1 !== null) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$idOrganizador1]);
    }
    if (!$terminouDireito) {
        exit(9);
    }
});

// B2: usuarioLogado() falha fechado quando a sessao nao tem um array.
$_SESSION['usuario'] = 'nao e um array';
Teste::igual(null, usuarioLogado(), 'usuarioLogado devolve nulo quando a sessao nao tem um array');
unset($_SESSION['usuario']);
Teste::igual(null, usuarioLogado(), 'usuarioLogado devolve nulo sem sessao nenhuma');

$organizador1 = 'dono' . uniqid() . '@exemplo.com';
$organizador2 = 'intruso' . uniqid() . '@exemplo.com';
$idOrganizador1 = Auth::cadastrar($pdo, 'Dono', $organizador1, 'senhaforte123');
$idOrganizador2 = Auth::cadastrar($pdo, 'Intruso', $organizador2, 'senhaforte123');

$insereCampeonato = $pdo->prepare(
    "INSERT INTO campeonatos (organizador_id, nome, data_evento, status, criado_em)
     VALUES (?, ?, CURDATE(), 'rascunho', NOW())"
);
$insereCampeonato->execute([$idOrganizador1, 'Campeonato de Teste']);
$idCampeonato = (int) $pdo->lastInsertId();

// Caminho do dono: logica pura, sem exit no caminho feliz, testada no mesmo
// processo. Se a busca de dono falhar por engano, exigirDonoDoCampeonato()
// chama exit() aqui mesmo, e e exatamente o que o register_shutdown_function
// acima existe para pegar.
$_SESSION['usuario'] = ['id' => $idOrganizador1];
$campeonatoLido = exigirDonoDoCampeonato($pdo, $idCampeonato);
Teste::igual($idCampeonato, (int) $campeonatoLido['id'], 'o dono recebe a linha do proprio campeonato');
Teste::verdade(
    isset($_SESSION['usuario']['nome']),
    'exigirLogin escreve a linha fresca de volta na sessao (usuarioLogado nao fica servindo a copia velha, so com o id)'
);
unset($_SESSION['usuario']);

// exigirDonoDoCampeonato aceita o usuario ja carregado como terceiro
// parametro, para quem chama (um controlador que ja rodou exigirLogin por
// conta propria) nao pagar uma segunda consulta identica a users. Precisa
// devolver a mesma linha de campeonato que o caminho sem esse parametro
// devolveu acima, e continuar recusando dono errado mesmo recebendo o
// usuario pronto.
$usuarioCarregado = ['id' => $idOrganizador1];
$campeonatoLidoComUsuario = exigirDonoDoCampeonato($pdo, $idCampeonato, $usuarioCarregado);
Teste::igual(
    $idCampeonato,
    (int) $campeonatoLidoComUsuario['id'],
    'exigirDonoDoCampeonato com o usuario ja carregado devolve a mesma linha do campeonato'
);

$ajudante = __DIR__ . '/_ajuda_dono_campeonato.php';

// Caminho de quem nao e dono: o campeonato existe, mas pertence a outra
// pessoa.
[$codigoIntruso, $saidaIntruso] = rodarAjudanteDono($ajudante, $idOrganizador2, $idCampeonato);
Teste::verdade($codigoIntruso !== 0, 'quem nao e dono do campeonato e rejeitado');
Teste::verdade(str_contains($saidaIntruso, 'Campeonato não encontrado.'), 'a rejeicao mostra a mensagem generica');
Teste::verdade(str_contains($saidaIntruso, 'codigo_http=404'), 'a rejeicao usa 404, nao 403 (403 confirmaria que o id existe)');

// Mesmo teste, mas o id nem existe. A resposta precisa ser identica a de
// cima: nada pode diferenciar "existe e nao e seu" de "nao existe".
[$codigoInexistente, $saidaInexistente] = rodarAjudanteDono($ajudante, $idOrganizador2, 999999999);
Teste::verdade($codigoInexistente !== 0, 'campeonato inexistente tambem e rejeitado');
Teste::igual(
    $saidaIntruso,
    $saidaInexistente,
    'a resposta e igual, byte a byte, para "existe mas nao e seu" e para "nao existe": nao da para diferenciar os dois casos pela saida'
);

// B1: desativar o dono precisa cortar o acesso na hora, mesmo que a sessao
// dele ainda "pareca" valida. exigirLogin tem que reler o banco a cada
// chamada, e nao confiar na copia parada em $_SESSION.
$pdo->prepare('UPDATE users SET ativo = 0 WHERE id = ?')->execute([$idOrganizador1]);
[$codigoDesativado, $saidaDesativado] = rodarAjudanteDono($ajudante, $idOrganizador1, $idCampeonato);
Teste::verdade($codigoDesativado !== 0, 'dono desativado e rejeitado mesmo sendo dono de verdade do campeonato');
Teste::verdade(
    !str_contains($saidaDesativado, 'Campeonato não encontrado.'),
    'a rejeicao de usuario desativado acontece dentro de exigirLogin, antes mesmo de chegar na checagem de dono'
);
Teste::verdade(!str_contains($saidaDesativado, 'dono confirmado'), 'usuario desativado nao recebe o campeonato');

unset($_SESSION['usuario']);
$terminouDireito = true;
exit(Teste::resumo());
