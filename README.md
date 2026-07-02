# CopaZone

## Docker de desenvolvimento

Use o compose padrao para desenvolvimento com hot reload:

```sh
docker compose up --build
```

## Docker parecido com producao

Crie o arquivo de variaveis a partir do exemplo e preencha as chaves reais:

```sh
cp .env.production.example .env.production
```

Gere uma `APP_KEY` no backend e copie para `.env.production`:

```sh
docker compose run --rm backend php artisan key:generate --show
```

Para testar localmente como producao:

```sh
docker compose --env-file .env.production -f docker-compose.prod.yml up --build
```

Por padrao, o frontend fica em `http://127.0.0.1:8081`, a API em
`http://127.0.0.1:8081/api/v1` e o Reverb passa pelo mesmo servidor web em
`ws://127.0.0.1:8081/app`.

Na Oracle, ajuste `.env.production` com o dominio publico, portas, `APP_URL`,
`VITE_API_BASE_URL`, `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`,
`REVERB_*` e senhas fortes antes de subir. Em HTTPS real, defina
`SESSION_SECURE_COOKIE=true`, `VITE_REVERB_SCHEME=https` e
`VITE_REVERB_PORT=443`.

Para deploy manual com migration executada uma unica vez:

```sh
sh scripts/deploy.sh
```

Para backup e restore local do PostgreSQL:

```sh
sh scripts/backup.sh
sh scripts/restore.sh backups/copazone-YYYYMMDD-HHMMSS.sql.gz
```
