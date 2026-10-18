-- Migração 001: categoria e número da Pokédex nos Pokémon
-- Pode ser executada mais de uma vez (MariaDB / XAMPP).
USE pokedex;

ALTER TABLE pokemons
    ADD COLUMN IF NOT EXISTS categoria VARCHAR(30) NOT NULL DEFAULT 'outros' AFTER tipo,
    ADD COLUMN IF NOT EXISTS numero SMALLINT UNSIGNED NULL AFTER categoria,
    ADD INDEX IF NOT EXISTS idx_pokemons_categoria (categoria);