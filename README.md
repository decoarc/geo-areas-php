# geo-areas-php

Aplicação PHP com PostgreSQL/PostGIS e Leaflet para desenhar áreas no mapa, salvar no banco e visualizá-las de forma interativa.

## Pré-requisitos

- PHP 8.x com extensões `pdo` e `pdo_pgsql`
- Docker Desktop (recomendado para o banco)
- Composer

## Opção 1: Banco com Docker (recomendado)

### 1. Subir PostgreSQL + PostGIS

Na raiz do projeto:

```bash
docker compose up -d
```

Isso sobe um container com:
- PostgreSQL 16 + PostGIS
- banco `geo_mapa_app`
- tabela `areas` criada automaticamente

### 2. Conferir se está rodando

```bash
docker compose ps
docker compose logs db
```

### 3. Configurar o `.env`

```bash
cp .env.example .env
```

Edite o `.env` e defina uma senha forte em `DB_PASS`. O `docker-compose.yml` lê esse mesmo valor, então não é preciso repeti-lo em outro lugar. O `.env` está no `.gitignore` e nunca deve ser versionado.

```ini
DB_HOST=localhost
DB_PORT=5432
DB_USER=postgres
DB_PASS=troque_por_uma_senha_forte
DB_NAME=geo_mapa_app
```

### 4. Conectar no DBeaver

- Host: `localhost`
- Port: `5432`
- Database: `geo_mapa_app`
- Username: `postgres`
- Password: a mesma definida em `DB_PASS`

### 5. Testar e rodar a aplicação

```bash
composer install
php connectionTest.php
php -S localhost:8000
```

Abra [http://localhost:8000](http://localhost:8000).

### Comandos úteis do Docker

```bash
docker compose up -d      # iniciar o banco
docker compose stop       # parar o banco
docker compose down       # parar e remover o container
docker compose down -v    # parar e apagar os dados do banco
```

## Opção 2: PostgreSQL instalado localmente

1. Copie o arquivo de exemplo e ajuste as credenciais:

```bash
cp .env.example .env
```

2. Instale as dependências PHP:

```bash
composer install
```

3. Crie o banco e a tabela com PostGIS:

```bash
psql -U postgres -f schema.sql
```

4. Teste a conexão:

```bash
php connectionTest.php
```

## Habilitar PostgreSQL no PHP (Windows)

Se `php connectionTest.php` retornar `could not find driver`, edite o `php.ini` e descomente:

```ini
extension=pdo_pgsql
extension=pgsql
```

Reinicie o terminal e confira com:

```bash
php -m | findstr pgsql
```

## Limites da demo pública

Os endpoints de gravação continuam abertos para qualquer visitante. Todas as áreas são dados de demonstração e podem ser apagadas. Para manter a demo estável:

- **Teto de áreas** (`AREA_LIMIT`, padrão 300): ao passar do teto, a gravação remove as áreas mais antigas (FIFO). Ninguém fica bloqueado.
- **Retenção** (`AREA_RETENTION_DAYS`, padrão 7): áreas mais antigas são apagadas durante as gravações. Para uma rotina à parte, rode `php cleanup_areas.php` (só pela linha de comando).
- **Rate limit por IP** (hash HMAC, nunca o IP em texto puro): 10 gravações por hora (`RATE_LIMIT_WRITES_PER_HOUR`) e 300 leituras por minuto (`RATE_LIMIT_READS_PER_MINUTE`). Acima disso a API responde `429` com `error_code: rate_limited`.
- **Proxy**: na Render, defina `TRUST_PROXY=1` para usar o IP real do `X-Forwarded-For`. Só faça isso atrás de um proxy que você controla.
- **Moderação básica**: nomes só com letras, números, espaços e pontuação comum, e termos de `moderation_terms.txt` bloqueados em nome e descrição (`content_rejected`).
- **Limites de conteúdo**: até 500 pontos, nome com 255 caracteres e descrição com 2000. Coordenadas dentro de lat −90…90 e lng −180…180.
- `RATE_LIMIT_SECRET` deve ser definido em produção. Sem ele, o hash dos IPs usa um valor padrão e fica fraco.

**Banco já existente:** aplique uma vez a migração `migrations/002_demo_protection.sql` (cria `rate_limits` e o índice de `created_at`). Novos bancos já recebem isso pelo `docker/init.sql`.

## Funcionalidades espaciais

O projeto usa PostGIS para:

- armazenar polígonos como `GEOMETRY(Polygon, 4326)`
- calcular área em km² com `ST_Area(geography)`
- calcular perímetro em km com `ST_Perimeter(geography)`

## Testes automatizados

O `tests/run.php` roda testes unitários (validação, WKT, `AREA_LIMIT`) e de integração (endpoints, conversor UTM/GMS e limite de áreas sob concorrência). A integração precisa de um PostgreSQL/PostGIS **de teste**. O script se recusa a rodar se `DB_NAME` não contiver `test`, e limpa a tabela `areas` antes de começar.

```bash
DB_HOST=localhost DB_PORT=5432 DB_USER=postgres DB_PASS=... DB_NAME=geo_mapa_test php tests/run.php
```

Use um banco separado, nunca o banco com os dados reais.

## Script utilitário (TypeScript)

O `update-perimeter.ts` recalcula perímetros em lote usando PostGIS. Ele **não está versionado** (está no `.gitignore`), então só existe se você mantiver a cópia local. Requer Node.js e os pacotes `pg` e `dotenv`.

```bash
npm install pg dotenv
npx ts-node update-perimeter.ts
```
