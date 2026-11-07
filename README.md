# Pokédex & Team Builder: Trabalho Individual II (SCOM)

Evolução do Trabalho 1 (catálogo Pokémon e montador de times em HTML/CSS) com backend em **PHP + MySQL**, autenticação por sessão, controle de acesso por perfil, validação, logs e testes automatizados.

## Requisitos

- [XAMPP](https://www.apachefriends.org) com **PHP 8.2 ou superior**, Apache e MySQL/MariaDB.
- Para os testes: nada além do que já vem no projeto (a pasta `vendor/` está incluída). O [Composer](https://getcomposer.org) só é necessário se a pasta `vendor/` for removida.

## Como executar

1. **Copie o projeto** para `C:\xampp\htdocs\Trabalho2SCOM` (a pasta deve conter diretamente `public`, `src`, `config`...).
2. No XAMPP Control Panel, clique em **Start** no **Apache** e no **MySQL**.
3. **Crie o banco.** Em `http://localhost/phpmyadmin`, aba **Importar**, importe nesta ordem:
   1. `database/schema.sql`, que cria o banco `pokedex` e as tabelas (apaga tabelas existentes);
   2. `database/seed_pokemons.sql`, que carrega os 50 Pokémon do catálogo.
4. **Crie o usuário do banco.** No phpMyAdmin, aba **SQL**, execute:
   ```sql
   CREATE USER IF NOT EXISTS 'pokedex_user'@'localhost' IDENTIFIED BY 'troque_esta_senha';
   GRANT ALL ON pokedex.* TO 'pokedex_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
5. **Crie o arquivo de configuração.** O `.env` não acompanha o projeto (ele guarda a senha do banco e fica fora do Git). Copie o modelo `.env.example` para `.env`, na raiz do projeto:
   ```
   copy .env.example .env
   ```
   O modelo já usa a mesma senha do passo 4:
   ```
   APP_ENV=development
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=pokedex
   DB_USER=pokedex_user
   DB_PASS=troque_esta_senha
   ```
   Se trocar a senha no passo 4, troque também em `DB_PASS`. Fora de um teste local, a senha de exemplo deve ser alterada.
6. **Abra o sistema** em `http://localhost/Trabalho2SCOM/public/`. Para conferir a API: `http://localhost/Trabalho2SCOM/public/api/health` (deve responder com o banco "conectado").

Se aparecer a mensagem "Arquivo .env nao encontrado", falta o passo 5. Se aparecer `Access denied`, o usuário do passo 4 não foi criado ou a senha do `.env` é diferente da usada nele.

### Criar um administrador

O cadastro público sempre cria o perfil `usuario`. Para criar um admin, cadastre-se normalmente pela página e, no phpMyAdmin (aba **SQL**), execute:

```sql
UPDATE pokedex.usuarios SET perfil = 'admin' WHERE email = 'seu@email.com';
```

Saia e entre de novo para o perfil valer.

### Bancos criados antes da etapa 4

Quem já tem o banco sem as colunas `categoria` e `numero` deve importar `database/migracao_001_categoria_numero.sql` e depois `database/seed_pokemons.sql`, sem recriar o banco.

## Testes automatizados

Na raiz do projeto, no terminal (PowerShell ou Prompt de Comando):

```
php vendor/bin/phpunit --testdox
```

Se o comando `php` não for reconhecido, use o PHP do XAMPP:

```
C:\xampp\php\php.exe vendor\bin\phpunit --testdox
```

Se a pasta `vendor/` não existir, rode antes `composer install` (exige a extensão `zip` ativada no `php.ini` do XAMPP).

Os testes cobrem validação, autenticação, catálogo e regras dos times (casos válidos e inválidos). Eles usam repositórios simulados e **não precisam** de banco nem de Apache.

## Testes de segurança manuais

A página `http://localhost/Trabalho2SCOM/public/ferramenta-testes.html` permite cadastrar, entrar, sair e enviar requisições à API (inclusive sem token CSRF), usada nos testes descritos no relatório.

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

- Com `APP_ENV=development`, erros internos (500) mostram detalhes técnicos na resposta. Em produção, use `APP_ENV=production`.
- Dados e imagens de Pokémon pertencem à Nintendo e The Pokémon Company.
- O uso de IA generativa no desenvolvimento está declarado no relatório técnico.