<?php

namespace Auxilium;

use Auxilium\DatabaseInteractions\RelationalDatabaseConnection;
use Auxilium\Utilities\Security;
use PDO;

class PersistentFormData
{
    public static function get()
    {
        $formPersistenceKey = null;
        if(isset($_GET["fpk"]))
        {
            if(strlen($_GET["fpk"]) >= 85)
            {
                $formPersistenceKey = $_GET["fpk"];
            }
            else
            {
                return null;
            }
        }
        else
        {
            return null;
        }
        $bindVariables = [
            "form_key" => $formPersistenceKey
        ];
        $sql = "SELECT persistence_data FROM form_persistence_data WHERE form_key=:form_key";
        $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
        $statement->execute($bindVariables);
        $returnedData = $statement->fetch(PDO::FETCH_ASSOC);
        if($returnedData === FALSE)
        {
            return [];
        }
        else
        {
            return json_decode($returnedData["persistence_data"], true);
        }
    }

    public static function set($data)
    {
        $formPersistenceKey = null;
        if(isset($_GET["fpk"]))
        {
            $formPersistenceKey = $_GET["fpk"];
            if(!preg_match("/^[a-zA-Z0-9\-_]*={0,2}$/", $formPersistenceKey))
            {
                $formPersistenceKeyBytes = Security::GeneratePseudoRandomBytes(length: 64);
                $formPersistenceKey = rtrim(strtr(base64_encode($formPersistenceKeyBytes), '+/', '-_'), '=');
            }
            else
            {
                $bindVariables = [
                    "form_key" => $formPersistenceKey,
                    "persistence_data" => json_encode($data)
                ];
                $sql = "UPDATE form_persistence_data SET persistence_data=:persistence_data WHERE form_key=:form_key";
                $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
                $statement->execute($bindVariables);
                $_GET["fpk"] = $formPersistenceKey;
                return $formPersistenceKey;
            }
        }
        else
        {
            $formPersistenceKeyBytes = Security::GeneratePseudoRandomBytes(length: 64);
            $formPersistenceKey = rtrim(strtr(base64_encode($formPersistenceKeyBytes), '+/', '-_'), '=');
        }
        $bindVariables = [
            "form_key" => $formPersistenceKey,
            "persistence_data" => json_encode($data)
        ];
        $sql = "INSERT INTO form_persistence_data (persistence_data, form_key) VALUES (:persistence_data, :form_key)";
        $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
        $statement->execute($bindVariables);
        $_GET["fpk"] = $formPersistenceKey;
        return $formPersistenceKey;
    }
}
