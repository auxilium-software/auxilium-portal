<?php

namespace Auxilium\DatabaseInteractions\MariaDB;

use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use PDO;

class MariaDBServerConnection
{
    private PDO $pdo;

    public function __construct()
    {
        $servername = INSTANCE_CREDENTIAL_SQL_HOST;
        $database = INSTANCE_CREDENTIAL_SQL_DATABASE;
        $username = INSTANCE_CREDENTIAL_SQL_USERNAME;
        $password = INSTANCE_CREDENTIAL_SQL_PASSWORD;

        $this->pdo = new PDO(
            dsn     : "mysql:host=$servername;dbname=$database",
            username: $username,
            password: $password,
        );
        $this->pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
    }

    public function RunSelect(SelectInterface $queryBuilder): array
    {
        $sth = $this->pdo->prepare($queryBuilder->getStatement());
        $sth->execute($queryBuilder->getBindValues());
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public function RunOneRowSelect(SelectInterface $queryBuilder): array|null
    {
        $sth = $this->pdo->prepare($queryBuilder->getStatement());
        $sth->execute($queryBuilder->getBindValues());
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);

        if(count($result) === 1)
        {
            return $result[0];
        }

        return null;
    }

    public function RunInsert(InsertInterface $queryBuilder): bool
    {
        $sth = $this->pdo->prepare($queryBuilder->getStatement());
        return $sth->execute($queryBuilder->getBindValues());
    }

    public function RunDelete(DeleteInterface $queryBuilder): bool
    {
        $sth = $this->pdo->prepare($queryBuilder->getStatement());
        return $sth->execute($queryBuilder->getBindValues());
    }

    public function InitialDatabaseSetup(): bool
    {
        $filePath = __DIR__ . "/../../../Public/Static/Misc/MariaDBSchema.sql";
        $schema = file_get_contents($filePath);

        $result = $this->pdo->exec(statement: $schema);
        return $result === 0;
    }
}
