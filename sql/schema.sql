CREATE DATABASE IF NOT EXISTS super8
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE super8;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  google_id     VARCHAR(64)  NULL UNIQUE,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NULL UNIQUE,
  senha_hash    VARCHAR(255) NULL,
  foto_url      VARCHAR(255) NULL,
  e_organizador TINYINT(1)   NOT NULL DEFAULT 0,
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em     DATETIME     NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tentativas_login (
  email         VARCHAR(160) PRIMARY KEY,
  tentativas    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campeonatos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organizador_id INT UNSIGNED NOT NULL,
  nome           VARCHAR(160) NOT NULL,
  data_evento    DATE NOT NULL,
  local          VARCHAR(160) NULL,
  custo          DECIMAL(10,2) NULL,
  descricao      TEXT NULL,
  status         ENUM('rascunho','sorteado','em_andamento','encerrado') NOT NULL DEFAULT 'rascunho',
  seed_sorteio   INT UNSIGNED NULL,
  criado_em      DATETIME NOT NULL,
  CONSTRAINT fk_camp_organizador FOREIGN KEY (organizador_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inscricoes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campeonato_id   INT UNSIGNED NOT NULL,
  jogador_id      INT UNSIGNED NULL,
  nome_exibicao   VARCHAR(120) NOT NULL,
  posicao_sorteio TINYINT UNSIGNED NULL,
  CONSTRAINT fk_insc_camp FOREIGN KEY (campeonato_id) REFERENCES campeonatos(id),
  CONSTRAINT fk_insc_jogador FOREIGN KEY (jogador_id) REFERENCES users(id),
  UNIQUE KEY uk_camp_posicao (campeonato_id, posicao_sorteio),
  UNIQUE KEY uk_camp_nome (campeonato_id, nome_exibicao),
  UNIQUE KEY uk_camp_jogador (campeonato_id, jogador_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rodadas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campeonato_id INT UNSIGNED NOT NULL,
  numero        TINYINT UNSIGNED NOT NULL,
  CONSTRAINT fk_rod_camp FOREIGN KEY (campeonato_id) REFERENCES campeonatos(id),
  UNIQUE KEY uk_camp_numero (campeonato_id, numero)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS partidas (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rodada_id    INT UNSIGNED NOT NULL,
  quadra       TINYINT UNSIGNED NOT NULL,
  dupla_a_j1   INT UNSIGNED NOT NULL,
  dupla_a_j2   INT UNSIGNED NOT NULL,
  dupla_b_j1   INT UNSIGNED NOT NULL,
  dupla_b_j2   INT UNSIGNED NOT NULL,
  games_a      TINYINT UNSIGNED NULL,
  games_b      TINYINT UNSIGNED NULL,
  encerrada    TINYINT(1) NOT NULL DEFAULT 0,
  gravado_por  INT UNSIGNED NULL,
  gravado_em   DATETIME NULL,
  CONSTRAINT fk_part_rodada FOREIGN KEY (rodada_id) REFERENCES rodadas(id),
  CONSTRAINT fk_part_a1 FOREIGN KEY (dupla_a_j1) REFERENCES inscricoes(id),
  CONSTRAINT fk_part_a2 FOREIGN KEY (dupla_a_j2) REFERENCES inscricoes(id),
  CONSTRAINT fk_part_b1 FOREIGN KEY (dupla_b_j1) REFERENCES inscricoes(id),
  CONSTRAINT fk_part_b2 FOREIGN KEY (dupla_b_j2) REFERENCES inscricoes(id),
  UNIQUE KEY uk_rodada_quadra (rodada_id, quadra)
) ENGINE=InnoDB;
