<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Auth.php';

echo "Auth\n";

$pdo = db();
$pdo->beginTransaction();

// Usada pelo teste de corrida de cadastro, mais abaixo: essa conexao roda
// fora da transacao principal (autocommit), entao o rollBack do fim do
// teste nao alcanca o que ela grava. Guardamos aqui para limpar por conta
// propria, depois que a transacao principal ja tiver liberado qualquer
// travamento que a corrida deixe para tras.
$pdo2 = null;
$emailCorrida = null;

try {
    $email = 'teste' . uniqid() . '@exemplo.com';

    $id = Auth::cadastrar($pdo, 'Organizador Teste', $email, 'senhaforte123');
    Teste::verdade($id > 0, 'cadastrar devolve o id do usuario');

    $busca = $pdo->prepare('SELECT senha_hash, e_organizador FROM users WHERE id = ?');
    $busca->execute([$id]);
    $linha = $busca->fetch();
    Teste::verdade($linha['senha_hash'] !== 'senhaforte123', 'a senha nao fica em texto no banco');
    Teste::verdade(password_verify('senhaforte123', $linha['senha_hash']), 'o hash confere com a senha');
    Teste::igual(1, (int) $linha['e_organizador'], 'quem se cadastra vira organizador');

    $infoHash = password_get_info($linha['senha_hash']);
    Teste::igual('argon2id', $infoHash['algoName'], 'o hash usa Argon2id, nao outro algoritmo');

    $usuario = Auth::autenticar($pdo, $email, 'senhaforte123');
    Teste::verdade($usuario !== null, 'autentica com a senha certa');
    Teste::igual($id, (int) $usuario['id'], 'devolve o usuario correto');
    Teste::verdade(!array_key_exists('senha_hash', $usuario), 'o retorno de autenticar nao tem a chave senha_hash (nem nula)');

    Teste::igual(null, Auth::autenticar($pdo, $email, 'senhaerrada'), 'recusa a senha errada');
    Teste::igual(null, Auth::autenticar($pdo, 'naoexiste@exemplo.com', 'qualquer'), 'recusa e-mail inexistente');

    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Curta', 'curta' . $email, '1234567');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa senha com menos de 8 caracteres');

    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Repetido', $email, 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa e-mail ja cadastrado');

    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Invalido', 'nao-e-email', 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa e-mail malformado');

    // A5: nome com mais de 120 caracteres.
    $erro = null;
    try {
        Auth::cadastrar($pdo, str_repeat('a', 121), 'nomelongo' . uniqid() . '@exemplo.com', 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa nome com mais de 120 caracteres');

    // A5: e-mail com mais de 160 caracteres, mas que FILTER_VALIDATE_EMAIL
    // aceita (local de 64 caracteres, dominio valido). Sem a checagem de
    // tamanho, a coluna VARCHAR(160) corta o valor em silencio, porque o
    // servidor nao roda em modo estrito.
    $emailLongo = str_repeat('a', 64) . '@' . str_repeat('b', 60) . str_repeat('.c', 40) . '.com';
    Teste::verdade((bool) filter_var($emailLongo, FILTER_VALIDATE_EMAIL), 'checagem do proprio teste: o e-mail longo usado aqui e valido pelo filtro do PHP');
    Teste::verdade(strlen($emailLongo) > 160, 'checagem do proprio teste: o e-mail longo usado aqui passa de 160 caracteres');
    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Nome Normal', $emailLongo, 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa e-mail com mais de 160 caracteres');

    // A6: senha de 7 caracteres multibyte (14 bytes). strlen() diria que tem
    // 14 e deixaria passar; a regra e de caracteres, entao mb_strlen precisa
    // contar 7 e recusar.
    $senhaMultibyte = 'áéíóúãõ';
    Teste::igual(7, mb_strlen($senhaMultibyte), 'checagem do proprio teste: a senha multibyte usada aqui tem 7 caracteres');
    Teste::verdade(strlen($senhaMultibyte) >= 8, 'checagem do proprio teste: a mesma senha ocupa 8 bytes ou mais');
    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Multibyte', 'multibyte' . uniqid() . '@exemplo.com', $senhaMultibyte);
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa senha com 7 caracteres multibyte, mesmo tendo 8 bytes ou mais (conta caracteres, nao bytes)');

    // A7: corrida de cadastro. Uma segunda conexao (fora da transacao
    // principal) insere e comita o mesmo e-mail depois que a transacao
    // principal ja tirou sua foto de leitura repeatable-read. O SELECT de
    // pre-checagem de cadastrar() nao enxerga esse commit alheio (a foto e
    // mais antiga), entao ele segue para o INSERT, que esbarra na restricao
    // UNIQUE de verdade. O catch em cadastrar() precisa converter isso na
    // mesma InvalidArgumentException do caminho normal.
    $emailCorrida = 'corrida' . uniqid() . '@exemplo.com';
    $dsnSegunda = 'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4';
    $pdo2 = new PDO($dsnSegunda, DB_USER, DB_SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo2->prepare(
        'INSERT INTO users (nome, email, senha_hash, e_organizador, ativo, criado_em)
         VALUES (?, ?, ?, 1, 1, NOW())'
    )->execute(['Corrida', $emailCorrida, password_hash('outrasenha123', PASSWORD_ARGON2ID)]);

    $buscaCorrida = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $buscaCorrida->execute([$emailCorrida]);
    Teste::igual(false, $buscaCorrida->fetch(), 'checagem do proprio teste: a pre-checagem da transacao principal nao enxerga o commit da segunda conexao (isolamento repeatable-read)');

    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Perdeu a corrida', $emailCorrida, 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::igual('Ja existe conta com esse e-mail.', $erro, 'corrida de cadastro: quem perde recebe a mesma mensagem de e-mail duplicado, nao um erro de banco cru');

    // Usuario com senha_hash nulo (por exemplo, cadastrado via login social)
    // nao pode autenticar por senha. password_verify(x, null) tambem devolve
    // false (PHP converte o nulo em string vazia), entao so conferir o
    // retorno nao prova que a checagem de nulo existe: se ela sumir, o
    // resultado final continua sendo nulo, so que passando por dentro do
    // password_verify e disparando um aviso de depreciacao do PHP por
    // receber nulo onde so se aceita string. Um manipulador de erro proprio
    // enxerga esse aviso mesmo com error_reporting do jeito que esta hoje
    // (que nao mostra E_DEPRECATED por padrao), entao a ausencia de avisos
    // e a prova de que a checagem explicita continua no lugar.
    $emailSemSenha = 'semsenha' . uniqid() . '@exemplo.com';
    $pdo->prepare(
        'INSERT INTO users (nome, email, senha_hash, e_organizador, ativo, criado_em)
         VALUES (?, ?, NULL, 1, 1, NOW())'
    )->execute(['Sem Senha', $emailSemSenha]);
    $avisosSenhaNula = [];
    set_error_handler(function (int $nivel, string $mensagem) use (&$avisosSenhaNula): bool {
        $avisosSenhaNula[] = $mensagem;
        return true;
    });
    $resultadoSenhaNula = Auth::autenticar($pdo, $emailSemSenha, 'qualquercoisa');
    restore_error_handler();
    Teste::igual(null, $resultadoSenhaNula, 'usuario com senha_hash nulo nao consegue autenticar');
    Teste::igual([], $avisosSenhaNula, 'senha_hash nulo e barrado antes de chegar em password_verify, sem avisos do PHP por passar nulo onde se espera string');

    // Usuario desativado nao pode autenticar, mesmo com a senha certa.
    $emailInativo = 'inativo' . uniqid() . '@exemplo.com';
    $idInativo = Auth::cadastrar($pdo, 'Inativo', $emailInativo, 'senhaforte123');
    $pdo->prepare('UPDATE users SET ativo = 0 WHERE id = ?')->execute([$idInativo]);
    Teste::igual(null, Auth::autenticar($pdo, $emailInativo, 'senhaforte123'), 'usuario inativo nao consegue autenticar mesmo com a senha certa');

    // autenticar precisa normalizar o e-mail que recebe (maiusculas e
    // espacos), assim como cadastrar ja normaliza o que grava.
    $emailNormalizacao = 'normaliza' . uniqid() . '@exemplo.com';
    Auth::cadastrar($pdo, 'Normalizacao', $emailNormalizacao, 'senhaforte123');
    $logadoComVariacao = Auth::autenticar($pdo, '  ' . strtoupper($emailNormalizacao) . '  ', 'senhaforte123');
    Teste::verdade($logadoComVariacao !== null, 'autentica mesmo enviando o e-mail com espacos e maiusculas (autenticar normaliza antes de buscar)');

    // registrarFalha tambem precisa normalizar: chamadas com variacoes de
    // caixa e espaco do mesmo e-mail devem cair na mesma linha.
    $emailFalhasMistas = 'mistura' . uniqid() . '@exemplo.com';
    for ($i = 0; $i < 5; $i++) {
        $formaUsada = $i % 2 === 0 ? ('  ' . strtoupper($emailFalhasMistas) . '  ') : $emailFalhasMistas;
        Auth::registrarFalha($pdo, $formaUsada);
    }
    Teste::verdade(Auth::bloqueadoAte($pdo, $emailFalhasMistas) !== null, 'registrarFalha normaliza o e-mail: chamadas com maiusculas e espacos incrementam a mesma linha ate bloquear');

    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'comeca sem bloqueio');

    // O limite e 5. Quatro falhas ainda deixam entrar; a quinta bloqueia.
    // Sem essas duas asserticoes juntas, um erro de uma unidade a mais ou a menos passaria batido.
    for ($i = 0; $i < 4; $i++) {
        Auth::registrarFalha($pdo, $email);
    }
    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'quatro falhas ainda nao bloqueiam');

    // Le o relogio do proprio MySQL, e nao o do PHP: o container/servico do
    // MySQL pode rodar num fuso diferente do PHP (foi o caso aqui, 5 horas
    // de diferenca), e bloqueado_ate vem de NOW() do banco. Comparar contra
    // time() do PHP mistura dois relogios e da um "quase 30 segundos" falso.
    $antesQuinta = $pdo->query('SELECT NOW() AS agora')->fetch()['agora'];
    Auth::registrarFalha($pdo, $email);
    $bloqueado5 = Auth::bloqueadoAte($pdo, $email);
    Teste::verdade($bloqueado5 !== null, 'a quinta falha bloqueia');

    // Curva de escalonamento: a quinta falha bloqueia por perto de 30
    // segundos (tolerancia para o tempo de execucao do teste), e a sexta
    // empurra o bloqueio bem mais para frente que a quinta, sem depender de
    // sleep().
    $segundosNaQuinta = strtotime($bloqueado5) - strtotime($antesQuinta);
    Teste::verdade($segundosNaQuinta >= 20 && $segundosNaQuinta <= 40, 'a quinta falha bloqueia por perto de 30 segundos');

    Auth::registrarFalha($pdo, $email);
    $bloqueado6 = Auth::bloqueadoAte($pdo, $email);
    Teste::verdade(strtotime($bloqueado6) > strtotime($bloqueado5) + 15, 'a sexta falha empurra o bloqueio bem mais para frente que a quinta');

    // Bloqueio expirado e uma linha apagada sao coisas diferentes: aqui o
    // bloqueio ja passou (voltamos bloqueado_ate para o passado direto no
    // banco), mas a linha continua na tabela, so nao bloqueia mais.
    $pdo->prepare('UPDATE tentativas_login SET bloqueado_ate = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE email = ?')
        ->execute([$email]);
    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'bloqueio que ja passou nao bloqueia mais');
    $contaLinha = $pdo->prepare('SELECT COUNT(*) AS c FROM tentativas_login WHERE email = ?');
    $contaLinha->execute([$email]);
    Teste::igual(1, (int) $contaLinha->fetch()['c'], 'mas a linha continua existindo, so o bloqueio expirou (nao foi apagada)');

    Auth::limparFalhas($pdo, $email);
    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'login certo limpa o bloqueio');
} finally {
    $pdo->rollBack();
}

// A linha da corrida de cadastro foi gravada por uma conexao separada, fora
// da transacao principal, entao o rollBack acima nao a desfaz. So limpamos
// aqui, depois que a transacao principal ja terminou e liberou qualquer
// travamento que o INSERT que falhou tenha deixado sobre aquele indice.
if ($pdo2 !== null && $emailCorrida !== null) {
    $pdo2->prepare('DELETE FROM users WHERE email = ?')->execute([$emailCorrida]);
}

exit(Teste::resumo());
