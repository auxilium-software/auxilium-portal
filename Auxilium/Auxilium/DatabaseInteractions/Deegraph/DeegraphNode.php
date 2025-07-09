<?php

namespace Auxilium\DatabaseInteractions\Deegraph;

use Auxilium\Auxilium\AuxiliumLFSObject;
use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use Darksparrow\DeegraphInteractions\DataStructures\DataURL;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use Darksparrow\DeegraphInteractions\Exceptions\InvalidUUIDFormatException;
use Darksparrow\DeegraphInteractions\QueryBuilder\QueryBuilder;

class DeegraphNode
{
    /**
     * Static cache for storing already instantiated nodes.
     * @var array
     */
    private static $cached_nodes = [];


    private $RawContent = null;
    private $Metadata = null;

    private ?array $CachedProperties = null;
    private ?array $CachedReferences = null;
    private ?array $CachedPermissions = null;

    private bool $HasRawContentBeenFetchedYet = false;
    private bool $HasMetadataBeenFetchedYet = false;

    private UUID $NodeID;

    /**
     * Constructor to initialise a DeegraphNode with a given ID.
     * @param string $id The unique identifier for the node.
     * @throws InvalidUUIDFormatException
     */
    public function __construct(string $id)
    {
        $this->NodeID = new UUID($id);
    }


    /**
     * Fetches a Node from the database by its path.
     * @param string $path The path to the Node.
     * @return DeegraphNode|null The Node if found, otherwise null.
     */
    public static function from_path(string $path): ?DeegraphNode
    {
        return GraphDatabaseConnection::node_from_path($path);
    }

    /**
     * Converts the Node to a string by fetching its data.
     * If the data is a string, it returns that string; otherwise, it returns an empty string.
     * @return string
     */
    public function __toString()
    {
        if(is_string($this->getData()))
        {
            return $this->getData();
        }
        else
        {
            return "";
        }
    }

    /**
     * Retrieves raw content data associated with the node.
     * @return mixed Raw content or null if not fetched.
     */
    public function getData(User $actor = null)
    {
        $content = $this->getContent($actor);
        return ($content == null) ? null : $content->getData();
    }

    public function getContent(User $actor = null): AuxiliumLFSObject|DataURL|null
    {
        if($this->getRawContent($actor) != null)
        {
            if(str_starts_with($this->getRawContent($actor), "data:"))
            {
                return new DataURL($this->getRawContent($actor));
            }
            elseif(str_starts_with($this->getRawContent($actor), "auxlfs:"))
            {
                return new AuxiliumLFSObject($this->getRawContent($actor));
            }
        }
        return null;
    }

    public function getRawContent(User $actor = null)
    {
        if($this->HasRawContentBeenFetchedYet)
        {
            return $this->RawContent;
        }

        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/" . $this->NodeID, "GET");

        if(is_array($result))
        {
            if(isset($result["@data"]))
            {
                $this->RawContent = $result["@data"];
            }
        }

        $this->HasRawContentBeenFetchedYet = true;
        return $this->RawContent;
    }

    public function getUuid(): string
    {
        return $this->NodeID->GetPlainUUID();
    }

    /**
     * Adds a property (link) between this Node and another Node.
     * @param string $key The key representing the property.
     * @param DeegraphNode $node The Node to link as a property.
     * @param User|null $actor The User performing the operation.
     * @param bool $force Whether to force the link creation.
     * @return mixed The result of the graph database query.
     * @throws InvalidUUIDFormatException
     */
    public function addProperty(string $key, DeegraphNode $node, User $actor = null, bool $force = false)
    {
        if($actor == null)
        {
            $actor = Session::get_current()?->getUser();
        }

        /*
         * Let's not allow injections! (Even though DDS handles permissions and damage will be limited to this user anyway,
         * there's not really a benefit to *not* preventing injections)
         */
        if($key === "#" || preg_match('/^[a-z_][a-z0-9_]*$/', $key) || preg_match('/^[0-9]+$/', $key))
        {
            // $query = "LINK {".$node->NodeID."} AS ".$key." OF {".$this->NodeID."}".($force ? " FORCE" : "");
            $query = QueryBuilder::Link()
                ->linkOfRelativePath($node->NodeID, $this->NodeID)
                ->as($key);
            if($force)
            {
                $query = $query->force();
            }
            $query = $query->build();
            return GraphDatabaseConnection::query($actor, $query);
        }
        else
        {
            return false;
        }
    }

    /**
     * Removes a property link from this Node.
     * @param string $key The key of the property to unlink.
     * @param User|null $actor The user performing the operation.
     * @return mixed The result of the graph database query.
     * @throws InvalidUUIDFormatException
     */
    public function unlinkProperty(string $key, User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if(preg_match('/^[a-z_][a-z0-9_]*$/', $key) || preg_match('/^[0-9]+$/', $key))
        { // Let's not allow injections! (Even though DDS handles permissions and damage will be limited to this user anyway, there's not really a benefit to *not* preventing injections)
            // $query = "UNLINK ".$key." FROM {".$this->GetNodeID()."}";
            $query = QueryBuilder::Unlink()
                ->unlinkWhat($key)
                ->from($this->NodeID)
                ->build();
            if($this->CachedProperties != null)
            {
                if(isset($this->CachedProperties[$key]))
                {
                    unset($this->CachedProperties[$key]); // Get rid of any dangling references to this
                }
            }
            return GraphDatabaseConnection::query($actor, $query);
        }
        else
        {
            return false;
        }
    }

    /**
     * Deletes this Node from the graph database.
     * @param User|null $actor The user performing the operation.
     * @return mixed The result of the graph database query.
     * @throws InvalidUUIDFormatException
     */
    public function delete(User $actor = null)
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        // $query = "DELETE {".$this->GetNodeID()."}";
        $query = QueryBuilder::Delete()
            ->relativePath($this->NodeID)
            ->build();
        return GraphDatabaseConnection::query($actor, $query);
    }

    /**
     * Checks if this Node is the same as another.
     * @param DeegraphNode $n Another Node to compare.
     * @return bool True if they are the same, false otherwise.
     */
    public function is(DeegraphNode $n): bool
    {
        if($this->getId() == $n->getId())
        {
            return true;
        }
        return false;
    }

    /**
     * Returns the UUID of the Node as a string.
     * @return string Node UUID.
     */
    public function getId(): string
    {
        return $this->NodeID->GetPlainUUID();
    }

    /**
     * Fetches the permissions of the Node from the database.
     * @param User|null $actor The user performing the operation.
     * @return array|null Permissions data or null if not fetched.
     * @throws InvalidUUIDFormatException
     */
    public function getPermissions(User $actor = null): ?array
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if($this->CachedPermissions != null)
        {
            return $this->CachedPermissions;
        }
        $outputMap = [];
        // $query = "PERMS ON {".$this->GetNodeID()."}";
        $query = QueryBuilder::Permission()
            ->on($this->NodeID)
            ->build();
        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@permissions"]))
        {
            foreach($response["@permissions"] as $value)
            {
                $outputMap[] = $value;
            }
            $this->CachedPermissions = $outputMap;
            return $this->CachedPermissions;
        }
        else
        {
            return null;
        }
    }

    /**
     * Fetches references to other Nodes.
     * @param User|null $actor The user performing the operation.
     * @return array|null References data or null if not fetched.
     * @throws InvalidUUIDFormatException
     */
    public function getReferences(User $actor = null): ?array
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if($this->CachedReferences != null)
        {
            return $this->CachedReferences;
        }
        $outputMap = [];
        // $query = "REFERENCES {".$this->GetNodeID()."}";
        $query = QueryBuilder::References()
            ->relativePath($this->NodeID)
            ->build();
        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@map"]))
        {
            foreach($response["@map"] as $key => $value)
            {
                $arr = [];
                foreach($value as $refNodeId)
                {
                    $arr[] = DeegraphNode::from_id($refNodeId);
                }
                $outputMap[$key] = $arr;
            }
            $this->CachedReferences = $outputMap;
            return $outputMap;
        }
        else
        {
            return null;
        }
    }

    /**
     * Fetches a Node by its ID, using a static cache for optimisation.
     * @param string|null $id The ID of the Node.
     * @return DeegraphNode|null The Node if found or created, null otherwise.
     * @throws InvalidUUIDFormatException
     */
    public static function from_id(string $id = null): ?DeegraphNode
    {
        if($id == null)
        {
            return null;
        }

        if(isset(self::$cached_nodes[$id]))
        {
            if(self::$cached_nodes[$id] instanceof DeegraphNode)
            {
                return self::$cached_nodes[$id]; // Skip creating the node representation - we've already loaded it!
            }
        }

        // Do stuff

        self::$cached_nodes[$id] = new DeegraphNode($id);

        if(isset(self::$cached_nodes[$id]))
        {
            return self::$cached_nodes[$id];
        }
        else
        {
            return null;
        }
    }

    /**
     * Retrieves a property of the Node by its key.
     * @param string $property Property name to fetch.
     * @param User|null $actor The user performing the operation.
     * @return mixed|null Property value or null if not found.
     */
    public function getProperty(string $property, User $actor = null): mixed
    {
        if($actor === null)
        {
            $actor = Session::get_current()?->getUser();
        }
        $temp = $this->getProperties($actor);
        return $temp[$property] ?? null;
    }

    public function getProperties(User $actor = null): ?array
    {
        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }
        if($this->CachedProperties != null)
        {
            return $this->CachedProperties;
        }
        $outputMap = [];
        // $query = "DIRECTORY {".$this->GetNodeID()."}";
        $query = QueryBuilder::Directory()
            ->relativePath($this->NodeID)
            ->build();

        $response = GraphDatabaseConnection::query($actor, $query);
        if(isset($response["@map"]))
        {
            foreach($response["@map"] as $key => $value)
            {
                $outputMap[$key] = DeegraphNode::from_id($value);
            }
            $this->CachedProperties = $outputMap;
            return $outputMap;
        }
        else
        {
            return null;
        }
    }

    public function getObjectSize()
    {
        $content = $this->getContent($actor);
        return ($content == null) ? 0 : $content->getSize();
    }

    public function getMimeType()
    {
        $content = $this->getContent($actor);
        return ($content == null) ? null : $content->getMimeType();
    }

    public function getTimestamp(): string
    {
        return date("c", strtotime($this->getNodeMetadata()["@created"]));
    }

    /**
     * Retrieves metadata associated with the node.
     * @return mixed Metadata or null if not fetched.
     */
    public function getNodeMetadata(User $actor = null): ?array
    {
        if($this->HasMetadataBeenFetchedYet)
        {
            return $this->Metadata;
        }

        if($actor == null)
        {
            $actor = Session::get_current()->getUser();
        }

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $this->getId() . "}", "GET");

        if(is_array($result))
        {
            $this->Metadata = $result;
        }

        $this->HasMetadataBeenFetchedYet = true;
        return $this->Metadata;
    }

    public function getTimestampInt(): false|int
    {
        return strtotime($this->getNodeMetadata()["@created"]);
    }

    public function getSchema()
    {
        return Schema::from_url($this->getSchemaUrl());
    }

    public function getSchemaUrl()
    {
        return isset($this->getNodeMetadata()["@schema"]) ? $this->getNodeMetadata()["@schema"] : null;
    }

    public function getCreator(): ?DeegraphNode
    {
        return DeegraphNode::from_id($this->getNodeMetadata()["@creator"]);
    }

    public function extendsOrInstanceOf(string $schema): bool
    {
        if(!isset($this->getNodeMetadata()["@schema"]))
        {
            return false;
        }
        return $this->getNodeMetadata()["@schema"] == $schema;
    }
}
