# Super 8 Padel

Sistema de torneios de padel no formato Super 8. Oito jogadores, 7 rodadas, todos jogam com e contra todos, pontuacao individual por games vencidos.

Etapa atual: motor do torneio e ranking acumulado, com login por e-mail e senha. O login com Google fica para a etapa 2, e a modelagem ja nasceu preparada para ele.

## Rodar local

1. Ligar Apache e MySQL no painel do XAMPP.
2. Criar o banco: `C:\xampp\mysql\bin\mysql.exe -u root < sql/schema.sql`
3. Copiar `config/config.exemplo.php` para `config/config.php` e ajustar as credenciais.
4. Abrir `http://localhost/super8/public/`.

O arquivo `config/config.php` guarda as credenciais e fica fora do controle de versao de proposito. Somente o exemplo e versionado.

## Rodar os testes

`C:\xampp\php\php.exe testes/executar.php`

Sao 13 arquivos de teste que rodam por linha de comando, sem navegador e sem servidor web. Alguns precisam do MySQL no ar, porque exercitam transacao e concorrencia de verdade, com dois processos disputando a mesma trava.

O runner julga cada arquivo pelo codigo de saida. Vale saber de uma armadilha do PHP que aparece varias vezes neste projeto: `exit('mensagem')` devolve codigo de saida zero, entao um teste que morre no meio passaria por aprovado. Os testes que podem alcancar um `exit` usam `register_shutdown_function` para forcar codigo diferente de zero.

## Como o codigo esta organizado

- `src/` guarda as regras. Nenhuma linha de HTML, nenhuma chamada de sessao ou de cabecalho. Cada arquivo recebe a conexao e os valores por parametro, o que e o que torna os testes de linha de comando possiveis.
- `config/` guarda conexao, sessao, token CSRF, escape de saida e controle de acesso. E aqui que mora o codigo que legitimamente toca sessao e cabecalho.
- `views/` guarda as telas, `public/` os pontos de entrada.
- `sql/schema.sql` cria as seis tabelas.
- `testes/` roda tudo. Arquivos comecando com `_ajuda_` sao auxiliares chamados por subprocesso, e nao testes; o nome e proposital, porque o runner so recolhe `teste_*.php`.

## Documentacao

- `CLAUDE.md` traz as regras de trabalho no projeto.
- `docs/especificacao-etapa1.md` traz o desenho: modelagem, decisoes de arquitetura com o motivo de cada uma, analise de seguranca, analise de LGPD e a lista dos limites aceitos nesta etapa.
- `docs/plano-etapa1.md` traz o plano de implementacao tarefa por tarefa.

As pastas `_LIXEIRA` e `_PUBLICAR\enviar`, citadas no `CLAUDE.md`, ficam na pasta do projeto e nao no repositorio.
