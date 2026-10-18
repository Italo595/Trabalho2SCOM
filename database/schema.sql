-- ============================================================
-- Trabalho Individual II - SCOM
-- Script de criacao do banco (MySQL/MariaDB - XAMPP)
-- Execute como root no phpMyAdmin (aba SQL) ou aba Importar.
-- ============================================================

CREATE DATABASE IF NOT EXISTS pokedex
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pokedex;

-- Para poder reexecutar o script do zero
DROP TABLE IF EXISTS time_pokemon;
DROP TABLE IF EXISTS times;
DROP TABLE IF EXISTS pokemons;
DROP TABLE IF EXISTS usuarios;

-- ------------------------------------------------------------
-- Usuarios (autenticacao + perfis)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(80)  NOT NULL,
    email       VARCHAR(120) NOT NULL,
    senha_hash  VARCHAR(255) NOT NULL,
    perfil      ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Pokemons (catalogo - gerenciado pelo admin)
-- ------------------------------------------------------------
CREATE TABLE pokemons (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(60)  NOT NULL,
    tipo        VARCHAR(30)  NOT NULL,
    categoria   VARCHAR(30)  NOT NULL DEFAULT 'outros',
    numero      SMALLINT UNSIGNED NULL,
    descricao   TEXT         NULL,
    imagem_url  VARCHAR(255) NULL,
    criado_por  INT UNSIGNED NULL,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pokemons_nome (nome),
    KEY idx_pokemons_tipo (tipo),
    KEY idx_pokemons_categoria (categoria),
    CONSTRAINT fk_pokemons_usuario
        FOREIGN KEY (criado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Times (entidade principal do usuario)
-- ------------------------------------------------------------
CREATE TABLE times (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(60)  NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,
    criado_em   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_times_usuario_nome (usuario_id, nome),
    KEY idx_times_usuario (usuario_id),
    CONSTRAINT fk_times_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Relacao N:N entre times e pokemons (maximo de 6 por time
-- e validado na regra de negocio)
-- ------------------------------------------------------------
CREATE TABLE time_pokemon (
    time_id     INT UNSIGNED NOT NULL,
    pokemon_id  INT UNSIGNED NOT NULL,
    adicionado_em TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (time_id, pokemon_id),
    KEY idx_tp_pokemon (pokemon_id),
    CONSTRAINT fk_tp_time
        FOREIGN KEY (time_id) REFERENCES times (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_tp_pokemon
        FOREIGN KEY (pokemon_id) REFERENCES pokemons (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- O catálogo é carregado depois, com: database/seed_pokemons.sql