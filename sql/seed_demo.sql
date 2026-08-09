-- Dados de demonstracao. Nao usar em producao.
USE super8;

INSERT INTO users (nome, email, senha_hash, e_organizador, ativo, criado_em)
VALUES ('Organizador Demo', 'demo@exemplo.com',
        '$argon2id$v=19$m=65536,t=4,p=1$dzdEclE5cVZBdC9QNGFRbg$9WM/3RwKA+BudvpYvDDB6GFcEYRuNZheNdinNqd1mG8',
        1, 1, NOW());

-- Senha de demonstracao: senhademo123
-- O hash acima foi gerado nesta maquina com:
-- C:\xampp\php\php.exe -r "echo password_hash('senhademo123', PASSWORD_ARGON2ID);"
