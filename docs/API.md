# Documentação da API

API REST em PHP que atende a Pokédex & Team Builder.

- **URL base:** `http://localhost/Trabalho2SCOM/public/api`
- **Formato:** JSON (`Content-Type: application/json`), em UTF-8.
- **Autenticação:** sessão por cookie (`SCOMSESSID`, HttpOnly e SameSite=Lax). O cookie é enviado automaticamente pelo navegador.
- **Proteção CSRF:** toda requisição `POST`, `PUT`, `PATCH` ou `DELETE` precisa do cabeçalho `X-CSRF-Token`, com o valor obtido em `GET /api/csrf`. Sem ele, a resposta é `403`. Isso vale também para login e cadastro.

## Formato das respostas

Sucesso:

```json
{ "success": true, "message": "OK", "data": { } }
```

Erro:

```json
{
  "success": false,
  "message": "Dados inválidos",
  "errors": { "email": "E-mail inválido" }
}
```

- `errors` só aparece quando há erros por campo (status `422`).
- `debug` (detalhe técnico) só aparece em erros `500` quando `APP_ENV=development`.

## Códigos de status usados

| Código | Significado |
|---|---|
| 200 | Operação realizada |
| 201 | Recurso criado |
| 400 | Requisição malformada (JSON inválido ou ID inválido na URL) |
| 401 | Não autenticado |
| 403 | Sem permissão (perfil insuficiente, time de outro usuário ou token CSRF inválido) |
| 404 | Recurso ou rota não encontrada |
| 405 | Método HTTP não permitido para a rota |
| 409 | Conflito (e-mail, nome de Pokémon, nome de time ou Pokémon repetido no time) |
| 422 | Dados inválidos ou regra de negócio violada |
| 500 | Erro interno |

## Resumo dos endpoints

| Método | URL | Acesso |
|---|---|---|
| GET | `/health` | Público |
| GET | `/csrf` | Público |
| POST | `/auth/register` | Público |
| POST | `/auth/login` | Público |
| POST | `/auth/logout` | Autenticado |
| GET | `/auth/me` | Autenticado |
| GET | `/auth/session` | Público |
| GET | `/admin/usuarios` | Admin |
| GET | `/pokemons` | Público |
| GET | `/pokemons/{id}` | Público |
| POST | `/pokemons` | Admin |
| PUT | `/pokemons/{id}` | Admin |
| DELETE | `/pokemons/{id}` | Admin |
| GET | `/times` | Autenticado |
| POST | `/times` | Autenticado |
| GET | `/times/{id}` | Dono do time |
| PUT | `/times/{id}` | Dono do time |
| DELETE | `/times/{id}` | Dono do time |
| POST | `/times/{id}/pokemons` | Dono do time |
| DELETE | `/times/{id}/pokemons/{pokemon_id}` | Dono do time |

---

## Sistema

### GET /health

Verifica se a API e o banco estão no ar.

**Resposta 200**

```json
{ "success": true, "message": "OK", "data": { "status": "online", "banco": "conectado", "pokemons": 50 } }
```

**Status:** 200, 500 (banco indisponível).

### GET /csrf

Devolve o token CSRF da sessão atual. O token deve ser enviado no cabeçalho `X-CSRF-Token` das requisições que alteram dados.

**Resposta 200**

```json
{ "success": true, "message": "OK", "data": { "csrf_token": "9f2c...e1" } }
```

**Status:** 200.

---

## Autenticação

### POST /auth/register

Cadastra um usuário. O perfil criado é sempre `usuario`; um campo `perfil` enviado pelo cliente é ignorado.

**Corpo**

| Campo | Tipo | Regras |
|---|---|---|
| nome | texto | 2 a 80 caracteres (tags HTML são removidas) |
| email | texto | formato de e-mail, até 120 caracteres |
| senha | texto | 8 a 72 caracteres, com letras e números |

**Requisição**

```
POST /api/auth/register
Content-Type: application/json
X-CSRF-Token: <token>

{ "nome": "Ana", "email": "ana@exemplo.com", "senha": "senha1234" }
```

**Resposta 201**

```json
{
  "success": true,
  "message": "Usuário cadastrado com sucesso",
  "data": { "id": 4, "nome": "Ana", "email": "ana@exemplo.com", "perfil": "usuario" }
}
```

**Status:** 201, 400, 403 (CSRF), 409 (e-mail já cadastrado), 422.

### POST /auth/login

Inicia a sessão. A senha é conferida com `password_verify` e o identificador da sessão é regenerado.

**Corpo:** `email` e `senha`.

**Requisição**

```
POST /api/auth/login
Content-Type: application/json
X-CSRF-Token: <token>

{ "email": "ana@exemplo.com", "senha": "senha1234" }
```

**Resposta 200**

```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "data": { "id": 4, "nome": "Ana", "email": "ana@exemplo.com", "perfil": "usuario" }
}
```

**Status:** 200, 400, 401 (e-mail ou senha incorretos), 403 (CSRF), 422.

### POST /auth/logout

Encerra a sessão e apaga o cookie.

**Resposta 200**

```json
{ "success": true, "message": "Logout realizado com sucesso", "data": null }
```

**Status:** 200, 401, 403 (CSRF).

### GET /auth/me

Devolve os dados do usuário logado.

**Resposta 200**

```json
{
  "success": true,
  "message": "OK",
  "data": { "id": 4, "nome": "Ana", "email": "ana@exemplo.com", "perfil": "usuario", "criado_em": "2026-10-18 13:22:35" }
}
```

**Status:** 200, 401.

### GET /auth/session

Informa se há sessão ativa, sem gerar erro nem aviso no log. É usada pelo front-end ao abrir a página.

**Resposta 200 (logado)**

```json
{
  "success": true,
  "message": "OK",
  "data": { "autenticado": true, "usuario": { "id": 4, "nome": "Ana", "email": "ana@exemplo.com", "perfil": "usuario", "criado_em": "2026-10-18 13:22:35" } }
}
```

**Resposta 200 (visitante):** `"data": { "autenticado": false, "usuario": null }`

**Status:** 200.

---

## Administração

### GET /admin/usuarios

Lista os usuários cadastrados (sem o hash da senha).

**Resposta 200**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 3, "nome": "adm", "email": "adm@email.com", "perfil": "admin", "criado_em": "2026-10-18 13:22:35" }
  ]
}
```

**Status:** 200, 401, 403 (perfil diferente de admin).

---

## Pokémon

Campos de um Pokémon na resposta: `id`, `nome`, `tipo`, `categoria`, `numero`, `descricao`, `imagem_url`, `criado_em`.

### GET /pokemons

Lista paginada. Público.

**Parâmetros de consulta (todos opcionais)**

| Parâmetro | Regras | Padrão |
|---|---|---|
| page | inteiro de 1 em diante | 1 |
| limit | inteiro de 1 a 50 | 10 |
| tipo | texto, até 30 caracteres (igualdade exata) | sem filtro |
| categoria | texto, até 30 caracteres (igualdade exata) | sem filtro |
| busca | texto, até 60 caracteres (busca parcial no nome) | sem filtro |

**Requisição**

```
GET /api/pokemons?page=1&limit=2&categoria=iniciais
```

**Resposta 200**

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "items": [
      {
        "id": 7, "nome": "Bulbasaur", "tipo": "Planta/Venenoso", "categoria": "iniciais", "numero": 1,
        "descricao": "Um Pokémon do tipo Planta/Venenoso. Ele tem uma semente nas costas desde o nascimento.",
        "imagem_url": "https://www.pokemon.com/static-assets/content-assets/cms2/img/pokedex/detail/001.png",
        "criado_em": "2026-10-18 14:02:11"
      }
    ],
    "page": 1, "limit": 2, "total": 6, "total_pages": 3
  }
}
```

**Status:** 200, 422 (parâmetro inválido, ex.: `limit=abc`).

### GET /pokemons/{id}

**Requisição:** `GET /api/pokemons/7`

**Resposta 200:** o objeto de um Pokémon em `data`.

**Status:** 200, 400 (ID inválido), 404.

### POST /pokemons

Cria um Pokémon. Somente admin.

**Corpo**

| Campo | Tipo | Regras |
|---|---|---|
| nome | texto | obrigatório, 2 a 60 caracteres, único |
| tipo | texto | obrigatório, 2 a 30 caracteres (ex.: `Fogo` ou `Planta/Venenoso`) |
| categoria | texto | obrigatório, 2 a 30 caracteres (guardado em minúsculas) |
| numero | inteiro | opcional, de 1 a 2000 |
| descricao | texto | opcional, até 500 caracteres |
| imagem_url | texto | opcional, URL `http://` ou `https://`, até 255 caracteres |

**Requisição**

```
POST /api/pokemons
Content-Type: application/json
X-CSRF-Token: <token>

{ "nome": "Pikachu", "tipo": "Elétrico", "categoria": "pikaclones", "numero": 25,
  "descricao": "Armazena eletricidade nas bochechas." }
```

**Resposta 201:** o Pokémon criado em `data`, com `message` "Pokémon criado com sucesso".

**Status:** 201, 400, 401, 403 (perfil ou CSRF), 409 (nome já existe), 422.

### PUT /pokemons/{id}

Substitui os dados de um Pokémon (mesmos campos e regras do `POST`). Somente admin.

**Resposta 200:** o Pokémon atualizado em `data`.

**Status:** 200, 400, 401, 403, 404, 409, 422.

### DELETE /pokemons/{id}

Exclui um Pokémon. Ele também sai de todos os times que o contêm. Somente admin.

**Resposta 200**

```json
{ "success": true, "message": "Pokémon excluído com sucesso", "data": null }
```

**Status:** 200, 400, 401, 403, 404.

---

## Times

Cada usuário gerencia apenas os próprios times. Um time tem no máximo **6 Pokémon**, sem repetição. Nomes de time são únicos por usuário.

Formato de um time: `id`, `nome`, `criado_em` e `pokemons`, uma lista de `{ id, nome, tipo, imagem_url }`.

### GET /times

Lista os times do usuário logado, cada um com seus Pokémon.

**Resposta 200**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    { "id": 1, "nome": "Meu Time", "criado_em": "2026-10-18 14:10:00",
      "pokemons": [ { "id": 7, "nome": "Bulbasaur", "tipo": "Planta/Venenoso", "imagem_url": "https://..." } ] }
  ]
}
```

**Status:** 200, 401.

### POST /times

Cria um time.

**Corpo:** `nome` (texto de 2 a 60 caracteres).

**Requisição**

```
POST /api/times
Content-Type: application/json
X-CSRF-Token: <token>

{ "nome": "Meu Time" }
```

**Resposta 201**

```json
{ "success": true, "message": "Time criado com sucesso", "data": { "id": 1, "nome": "Meu Time", "pokemons": [] } }
```

**Status:** 201, 400, 401, 403 (CSRF), 409 (já existe time com esse nome), 422.

### GET /times/{id}

Devolve um time com seus Pokémon.

**Status:** 200, 400, 401, 403 (time de outro usuário), 404.

### PUT /times/{id}

Renomeia o time. **Corpo:** `nome`.

**Status:** 200, 400, 401, 403, 404, 409, 422.

### DELETE /times/{id}

Exclui o time.

**Status:** 200, 400, 401, 403, 404.

### POST /times/{id}/pokemons

Adiciona um Pokémon ao time.

**Corpo:** `pokemon_id` (inteiro positivo).

**Requisição**

```
POST /api/times/1/pokemons
Content-Type: application/json
X-CSRF-Token: <token>

{ "pokemon_id": 7 }
```

**Resposta 201:** o time atualizado em `data`, com `message` "Pokémon adicionado ao time".

**Status:** 201, 400, 401, 403 (time de outro usuário ou CSRF), 404 (time ou Pokémon inexistente), 409 (Pokémon já está no time), 422 (`pokemon_id` inválido ou time já com 6 Pokémon).

### DELETE /times/{id}/pokemons/{pokemon_id}

Remove um Pokémon do time.

**Resposta 200:** o time atualizado em `data`.

**Status:** 200, 400, 401, 403, 404 (o Pokémon não está no time).