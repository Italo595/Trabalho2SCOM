# Arquitetura do sistema

## Visão geral

Aplicação **cliente-servidor** com **arquitetura em camadas** no backend (variação de MVC em que a "visão" é uma página estática que consome uma API JSON).

- **Interface:** `public/pokedex.html`, `public/css/` e `public/js/app.js` (HTML, CSS e JavaScript puros).
- **Backend:** PHP 8, sem framework, com autoload próprio e PDO.
- **Banco de dados:** MySQL/MariaDB (XAMPP).
- **Servidor web:** Apache, com `mod_rewrite` enviando as rotas para `public/index.php` (front controller).

## Diagrama

```
 Navegador (pokedex.html + app.js)
        │  fetch() JSON  +  cookie de sessão  +  X-CSRF-Token
        ▼
 Apache ──► public/index.php  (front controller)
                │  1. carrega config e logger
                │  2. inicia sessão segura
                │  3. valida token CSRF (POST/PUT/PATCH/DELETE)
                │  4. roteia  ──► Router
                ▼
        Controllers      traduzem HTTP ⇄ regras (lêem o JSON, devolvem a resposta)
                │         └─ chamam Middleware (AuthMiddleware: login e perfil)
                ▼
         Services        regras de negócio e validação (sem HTTP, testáveis)
                │
                ▼
       Repositories      único lugar com SQL (PDO, prepared statements)
                │
                ▼
        MySQL / MariaDB
```

## Responsabilidade de cada camada

| Camada | Pasta | O que faz | O que **não** faz |
|---|---|---|---|
| Interface | `public/` | Desenha a página e chama a API | Não acessa o banco |
| Controllers | `src/Controllers/` | Lê a requisição, exige login/perfil, devolve JSON | Não tem regra de negócio nem SQL |
| Services | `src/Services/` | Valida, sanitiza e aplica regras (limite de 6, duplicados, dono do time) | Não conhece HTTP nem SQL |
| Repositories | `src/Repositories/` | Consultas SQL parametrizadas | Não tem regra de negócio |
| Middleware | `src/Middleware/` | Autenticação, perfil e CSRF | |
| Core | `src/Core/` | Roteador, sessão, validação, resposta JSON, logger, conexão, exceção | |

## Estrutura de pastas

```
Trabalho2SCOM/
├── public/              única pasta feita para ser acessada pelo navegador
│   ├── index.php        front controller (rotas e montagem das dependências)
│   ├── pokedex.html     interface
│   ├── css/  js/
│   └── .htaccess        reescrita de URLs para o front controller
├── src/
│   ├── Controllers/  Services/  Repositories/  Middleware/  Core/
├── config/config.php    leitura do .env
├── database/            schema.sql, seed_pokemons.sql e migrações
├── logs/                app.log (ignorado pelo Git)
├── tests/               testes automatizados (PHPUnit)
├── docs/                API.md e este documento
├── .env.example         modelo de configuração (o .env real não vai ao Git)
└── README.md
```

As pastas `src`, `config`, `database`, `logs`, `tests` e `docs` têm um `.htaccess` com `Require all denied`, e o `.htaccess` da raiz bloqueia `.env`, `.sql`, `.log`, `.md`, `composer.*`, `phpunit.xml` e a pasta `vendor`.

## Fluxo de uma requisição (exemplo: adicionar Pokémon ao time)

1. `app.js` pega o token em `GET /api/csrf` e envia `POST /api/times/1/pokemons` com o cabeçalho `X-CSRF-Token`.
2. `public/index.php` inicia a sessão e confere o token (403 se faltar).
3. O `Router` encontra a rota e chama `TimeController::addPokemon`.
4. O controller chama `AuthMiddleware::require()` (401 sem login) e passa os dados ao `TimeService`.
5. O `TimeService` confere se o time é do usuário (403/404), valida o `pokemon_id` (422), verifica se o Pokémon existe (404), se já está no time (409) e se o limite de 6 foi atingido (422).
6. O `TimeRepository` executa os `INSERT`/`SELECT` com prepared statements.
7. O controller devolve JSON padronizado; qualquer exceção é tratada no ponto central de `public/index.php`.

## Padrões adotados

- **Injeção de dependências manual:** `public/index.php` cria os repositórios, serviços e controllers e os conecta.
- **Interface de repositório** (`UsuarioRepositoryInterface`) e classes não finais nos demais repositórios, para permitir dublês nos testes.
- **Tratamento centralizado de erros:** serviços lançam `AppException` (com status HTTP e erros por campo); o tratador global converte em JSON. Erros inesperados viram `500`, são registrados no log e só mostram o detalhe técnico com `APP_ENV=development`.
- **Configuração fora do código:** credenciais ficam no `.env`, ignorado pelo Git. O repositório traz só o `.env.example`.

## Modelo de dados

```
usuarios (id PK, nome, email UNIQUE, senha_hash, perfil ENUM('admin','usuario'), criado_em)
   │ 1
   ├──< N pokemons (id PK, nome UNIQUE, tipo, categoria, numero, descricao,
   │                 imagem_url, criado_por FK→usuarios ON DELETE SET NULL, criado_em)
   │
   └──< N times (id PK, nome, usuario_id FK→usuarios ON DELETE CASCADE,
                 UNIQUE(usuario_id, nome), criado_em)
              │ N
              └── N time_pokemon (time_id FK, pokemon_id FK, PK(time_id, pokemon_id),
                                   ambos ON DELETE CASCADE, adicionado_em)
```

Índices adicionais: `pokemons(tipo)`, `pokemons(categoria)`, `times(usuario_id)` e `time_pokemon(pokemon_id)`. O limite de 6 Pokémon por time é uma regra de negócio, aplicada no `TimeService`.

## Perfis e matriz de permissões

| Ação | Visitante | Usuário | Admin |
|---|:---:|:---:|:---:|
| Ver catálogo de Pokémon | ✅ | ✅ | ✅ |
| Cadastrar-se e entrar | ✅ | ✅ | ✅ |
| Criar, ver, renomear e excluir **os próprios** times | ❌ | ✅ | ✅ |
| Acessar time de outro usuário | ❌ | ❌ | ❌ |
| Cadastrar, editar e excluir Pokémon | ❌ | ❌ | ✅ |
| Listar usuários | ❌ | ❌ | ✅ |

O controle é aplicado no backend (`AuthMiddleware::require()` e `AuthMiddleware::role('admin')`). Esconder botões na interface é só conveniência visual.

## Autenticação e segurança

- **Sessão** por cookie `HttpOnly`, `SameSite=Lax` (e `Secure` quando houver HTTPS), com modo estrito e `session_regenerate_id` no login.
- **Senhas** com `password_hash` (bcrypt, custo 12) e `password_verify`. O login responde igual para e-mail inexistente e senha errada, e gasta o mesmo tempo de processamento.
- **SQL Injection:** apenas prepared statements reais (`ATTR_EMULATE_PREPARES = false`).
- **XSS:** a entrada é sanitizada (tags removidas) e a interface insere os textos com `textContent`, nunca com `innerHTML`. URLs de imagem só aceitam `http` e `https`.
- **CSRF:** token por sessão, comparado com `hash_equals`, exigido em todo método que altera estado.
- **Cabeçalhos:** `X-Content-Type-Options: nosniff` e `X-Frame-Options: DENY`.

## Observabilidade

`logs/app.log` registra, com data e hora e IP: login bem-sucedido, falha de autenticação, acesso sem autenticação, acesso negado por perfil, falha de CSRF, criação, alteração e exclusão de entidades e erros internos.