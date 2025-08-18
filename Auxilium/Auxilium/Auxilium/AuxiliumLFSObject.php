<?php

namespace Auxilium\Auxilium;

use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;

class AuxiliumLFSObject
{
    private $data = null;
    private $dataHash = null;
    private $mimeType = null;
    private $uuid = null;
    private $length = null;
    private $domain = null;
    private $readPermission = [];

    public function __construct(string $stringRepresentation)
    {
        $stringRepresentation = trim($stringRepresentation);
        if(mb_strpos($stringRepresentation, "auxlfs://") === 0)
        {
            $stringRepresentation = mb_substr($stringRepresentation, 9);
            $expl1 = explode("/", $stringRepresentation);
            $expl2 = [];
            if($expl1[0] !== '')
            {
                $this->domain = $expl1[0];
            }
            if(count($expl1) > 1)
            {
                $expl2 = explode("+", $expl1[1]);
            }
            if($expl2[0] !== '')
            {
                $this->uuid = $expl2[0];
            }
            if(count($expl2) > 1)
            {
                if($expl2[1] !== '')
                {
                    $this->dataHash = $expl2[1];
                }
            }
            if(count($expl2) > 2)
            {
                if($expl2[2] !== '')
                {
                    $this->mimeType = urldecode($expl2[2]);
                }
            }
            if(count($expl2) > 3)
            {
                if($expl2[3] !== '')
                {
                    $this->length = intval($expl2[3]);
                }
            }
        }
    }

    public function isWriteable(User $actor = null): bool
    {
        if($this->exists())
        {
            return false;
        }
        if($actor === null)
        {
            $actor = Session::get_current()?->getUser();
        }
        $actorId = ($actor === null) ? null : $actor->getId();
        if($this->getId() === null)
        {
            return false;
        }

        $query = "SELECT {" . $this->getId() . "}/@creator/@id";
        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@rows"]))
        {
            if(count($response["@rows"]) > 0)
            {
                if($response["@rows"][0]["{" . $this->getId() . "}/@creator/@id"]["{" . $this->getId() . "}/@creator/@id"] == "{" . $actorId . "}")
                {
                    return true;
                }
            }
        }
        return false;
    }

    public function exists(): bool
    {
        if(file_exists($this->getFilePath()))
        {
            return true;
        }
        return false;
    }

    public function getFilePath(): string
    {
        if(str_starts_with(haystack: $this->getMimeType(), needle: "message"))
        {
            return LOCAL_STORAGE_DIRECTORY . "/Messages/" . $this->getId();
        }
        return LOCAL_STORAGE_DIRECTORY . "/LFS/" . $this->getId();
    }

    public function getId()
    {
        return $this->uuid;
    }

    public function getMimeType(): string
    {
        return $this->mimeType ?? "application/octet-stream";
    }

    public function __toString()
    {
        return "auxlfs://" . $this->domain . "/" . $this->uuid . "+" . $this->getHash() . "+" . urlencode($this->getMimeType()) . "+" . $this->getSize();
    }

    public function getHash()
    {
        return $this->dataHash;
    }

    public function getSize(): int
    {
        if($this->length === null)
        {
            $this->length = strlen($this->getData());
        }
        return $this->length;
    }

    public function getData(User $actor = null): false|string|null
    {
        if($this->canRead($actor))
        {
            if($this->data === null)
            {
                if($this->exists())
                {
                    $this->data = file_get_contents($this->getFilePath());
                }
            }
            return $this->data;
        }
        return null;
    }

    public function canRead(User $actor = null)
    {
        if($actor === null)
        {
            $actor = Session::get_current()?->getUser();
        }
        $actorId = ($actor === null) ? null : $actor->getId();
        if(!isset($this->readPermission[$actorId]))
        {
            if($this->getId() === null)
            {
                $this->readPermission[$actorId] = false;
            }
            else
            {
                $query = "SELECT {" . $this->getId() . "}";
                $response = GraphDatabaseConnection::query($actor, $query);
                $this->readPermission[$actorId] = false;
                if(isset($response["@rows"]))
                {
                    if(count($response["@rows"]) > 0)
                    {
                        $query = "PERMS ON {" . $this->getId() . "}";
                        $response = GraphDatabaseConnection::query($actor, $query);
                        if(isset($response["@permissions"]))
                        {
                            foreach($response["@permissions"] as $value)
                            {
                                if(strtoupper($value) === "READ")
                                {
                                    $this->readPermission[$actorId] = true;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this->readPermission[$actorId];
    }
}
