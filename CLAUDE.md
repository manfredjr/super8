# CLAUDE.md - Projeto Super 8 (Padel)

Arquivo de contexto e regras deste projeto. Ler antes de qualquer tarefa nesta pasta.

## O que e o projeto

Plataforma web para criar, gerenciar e ranquear torneios de padel no formato Super 8. Oito jogadores, 7 rodadas, todos jogam com e contra todos, pontuacao individual por games vencidos.

O documento de requisitos completo esta em `projeto-super8-padel.md`.

Etapa atual: primeira versao sem login com Google. A autenticacao via Google (Laravel Socialite) fica para a segunda etapa.

## Regras obrigatorias

### Textos

Todo texto produzido no projeto passa pela skill `/anthropic-skills:humanizar-ptbr`. Vale para documentacao, comentarios longos, textos de interface, e-mails e qualquer conteudo lido por pessoa. Sem travessao longo, sem aspas curvas, sem emoji em titulo, sem as muletas de IA listadas na skill.

### Exclusao de arquivos

Nao apagar arquivo nenhum. Quando um arquivo precisar sair do lugar, mover para `_LIXEIRA` na raiz do projeto. Isso inclui arquivo substituido, versao antiga, teste descartado e qualquer coisa que pareca lixo.

### Controle de versao

Todo software desenvolvido aqui tem repositorio no GitHub associado. Cada alteracao vira um commit com mensagem que explica o que mudou. Commit e push so acontecem quando solicitado.

### Publicacao

Arquivo pronto para producao vai para `_PUBLICAR\enviar` na raiz do projeto. Essa pasta e o pacote de subida para o servidor. Nada de subir direto da pasta de desenvolvimento.

### Ambiente de teste

Aplicacao PHP + MySQL roda no XAMPP local. A pasta de teste deste projeto e:

```
C:\xampp\htdocs\super8
```

Cada projeto ganha uma pasta com o nome dele dentro de `htdocs`.

### Antes de codar

Software se projeta antes de executar. Isso significa: definir requisitos, modelar dados, desenhar as telas e o fluxo, e so entao escrever codigo. A cada projeto novo, avaliar duas frentes:

1. Seguranca - autenticacao, controle de acesso, injecao de SQL, XSS, CSRF, upload de arquivo, exposicao de dados no ambiente publico.
2. Leis aplicaveis - LGPD para dados pessoais (nome, e-mail, foto, telefone), base legal do tratamento, consentimento, politica de privacidade, prazo de retencao e direito de exclusao do titular. Verificar tambem uso de imagem quando houver fotos de jogadores.

O resultado dessa avaliacao entra em documento no projeto, nao so na conversa.

## Estrutura de pastas

```
C:\COWORK\CODE\SUPER8\
  CLAUDE.md                  este arquivo
  projeto-super8-padel.md    requisitos do sistema
  _LIXEIRA\                  destino de arquivos removidos
  _PUBLICAR\enviar\          pacote pronto para producao
```

## Stack

PHP e MySQL, com Laravel previsto no documento de requisitos. Teste local no XAMPP, producao em VPS proprio com Let's Encrypt.
