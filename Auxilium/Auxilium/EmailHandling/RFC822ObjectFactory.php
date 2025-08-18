<?php

namespace Auxilium\EmailHandling;

use Auxilium\NodeFactory;
use Auxilium\Utilities\Security;
use ZBateson\MailMimeParser\Message;

class RFC822ObjectFactory extends NodeFactory
{
    private $emailData = null;
    private $recipients = [];
    private $message = null;

    public function __construct()
    {
        parent::__construct(null);
        $this->setMimeType("message/rfc822");
    }

    public function fromExisting($data)
    {
        $this->message = Message::from($data, false);

        $non_normal_content_parts = $this->message->getAllAttachmentParts();
        foreach($non_normal_content_parts as &$non_normal_content_part)
        {
            if($non_normal_content_part->getContentId() == null)
            {
                $content_key = rtrim(strtr(base64_encode(Security::GeneratePseudoRandomBytes(length: 24)), '+/', '-_'), '=');
                $non_normal_content_part->setRawHeader("Content-ID", "vnd-auxilium-random-id-" . $content_key);
            }
        } // If we force everything to have a Content-ID it makes attachments a lot simpler!

        return $this;
    }

    public function build()
    {
        $this->setData($this->message);
        foreach($this->message->getAllHeaders() as $header)
        {
            switch(strtolower($header->getName()))
            {
                case "to":
                case "from":
                    $emails = $header->getParts();
                    foreach($emails as $email)
                    {

                        //"email_address" => $email, Do attaching here
                    }
                    break;
                default:
                    //echo "UNKNOWN HEADER ".$header->getName()."<br/>";
                    break;
            }
        }
        //$this->addRelationshipUnsafe("SENDER", $sender);

        return parent::build();
    }
}
