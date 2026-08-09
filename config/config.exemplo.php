<?php

// Copiar este arquivo para config.php e ajustar. config.php fica fora do git.
const DB_HOST  = '127.0.0.1';
const DB_PORTA = 3306;
const DB_NOME  = 'super8';
const DB_USER  = 'root';
const DB_SENHA = '';

// Em producao, com HTTPS ativo, mudar para true.
const COOKIE_SEGURO = false;

// Versao do termo de uso em vigor. Quando o texto do termo mudar, esta constante
// muda junto, e quem aceitou a versao anterior passa pela tela de aceite de novo.
// A comparacao e por igualdade e nunca por ordem: como string, '1.10' e menor que
// '1.9', e um numero de versao maior passaria por desatualizado.
const TERMO_VERSAO = '1.0';

// Chave de cache dos arquivos estaticos (css/js). Muda a cada alteracao de
// public/css/estilo.css para o navegador buscar a versao nova em vez de
// segurar a folha antiga em cache.
const VERSAO_ESTATICO = '4';
