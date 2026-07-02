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

Para testar localmente como producao sem dominio publico, use
`CADDY_SITE_ADDRESS=:80`, `HTTP_PORT=8081`, `HTTPS_PORT=8443`,
`APP_URL=http://localhost:8081`, `SESSION_SECURE_COOKIE=false` e mantenha os
dominios de Sanctum/CORS/Reverb apontando para `localhost`.

```sh
docker compose --env-file .env.production -f docker-compose.prod.yml up --build
```

Em producao, o Caddy publica `80` e `443`, gera HTTPS automaticamente e
encaminha o trafego para o container `web`. A API fica em
`https://seu-dominio/api/v1` e o Reverb passa pelo mesmo dominio em
`wss://seu-dominio/app`.

Na Oracle, use `CADDY_SITE_ADDRESS=seu-dominio`, `HTTP_PORT=80`,
`HTTPS_PORT=443`, `APP_URL=https://seu-dominio`,
`SESSION_SECURE_COOKIE=true`, `VITE_REVERB_SCHEME=https`,
`VITE_REVERB_PORT=443`, alem de `SANCTUM_STATEFUL_DOMAINS`,
`CORS_ALLOWED_ORIGINS`, `REVERB_*` e senhas fortes. O security list/firewall da
VM deve liberar `80` e `443` para o Caddy.

Para deploy manual com migration executada uma unica vez:

```sh
sh scripts/deploy.sh
```

Para backup e restore local do PostgreSQL:

```sh
sh scripts/backup.sh
sh scripts/restore.sh backups/copazone-YYYYMMDD-HHMMSS.dump
```
