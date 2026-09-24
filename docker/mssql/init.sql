-- Crée la base "annuaire" et l'utilisateur applicatif "app".
-- Exécution : docker compose exec database /opt/mssql-tools18/bin/sqlcmd -C -S localhost -U sa -P "$MSSQL_SA_PASSWORD" -i /docker/init.sql
-- Le mot de passe doit correspondre à celui de DATABASE_URL (.env.local).
IF DB_ID(N'annuaire') IS NULL
    CREATE DATABASE annuaire;
GO

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'app')
    CREATE LOGIN app WITH PASSWORD = N'!ChangeMe!', CHECK_POLICY = OFF;
GO

USE annuaire;
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'app')
    CREATE USER app FOR LOGIN app;
GO

ALTER ROLE db_owner ADD MEMBER app;
GO
