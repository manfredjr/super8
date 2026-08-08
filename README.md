# Super 8 Padel

Sistema de torneios de padel no formato Super 8. Oito jogadores, 7 rodadas, todos jogam com e contra todos, pontuacao individual por games vencidos.

## Rodar local

1. Ligar Apache e MySQL no painel do XAMPP.
2. Criar o banco: `C:\xampp\mysql\bin\mysql.exe -u root < sql/schema.sql`
3. Copiar `config/config.exemplo.php` para `config/config.php` e ajustar as credenciais.
4. Abrir `http://localhost/super8/public/`.

## Rodar os testes

`C:\xampp\php\php.exe testes/executar.php`
