# Pokédex & Team Builder: Trabalho Individual II (SCOM)

Evolução do Trabalho 1 (catálogo Pokémon e montador de times em HTML/CSS) com backend em **PHP + MySQL**, autenticação por sessão, controle de acesso por perfil, validação, logs e testes automatizados.

## Requisitos

- [XAMPP](https://www.apachefriends.org) com **PHP 8.1 ou superior**, Apache e MySQL/MariaDB.
- Para os testes: [Composer](https://getcomposer.org) e a extensão `zip` ativada no `php.ini` do XAMPP.

## Como executar

1. **Copie o projeto** para `C:\xampp\htdocs\Trabalho2SCOM` (a pasta deve conter diretamente `public`, `src`, `config`...).
2. No XAMPP Control Panel, clique em **Start** no **Apache** e no **MySQL**.
3. **Crie o banco.** Em `http://localhost/phpmyadmin`, aba **Importar**, importe nesta ordem:
   1. `database/schema.sql`, que cria o banco `pokedex` e as tabelas (apaga tabelas existentes);
   2. `database/seed_pokemons.sql`, que carrega os 50 Pokémon do catálogo.
4. **Crie o usuário do banco.** Na aba **SQL** (troque a senha):
```sql
   CREATE USER 'pokedex_user'@'localhost' IDENTIFIED BY 'SUA_SENHA';
   GRANT ALL ON pokedex.* TO 'pokedex_user'@'localhost';
   FLUSH PRIVILEGES;
```
5. **Configure o ambiente.** Copie `.env.example` para `.env` e preencha `DB_PASS` com a mesma senha:
```
   APP_ENV=development
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=pokedex
   DB_USER=pokedex_user
   DB_PASS=SUA_SENHA
```
6. **Abra o sistema** em `http://localhost/Trabalho2SCOM/public/`. Para conferir a API: `http://localhost/Trabalho2SCOM/public/api/health`.

### Criar um administrador

O cadastro público sempre cria o perfil `usuario`. Para criar um admin, cadastre-se normalmente pela página e, no phpMyAdmin (aba **SQL**), rode:

```sql
UPDATE pokedex.usuarios SET perfil = 'admin' WHERE email = 'seu@email.com';
```

Saia e entre de novo para o perfil valer.

### Bancos criados antes da etapa 4

Quem já tem o banco sem as colunas `categoria` e `numero` deve importar `database/migracao_001_categoria_numero.sql` e depois `database/seed_pokemons.sql`, sem recriar o banco.

## Testes automatizados

```
composer install
composer exec phpunit -- --testdox
```

Os testes cobrem validação, autenticação, catálogo e regras dos times (casos válidos e inválidos). Eles usam repositórios simulados e **não precisam** de banco nem de Apache.

## Documentação

- [API](docs/API.md): todos os endpoints, com exemplos e códigos de status.
- [Arquitetura](docs/ARQUITETURA.md): camadas, modelo de dados, matriz de permissões e decisões de segurança.

## Estrutura

```
public/      interface e front controller (index.php)
src/         Controllers, Services, Repositories, Middleware e Core
config/      leitura do .env
database/    schema.sql, seed_pokemons.sql e migrações
tests/       testes PHPUnit
docs/        documentação
logs/        app.log (gerado em execução)
```

## Segurança

Prepared statements (SQL Injection), sanitização de entrada e escrita na tela com `textContent` (XSS), token CSRF em requisições que alteram dados, senhas com bcrypt, sessão com cookie HttpOnly e SameSite, e controle de acesso por perfil no backend. Detalhes em `docs/ARQUITETURA.md`.

## Observações

- Dados e imagens de Pokémon pertencem à Nintendo e The Pokémon Company.
- O uso de IA generativa no desenvolvimento está declarado no relatório técnico.