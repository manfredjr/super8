<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Auth.php';

echo "Auth\n";

$pdo = db();
$pdo->beginTransaction();

$email = 'teste' . random_int(1000, 9999) . '@exemplo.com';

$id = Auth::cadastrar($pdo, 'Organizador Teste', $email, 'senhaforte123');
Teste::verdade($id > 0, 'cadastrar devolve o id do usuario');

$busca = $pdo->prepare('SELECT senha_hash, e_organizador FROM users WHERE id = ?');
$busca->execute([$id]);
$linha = $busca->fetch();
Teste::verdade($linha['senha_hash'] !== 'senhaforte123', 'a senha nao fica em texto no banco');
Teste::verdade(password_verify('senhaforte123', $linha['senha_hash']), 'o hash confere com a senha');
Teste::igual(1, (int) $linha['e_organizador'], 'quem se cadastra vira organizador');

$usuario = Auth::autenticar($pdo, $email, 'senhaforte123');
Teste::verdade($usuario !== null, 'autentica com a senha certa');
Teste::igual($id, (int) $usuario['id'], 'devolve o usuario correto');

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

Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'comeca sem bloqueio');

// O limite e 5. Quatro falhas ainda deixam entrar; a quinta bloqueia.
// Sem essas duas asserticoes juntas, um erro de uma unidade a mais ou a menos passaria batido.
for ($i = 0; $i < 4; $i++) {
    Auth::registrarFalha($pdo, $email);
}
Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'quatro falhas ainda nao bloqueiam');

Auth::registrarFalha($pdo, $email);
Teste::verdade(Auth::bloqueadoAte($pdo, $email) !== null, 'a quinta falha bloqueia');

Auth::limparFalhas($pdo, $email);
Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'login certo limpa o bloqueio');

$pdo->rollBack();

exit(Teste::resumo());
