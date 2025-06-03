<?php

namespace Auxilium\API\Controllers;

use Auxilium\API\Models\LFSModel;
use Auxilium\API\Superclasses\APIController;
use Auxilium\API\Superclasses\APIModel;
use Auxilium\Auxilium\AuxiliumLFSObject;
use Auxilium\Utilities\Security;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Response;

class LFSController extends APIController
{
    private $file_id = null;
    private $file_hash = null;
    private $metadata = false;
    private $mime_type = null;
    private AuxiliumLFSObject $lfsobj;



    public function __construct()
    {
        parent::__construct();

        if(count($this->URIUtilities->getURIComponents()) > 4)
        {
            $spl = explode("+", $this->URIUtilities->getURIComponents()[4]);
            $this->file_id = strtolower($spl[0]);
            if(count($spl) > 1)
            {
                $this->file_hash = $spl[1];
            }
            if(count($spl) > 2)
            {
                $this->mime_type = str_replace(":", "/", urldecode($spl[2]));
            }
        }

        if($this->mime_type == null)
        {
            $this->mime_type = "application/octet-stream";
        }

        if(strtolower($this->URIUtilities->getGetParameters()) == "metadata")
        {
            $this->metadata = true;
        }

        $this->lfsobj = new AuxiliumLFSObject("auxlfs://" . INSTANCE_CREDENTIAL_DDS_HOST . "/" . $this->file_id . "+" . $this->file_hash . "+" . urlencode($this->mime_type));

        if(!$this->lfsobj->canRead())
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Missing read permission";
            $this->Render();
        }

    }
    /*

    Byteserving code derived from work by Răzvan Valentin Florian avaliable at:
    https://github.com/rvflorian/byte-serving-php
    Accessed 2022-04-14

    MIT License

    Copyright (c) 2018 Răzvan Valentin Florian

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.

    */
    #[NoReturn]
    #[Get(
        path       : "/api/v2/lfs",
        operationId: "[GET]/api/v2/lfs",
        description: "",
        summary    : "LFS",
        tags       : [
            "LFS",
        ],
        responses  : [
            new Response(
                response   : 200,
                description: ""
            )
        ],
        deprecated : false,
    )]
    public function Get(): void
    {
        $this->Model = new LFSModel();


        if($this->file_id == null)
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Missing uuid";
            $this->Render();
        }
        if(preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $this->file_id))
        {
            //echo "SENDING ".$file_id;
            if(!$this->lfsobj->exists())
            {
                $this->Model = new APIModel();
                $this->Model->ResponseCode = 404;
                $this->Model->ErrorText = "LFS file content is missing";
                $this->Render();
            }

            if($this->metadata)
            {
                $this->Model->ID = $this->file_id;
                $this->Model->Hash = $this->file_hash;
                $this->Model->Size = filesize($this->lfsobj->getFilePath());
                $this->Render();
            }
            else
            {
                //header("Content-Disposition: filename=\"".$file_name."\"");
                $data_size = filesize($this->lfsobj->getFilePath());
                if($_SERVER["REQUEST_METHOD"] === "GET" && isset($_SERVER["HTTP_RANGE"]) && $range = stristr(trim($_SERVER["HTTP_RANGE"]), "bytes="))
                {

                    $range = substr($range, 6);
                    $ranges = explode(",", $range);

                    if(count($ranges) > 0)
                    {
                        $firsts = [];
                        $lasts = [];
                        $rangecount = 0;

                        foreach($ranges as $range)
                        {
                            $dash = strpos($range, "-");
                            $first = trim(substr($range, 0, $dash));
                            $last = trim(substr($range, $dash + 1));
                            if($first == "")
                            {
                                $suffix = $last;
                                $last = $data_size - 1;
                                $first = $data_size - $suffix;
                                if($first < 0) $first = 0;
                            }
                            else
                            {
                                if($last == "" || ($last > ($data_size - 1))) $last = $data_size - 1;
                            }
                            if($first > $last)
                            {
                                // Unsatisfiable range
                                header("Status: 416 Requested range not satisfiable");
                                header("Content-Range: */$data_size");
                                exit();
                            }
                            else
                            {
                                $firsts[] = $first;
                                $lasts[] = $last;
                                $rangecount++;
                            }
                        }

                        header("HTTP/1.1 206 Partial content");
                        header("Accept-Ranges: bytes");
                        if(count($ranges) > 1)
                        {
                            $boundary = bin2hex(Security::GeneratePseudoRandomBytes(length: 32)); // Set a random boundary - we better hope this doesn't show up in the file!
                            $content_length = 0;

                            for($i = 0; $i < $rangecount; $i++)
                            {
                                $content_length += strlen("\r\n--$boundary\r\n");
                                $content_length += strlen("Content-Type: " . $this->mime_type . "\r\n");
                                $content_length += strlen("Content-Range: bytes " . $firsts[$i] . "-" . $lasts[$i] . "/$data_size\r\n\r\n");
                                $content_length += $lasts[$i] - $firsts[$i];
                            }
                            $content_length += strlen("\r\n--$boundary--\r\n");
                            header("Content-Length: $content_length");
                            header("Content-Type: multipart/x-byteranges; boundary=$boundary");

                            while(ob_get_level())
                            {
                                ob_end_clean();
                            }
                            flush();
                            $fh = fopen($this->lfsobj->getFilePath(), 'r');
                            for($i = 0; $i < $rangecount; $i++)
                            {
                                echo "\r\n--$boundary\r\n";
                                echo "Content-Type: " . $this->mime_type . "\r\n";
                                echo "Content-Range: bytes " . $firsts[$i] . "-" . $lasts[$i] . "/$data_size\r\n\r\n";
                                fseek($fh, $firsts[$i], SEEK_SET);
                                echo fread($fh, $lasts[$i] - $firsts[$i]);
                            }
                            fclose($fh);
                            echo "\r\n--$boundary--\r\n";
                        }
                        else
                        {
                            header("Content-Length: " . ($lasts[0] - $firsts[0]));
                            header("Content-Range: bytes " . $firsts[0] . "-" . $lasts[0] . "/$data_size");
                            header("Content-Type: " . $this->mime_type . "");
                            $fh = fopen($this->lfsobj->getFilePath(), 'r');
                            while(ob_get_level())
                            {
                                ob_end_clean();
                            }
                            flush();
                            fseek($fh, $firsts[0], SEEK_SET);
                            echo fread($fh, $lasts[0] - $firsts[0]);
                            fclose($fh);
                        }
                    }
                    else
                    {
                        header("Accept-Ranges: bytes");
                        header("Content-Length: $data_size");
                        header("Content-Type: " . $this->mime_type);
                        while(ob_get_level())
                        {
                            ob_end_clean();
                        }
                        flush();
                        readfile($this->lfsobj->getFilePath());
                    }
                }
                else
                {
                    header("Accept-Ranges: bytes");
                    header("Content-Length: $data_size");
                    header("Content-Type: " . $this->mime_type);
                    while(ob_get_level())
                    {
                        ob_end_clean();
                    }
                    flush();
                    readfile($this->lfsobj->getFilePath());
                }
            }
        }
        else
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Malformed uuid";
            $this->Render();
        }
    }






    #[NoReturn]
    #[Post(
        path       : "/api/v2/lfs",
        operationId: "[POST]/api/v2/lfs",
        description: "",
        summary    : "LFS",
        tags       : [
            "LFS",
        ],
        responses  : [
            new Response(
                response   : 200,
                description: ""
            )
        ],
        deprecated : false,
    )]
    public function Post(): void
    {
        $this->Model = new LFSModel();


        if($this->file_id == null)
        {
            $this->Model = new APIModel();
            $this->Model->ResponseCode = 400;
            $this->Model->ErrorText = "Missing uuid";
            $this->Render();
        }

        if(preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $this->file_id))
        {
            if($this->lfsobj->exists())
            {
                $this->Model = new APIModel();
                $this->Model->ResponseCode = 409;
                $this->Model->ErrorText = "You cannot overwrite LFS objects";
                $this->Render();
            }

            if(!$this->lfsobj->isWriteable())
            {
                $this->Model = new APIModel();
                $this->Model->ResponseCode = 403;
                $this->Model->ErrorText = "You didn't create the node associated with this object";
                $this->Render();
            }

            move_uploaded_file($_FILES["file"]["tmp_name"], $this->lfsobj->getFilePath());

            $this->Model->FileID = $this->file_id;
            $this->Model->FileHash = $this->file_hash;
            $this->Render();
        }
        else
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Malformed uuid";
            $this->Render();
        }
    }
}
