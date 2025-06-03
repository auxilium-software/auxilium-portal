<?php

namespace Auxilium\API\Controllers;

use Auxilium\API\Models\DraftModel;
use Auxilium\API\Superclasses\APIController;
use Auxilium\API\Superclasses\APIModel;
use Auxilium\Auxilium\API\Controllers\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\EmailHandling\InternetMessageTransport;
use Auxilium\Schemas\CollectionSchema;
use Auxilium\Schemas\MessageSchema;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\Security;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;
use RuntimeException;

class MessageController extends APIController
{

    public function __construct()
    {
        parent::__construct();
    }


    #[NoReturn]
    #[Get(
        path       : "/api/v2/drafts",
        operationId: "[GET]/api/v2/drafts",
        description: "",
        summary    : "Messages",
        tags       : [
            "Messages",
        ],
        responses  : [
            new Response(
                response   : 200,
                description: "",
                content    : new JsonContent(
                    ref: "#/components/schemas/DraftModel"
                )
            )
        ],
        deprecated : false,
    )]
    public function Get()
    {
        $this->Model = new DraftModel();


        $draft_content = null;
        $put_data = false;

        $draft_id = $this->URIUtilities->getURIComponents()[4];
        $action = "access";
        if(count($this->URIUtilities->getURIComponents()) > 5)
        {
            $action = strtolower($this->URIUtilities->getURIComponents()[5]);
        }
        if($draft_id == "new")
        {
            $draft_id = EncodingTools::GenerateNewUUID();
            $draft_content = [];
            $put_data = true;
        }
        if(!preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $draft_id))
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Malformed uuid";
            $this->Render();
        }
        $message_draft_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/MessageDrafts/" . Session::get_current()->getUser()->getId() . "/" . $draft_id . ".json";
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/MessageDrafts/" . Session::get_current()->getUser()->getId() . "/"))
        {
            if(!mkdir($concurrentDirectory = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/MessageDrafts/" . Session::get_current()->getUser()->getId() . "/", 0700, true) && !is_dir($concurrentDirectory))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        if($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "PUT")
        {
            $draft_content = file_get_contents("php://input");
            $draft_content = json_decode($draft_content);
            $put_data = true;
        }

        if($action === "access")
        {
            $this->Model->DraftID = $draft_id;
            if($put_data)
            {
                $bytes_written = file_put_contents($message_draft_path, json_encode($draft_content));
                if($bytes_written === FALSE)
                {
                    $this->Model = new APIModel();
                    $this->Model->ErrorText = "Failed to write new message to RAM disk";
                    $this->Render();
                }
                else
                {
                    $this->Model->BytesWritten = $bytes_written;
                }
            }
            else
            {
                $this->Model->Content = json_decode(file_get_contents($message_draft_path), true);
            }
            $this->Render();
        }
        elseif($action === "send")
        {
            $draft_content = json_decode(file_get_contents($message_draft_path), true, 512, JSON_THROW_ON_ERROR);
            $message_build_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/MessageDrafts/" . Session::get_current()->getUser()->getId() . "/" . $draft_id . ".eml";
            $build_content = "X-Auxilium-Message-Version: 2.0\r\n";
            $build_content .= "MIME-Version: 1.0\r\n";
            $build_content .= "Message-ID: $draft_id." . Session::get_current()->getUser()->getId() . "@" . INSTANCE_BRANDING_DOMAIN_NAME . "\r\n";
            $boundary = EncodingTools::Base64EncodeURLSafe(Security::GeneratePseudoRandomBytes(length: 48));

            $message_parties = [];
            $message_parties[] = Session::get_current()->getUser();
            $from_user_name = Session::get_current()->getUser()->getDisplayName();
            if($from_user_name !== null)
            {
                $build_content .= "From: \"" . EncodingTools::RC2047Encode($from_user_name) . "\" <auxiliuminbox+" . Session::get_current()->getUser()->getId() . "@" . INSTANCE_BRANDING_DOMAIN_NAME . ">\r\n";
            }
            else
            {
                $build_content .= "From: auxiliuminbox+" . Session::get_current()->getUser()->getId() . "@" . INSTANCE_BRANDING_DOMAIN_NAME . "\r\n";
            }

            $build_content .= "To: ";
            $first = true;
            foreach($draft_content["recipients"] as &$recipient_string)
            {
                if($first)
                {
                    $first = false;
                }
                else
                {
                    $build_content .= ", ";
                }
                $to_user_name = null;
                $recipient = null;
                if(preg_match("/\{[a-f0-9]{8}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{12}\}/", $recipient_string))
                {
                    $recipient = new User(substr($recipient_string, 1, 36));
                    if($recipient === null)
                    {
                        $this->Model = new APIModel();
                        $this->Model->ErrorText = "Failed to create valid RFC822 object. Invalid local user id provided.";
                        $this->Render();
                    }
                    else
                    {
                        $message_parties[] = $recipient;
                    }
                    $to_user_name = $recipient->getDisplayName();
                    if($to_user_name !== null)
                    {
                        $build_content .= "\"" . EncodingTools::RC2047Encode($to_user_name) . "\" <auxiliuminbox+" . $recipient->getId() . "@" . INSTANCE_BRANDING_DOMAIN_NAME . ">";
                    }
                    else
                    {
                        $build_content .= "auxiliuminbox+" . $recipient->getId() . "@" . INSTANCE_BRANDING_DOMAIN_NAME . "";
                    }
                }
                else
                {
                    if(preg_match("/^[_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i", $recipient_string))
                    {
                        $build_content .= $recipient_string;
                    }
                    else
                    {
                        $this->Model = new APIModel();
                        $this->Model->ErrorText = "Failed to create valid RFC822 object. Invalid external email address provided.";
                        $this->Render();
                    }
                }
            }
            $build_content .= "\r\n";

            $build_content .= "Subject: Auxilium Message\r\n";
            $build_content .= "Content-Type: multipart/alternative; boundary=$boundary\r\n";
            $build_content .= "\r\n";

            $contents = [
                [
                    "content_type" => "text/plain",
                    "content" => $draft_content["body"]
                ]
            ];

            foreach($contents as &$content)
            {
                if($content["content_type"] === "text/plain")
                {
                    $content["content_type"] = "text/plain; charset=\"UTF-8\"";
                }
            }

            $first = true;
            foreach($contents as &$content)
            {
                if($first)
                {
                    $build_content .= "--$boundary\r\n";
                    $first = false;
                }
                else
                {
                    $build_content .= "\r\n";
                }

                $build_content .= "Content-Type: " . $content["content_type"] . "\r\n\r\n";
                $build_content .= $content["content"] . "\r\n";

                $build_content .= "--$boundary";
            }
            $build_content .= "--\r\n";

            $bytes_written = file_put_contents($message_build_path, $build_content);
            if($bytes_written === false)
            {
                $this->Model = new APIModel();
                $this->Model->ErrorText = "Failed to write new RFC822 object to RAM disk";
                $this->Render();
            }
            else
            {
                $message_node = GraphDatabaseConnection::new_node_raw(
                    data_url: "auxlfs://" . INSTANCE_BRANDING_DOMAIN_NAME . "/++message%3Arfc822+" . $bytes_written,
                    schema  : URLHandling::GetURLForSchema(MessageSchema::class)
                );

                rename($message_build_path, LOCAL_STORAGE_DIRECTORY . '/Messages/' . $message_node->getId());


                $attach_failures = [];
                $notified_parties = [];

                foreach($message_parties as &$message_party)
                {
                    if(!in_array($message_party->getId(), $notified_parties, true))
                    {
                        try
                        {
                            // Due to caching, we MUST add property using the node returned from creation
                            if($message_party->getProperty("messages") === null)
                            {
                                $messages_node = GraphDatabaseConnection::new_node(
                                    data      : null,
                                    media_type: null,
                                    schema    : URLHandling::GetURLForSchema(
                                        targetSchemaClassName: CollectionSchema::class
                                    )
                                );
                                $message_party->addProperty("messages", $messages_node);
                                $messages_node->addProperty("#", $message_node);
                            }
                            else
                            {
                                $message_party->getProperty("messages")->addProperty("#", $message_node);
                            }
                            $notified_parties[] = $message_party->getId();
                        }
                        catch(Exception $e)
                        {
                            $attach_failures[] = $message_party->getId();
                        }
                    }
                }

                $job_reference = InternetMessageTransport::send(
                    internet_message: file_get_contents(
                        filename: LOCAL_STORAGE_DIRECTORY . '/Messages/' . $message_node->getId()
                    ),
                    type            : "MIME"
                );

                $this->Model->JobReference = $job_reference;

                if(count($attach_failures) > 0)
                {
                    $this->Model->AttachFailures = $attach_failures;
                }
                if(count($notified_parties) > 0)
                {
                    $this->Model->AttachedTo = $notified_parties;
                }
                $this->Model->MessageNodeID = $message_node->getId();
                $this->Render();
            }
        }
        else
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Invalid action";
            $this->Render();
        }
    }
}
