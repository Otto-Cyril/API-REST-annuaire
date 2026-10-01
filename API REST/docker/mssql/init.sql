-- Crée la base applicative et un login dédié (db_owner) pour ne pas utiliser "sa" dans DATABASE_URL.
-- Optionnel : l'API fonctionne aussi avec "sa" + doctrine:database:create.
-- Exécution (depuis "API REST/") :
--   docker compose --env-file .env.local exec database /opt/mssql-tools18/bin/sqlcmd -C -S localhost -U sa -P "$MSSQL_SA_PASSWORD" -v DB_NAME=ANNUAIRE_IMM_DEV APP_PASSWORD="<mot_de_passe_app>" -i /docker/init.sql
-- Puis utiliser pdo-sqlsrv://app:<mot_de_passe_app>@127.0.0.1:1433/ANNUAIRE_IMM_DEV dans DATABASE_URL.
IF DB_ID(N'$(DB_NAME)') IS NULL
    CREATE DATABASE [$(DB_NAME)];
GO

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'app')
    CREATE LOGIN app WITH PASSWORD = N'$(APP_PASSWORD)', CHECK_POLICY = OFF;
GO

USE [$(DB_NAME)];
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'app')
    CREATE USER app FOR LOGIN app;
GO

ALTER ROLE db_owner ADD MEMBER app;
GO
