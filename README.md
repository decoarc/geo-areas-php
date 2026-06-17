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

```ini
DB_HOST=localhost
DB_PORT=5432
DB_USER=postgres
DB_PASS=azerbijal324
DB_NAME=geo_mapa_app
```

### 4. Conectar no DBeaver

- Host: `localhost`
- Port: `5432`
- Database: `geo_mapa_app`
- Username: `postgres`
- Password: `azerbijal324`

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

## Funcionalidades espaciais

O projeto usa PostGIS para:

- armazenar polígonos como `GEOMETRY(Polygon, 4326)`
- calcular área em km² com `ST_Area(geography)`
- calcular perímetro em km com `ST_Perimeter(geography)`

## Script utilitário (TypeScript)

O arquivo `update-perimeter.ts` recalcula perímetros em lote usando PostGIS. Requer Node.js e o pacote `pg`.

```bash
npm install pg dotenv
npx ts-node update-perimeter.ts
```
