<?php

namespace Auxilium\EmailHandling;

use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\Enumerators\InternetMessageTransportService;
use Auxilium\Exceptions\MessageSendException;
use Auxilium\Helpers\Messaging\SMTPUtilities;
use Auxilium\Helpers\MSGraph\MSGraphInteractions;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\Security;
use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Exception;
use ZBateson\MailMimeParser\Message;

class InternetMessageTransport
{
    public static function scan_inboxes()
    {
        if(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == InternetMessageTransportService::MS_GRAPH->value)
        {

        }
        elseif(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == InternetMessageTransportService::STANDARD->value)
        {

        }
        elseif(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == InternetMessageTransportService::AWS->value)
        {
            try
            {
                $client = new S3Client([
                        'region' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["region"],
                        'version' => '2006-03-01',
                        'credentials' => [
                            'key' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_key"],
                            'secret' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_secret"],
                        ]
                    ]
                );
                //
                $response = $client->listObjectsV2([
                        'Bucket' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["bucket"]
                    ]
                );
                foreach($response["Contents"] as $object)
                {
                    $job_id = bin2hex(pack("J", time())) . "." . EncodingTools::Base64EncodeURLSafe(Security::GeneratePseudoRandomBytes(length: 3 * 8));
                    $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_id . ".json";

                    $job = [
                        "type" => "INGEST_S3_EMAIL",
                        "max_tries" => 3,
                        "tries" => 0,
                        "key" => $object["Key"]
                    ];

                    file_put_contents($job_path, json_encode($job, JSON_PRETTY_PRINT));
                }
                return true;
            }
            catch(Exception $e)
            {
                echo "<pre>" . htmlentities($e->getMessage()) . "</pre>";
                //die();
                return false;
            }
        }
        return false;
    }

    public static function ingest_s3_object(string $key)
    {
        $client = new S3Client([
                'region' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["region"],
                'version' => '2006-03-01',
                'credentials' => [
                    'key' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_key"],
                    'secret' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_secret"],
                ]
            ]
        );
        //
        $response = $client->getObject([
                'Bucket' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["bucket"],
                'Key' => $key
            ]
        );
        $response["Body"];
        //echo "<pre>".htmlentities()."</pre>";
        //die();
    }

    public static function send_autodetect(string $internet_message)
    {
        $type = null;
        if(substr($internet_message, 0, 7) === "BEGIN:VCALENDAR")
        {
            $type = "ICALENDAR";
        }
        if($type == null)
        {
            if(strpos($internet_message, substr("MIME-Version: ", 0, 2048)))
            {
                $type = "MIME";
            }
        }

        return self::send($internet_message, $type);
    }

    public static function send_now(string $rfc822_raw_message)
    {
        $debug = false;

        switch(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"])
        {
            case InternetMessageTransportService::MS_GRAPH->value:
                $graph = new MSGraphInteractions();
                $graph->SendMail(
                    $rfc822_raw_message,
                    $debug,
                );
                return true;
            case InternetMessageTransportService::STANDARD->value:
                $mailer = new SMTPUtilities();
                $mailer->SetMessage($rfc822_raw_message);

                if(!$mailer->Mailer->send())
                    throw new MessageSendException($mailer->Mailer->ErrorInfo);

                echo "\n";
                echo $mailer->Mailer->getSentMIMEMessage();
                return true;
            case InternetMessageTransportService::AWS->value:
                $client = new SesClient([
                        'region' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["region"],
                        'version' => '2010-12-01',
                        'credentials' => [
                            'key' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_key"],
                            'secret' => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["access_secret"],
                        ]
                    ]
                );
                $response = $client->sendRawEmail([
                        'RawMessage' => [
                            'Data' => $rfc822_raw_message
                        ]
                    ]
                );
                return true;
        }
    }

    public static function send(string $internet_message, string $type)
    {
        $job_id = bin2hex(pack("J", time())) . "." . EncodingTools::Base64EncodeURLSafe(Security::GeneratePseudoRandomBytes(length: 3 * 8));
        $job_change_key = EncodingTools::Base64EncodeURLSafe(Security::GeneratePseudoRandomBytes(length: 3 * 16));
        $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_id . ".json";

        if($type == "MIME")
        {
            $mime_message = Message::from($internet_message, false);
            $sender_headers = $mime_message->getAllHeadersByName("from");
            $senders = [];
            foreach($sender_headers as &$sender_header)
            {
                $sender_header_parts = $sender_header->getParts();
                foreach($sender_header_parts as &$sender_header_part)
                {
                    $senders[] = $sender_header_part->getValue();
                }
            }
            $sender = end($senders); // We should only really have one "from"
            $recipient_headers = $mime_message->getAllHeadersByName("to");
            $recipients = [];
            foreach($recipient_headers as &$recipient_header)
            {
                $recipient_header_parts = $recipient_header->getParts();
                foreach($recipient_header_parts as &$recipient_header_part)
                {
                    $recipients[] = $recipient_header_part->getValue();
                }
            }
            //var_dump($sender);
            //echo " ==> ";
            //var_dump($recipients);

            //$sender = "me@alexbaldwin.dev";


            $auxinbox_regex = "/auxiliuminbox\+([a-f0-9]{8}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{4}\-[a-f0-9]{12})@([^><]+)/";

            if($sender === false)
            {
                $mime_message->setRawHeader("From", INSTANCE_BRANDING_NAME . " <" . InternetMessageTransport::get_default_smtp_outbound_address() . ">");
            }
            else
            {
                preg_match($auxinbox_regex, $sender, $matches, PREG_UNMATCHED_AS_NULL);
                if(count($matches) > 0)
                {
                    $sender_user = new User($matches[1]);
                    $mime_message->removeHeader("from");
                    if(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == "AWS_SES")
                    {
                        if(isset(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_dynamic_prefix"]) && strlen(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_dynamic_prefix"]) > 0)
                        {
                            $mime_message->setRawHeader("From", $sender_user->getFullName() . " <" . INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_dynamic_prefix"] . "+" . $sender_user->getId() . "@" . INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_domain"] . ">");
                        }
                        else
                        {
                            $mime_message->setRawHeader("From", $sender_user->getFullName() . " <" . $sender_user->getId() . "@" . INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_domain"] . ">");
                        }
                    }
                    else
                    {
                        $mime_message->setRawHeader("From", $sender_user->getFullName() . " <" . InternetMessageTransport::get_default_smtp_outbound_address() . ">");
                    }
                }
            }

            $recipient_strings = [];
            $mime_message->removeHeader("to");
            foreach($recipients as &$recipient)
            {
                preg_match($auxinbox_regex, $recipient, $matches, PREG_UNMATCHED_AS_NULL);
                if(count($matches) > 0)
                {
                    $recipient_user = new User($matches[1]);
                    if($recipient_user->getContactEmail() != null)
                    {
                        $recipient_strings[] = $recipient_user->getFullName() . " <" . $recipient_user->getContactEmail() . ">";
                    }
                }
                else
                {
                    $recipient_strings[] = $recipient;
                }
            }
            $mime_message->setRawHeader("To", implode(", ", $recipient_strings));

            $mime_message->removeHeader("X-Auxilium-Message-Version");
            $mime_message->setRawHeader("X-Auxilium-Message-Transport-Version", "2.0");
            $mime_message->setRawHeader("X-Auxilium-Export-Type", "FORWARD_MESSAGE");

            $job = [
                "type" => "SEND_EMAIL",
                "max_tries" => 3,
                "tries" => 0,
                "job_key" => $job_change_key,
                "content" => strval($mime_message)
            ];

            file_put_contents($job_path, json_encode($job, JSON_PRETTY_PRINT));

            return $job_id . "." . $job_change_key;
        }
    }

    public static function get_default_smtp_outbound_address()
    {
        if(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == InternetMessageTransportService::MS_GRAPH->value)
        {
            return INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["external_smtp_address"];
        }
        elseif(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == InternetMessageTransportService::STANDARD->value)
        {
            return INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"];
        }
        elseif(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["type"] == InternetMessageTransportService::AWS->value)
        {
            return INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["outbound_email_address"];
        }
    }
}
