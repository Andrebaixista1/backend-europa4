# Backend Europa 4

API Laravel para autenticacao e consultas do sistema Europa 4.

## Resumo das APIs

### `GET /api/teste`
Verifica se a API esta respondendo.

Resposta esperada:

```json
{
  "message": "Api teste"
}
```

### `POST /api/login`
Valida login e senha no SQL Server e devolve os dados publicos do usuario com perfil e permissoes.

Body JSON:

```json
{
  "login": "andrefelipe",
  "password": "899605"
}
```

Retornos principais:

- `200`: login realizado com sucesso
- `401`: login ou senha invalidos
- `403`: conta bloqueada, equipe inativa ou acesso nao autorizado
- `503`: falha ao consultar o banco

### `GET /api/usuarios`
Lista usuarios vindos da tabela `[europa4].[dbo].[users45]`.

Resposta:

```json
{
  "success": true,
  "total": 315,
  "data": []
}
```

### `GET /api/equipes`
Lista equipes vindas da tabela `[europa4].[dbo].[equipes45]`.

Resposta:

```json
{
  "success": true,
  "total": 10,
  "data": []
}
```

### `GET /api/permissoes`
Lista permissoes vindas da tabela `[europa4].[dbo].[permissions45]`.

Resposta:

```json
{
  "success": true,
  "total": 16,
  "data": []
}
```

## Como rodar localmente

Este projeto precisa de PHP com extensao `sqlsrv` e `pdo_sqlsrv`.

Comando usado no ambiente local configurado:

```powershell
C:\Tools\php84-vs17\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

## Testes rapidos

### Testar login

```bash
curl --location "http://127.0.0.1:8000/api/login" \
--header "Content-Type: application/json" \
--data "{\"login\":\"andrefelipe\",\"password\":\"899605\"}"
```

### Testar usuarios

```bash
curl --location "http://127.0.0.1:8000/api/usuarios" \
--header "Accept: application/json"
```

### Testar equipes

```bash
curl --location "http://127.0.0.1:8000/api/equipes" \
--header "Accept: application/json"
```

### Testar permissoes

```bash
curl --location "http://127.0.0.1:8000/api/permissoes" \
--header "Accept: application/json"
```

## Deploy no VPS

O deploy foi preparado para subir via Docker Compose com a API exposta em uma porta publica.

Arquivos usados:

- `Dockerfile`
- `docker-compose.yml`
- `.dockerignore`

Depois do clone no servidor:

1. criar o `.env`
2. subir com `docker compose up -d --build`
3. liberar a porta escolhida no `ufw`

## Stack

- Laravel 12
- PHP 8.4
- SQL Server
- Docker Compose para deploy
