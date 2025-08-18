<?php

namespace Auxilium\Helpers\PDF;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphServerConnection;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use Darksparrow\DeegraphInteractions\QueryBuilder\QueryBuilder;
use Fpdf\Fpdf;
use ICalendarOrg\ZCiCal;
use JetBrains\PhpStorm\NoReturn;

class PDFGeneration
{
    private FPDF $pdf;
    public function __construct(
        string $title
    )
    {
        define('FPDF_FONTPATH', __DIR__ . '/../../../Public/Static/FPDF/');

        $this->pdf = new FPDF();

        // fonts
        $this->pdf->AddFont(family: 'AtkinsonHyperlegible-Regular',     style: '',      file: 'Atkinson-Hyperlegible-Regular-102.php');
        $this->pdf->AddFont(family: 'AtkinsonHyperlegible-Italic',      style: 'I',     file: 'Atkinson-Hyperlegible-Italic-102.php');
        $this->pdf->AddFont(family: 'AtkinsonHyperlegible-Bold',        style: 'B',     file: 'Atkinson-Hyperlegible-Bold-102.php');
        $this->pdf->AddFont(family: 'AtkinsonHyperlegible-BoldItalic',  style: 'BI',    file: 'Atkinson-Hyperlegible-BoldItalic-102.php');
        $this->pdf->AddFont(family: 'OCRAbyBT-Regular',                 style: '',      file: 'ocr-a.php');

        //
        $this->pdf->AddPage();

        // header
        $this->pdf->Image(__DIR__ . '/../../../Public/Static/Favicons/Black.png',10,6,30);
        $this->SetFont_Heading();
        $this->pdf->Cell(80);
        $this->pdf->Cell(80,10, $title,1,0,'C');
        $this->pdf->Ln(25);

        //
        $this->SetFont_Regular();
    }

    private function SetFont_Regular(): void
    {
        $this->pdf->SetFont(family: 'AtkinsonHyperlegible-Regular', style: '', size: 12);
    }
    private function SetFontItalic(): void
    {
        $this->pdf->SetFont(family: 'AtkinsonHyperlegible-Italic', style: 'I', size: 12);
    }
    private function SetFont_Heading(): void
    {
        $this->pdf->SetFont(family: 'AtkinsonHyperlegible-Bold', style: 'B', size: 14);
    }
    private function SetFontBoldItalic(): void
    {
        $this->pdf->SetFont(family: 'AtkinsonHyperlegible-BoldItalic', style: 'BI', size: 12);
    }
    private function SetFont_OCR(): void
    {
        $this->pdf->SetFont(family: 'OCRAbyBT-Regular', style: '', size: 12);
    }




    #[NoReturn] public function Render(): void
    {
        // footer

        // render
        $this->pdf->Output();
        die();
    }








    private static function GetTextFromURL(string $url): string
    {
        $temp = explode(',', $url);
        return match ($temp[0])
        {
            "data:text/plain;base64", "data:text/calendar;base64" => base64_decode($temp[1]),
            default => $temp[1],
        };
    }
    public static function GenerateCaseOverviewPage(string $caseID): PDFGeneration
    {
        $caseData = QueryBuilder::Select()
            ->relativePaths([
                '*',
            ])
            ->from($caseID)
            ->build()
            ->runQuery(
                new UUID(Session::get_current()->getUser()->getUuid()),
                DeegraphServerConnection::GetConnection()
            );
        $beneficiariesData = QueryBuilder::Select()
            ->relativePaths([
                'clients/#',
            ])
            ->from($caseID)
            ->build()
            ->runQuery(
                new UUID(Session::get_current()->getUser()->getUuid()),
                DeegraphServerConnection::GetConnection()
            );
        $caseWorkersData = QueryBuilder::Select()
            ->relativePaths([
                'workers/#',
            ])
            ->from($caseID)
            ->build()
            ->runQuery(
                new UUID(Session::get_current()->getUser()->getUuid()),
                DeegraphServerConnection::GetConnection()
            );
        $timelineData = QueryBuilder::Select()
            ->relativePaths([
                'timeline/#',
            ])
            ->from($caseID)
            ->build()
            ->runQuery(
                new UUID(Session::get_current()->getUser()->getUuid()),
                DeegraphServerConnection::GetConnection()
            );

        $caseTitle          = self::GetTextFromURL($caseData->Rows[0]->Properties["title"]["{$caseID}/title"]);
        $caseDescription    = self::GetTextFromURL($caseData->Rows[0]->Properties["description"]["{$caseID}/description"]);
        $beneficiaries      = [];
        $caseWorkers        = [];
        $timeLineItems      = [];

        foreach($beneficiariesData->Rows[0]->Properties as $key=>$value)
        {
            $temp = QueryBuilder::Select()
                ->relativePaths([
                    '*',
                ])
                ->from("{$caseID}/{$key}")
                ->build()
                ->runQuery(
                    new UUID(Session::get_current()?->getUser()?->getUuid()),
                    DeegraphServerConnection::GetConnection()
                )
                ->Rows[0]
                ->Properties;
            $beneficiaries[] = [
                "Name"              => self::GetTextFromURL($temp['name']["{$caseID}/{$key}/name"]),
                "DisplayName"       => self::GetTextFromURL($temp['display_name']["{$caseID}/{$key}/display_name"]),
                "PreferredLanguage" => self::GetTextFromURL($temp['preferred_language']["{$caseID}/{$key}/preferred_language"]),
                "ContactEmail"      => self::GetTextFromURL($temp['contact_email']["{$caseID}/{$key}/contact_email"]),
            ];
        }
        if(isset($caseWorkersData->Rows))
        {
            foreach($caseWorkersData->Rows[0]->Properties as $key=>$value)
            {
                $temp = QueryBuilder::Select()
                    ->relativePaths([
                        '*',
                    ])
                    ->from("{$caseID}/{$key}")
                    ->build()
                    ->runQuery(
                        new UUID(Session::get_current()->getUser()->getUuid()),
                        DeegraphServerConnection::GetConnection()
                    )
                    ->Rows[0]
                    ->Properties;
                $caseWorkers[] = [
                    "Name"              => self::GetTextFromURL($temp['name']["{$caseID}/{$key}/name"]),
                    "DisplayName"       => self::GetTextFromURL($temp['display_name']["{$caseID}/{$key}/display_name"]),
                    "PreferredLanguage" => self::GetTextFromURL($temp['preferred_language']["{$caseID}/{$key}/preferred_language"]),
                    "ContactEmail"      => self::GetTextFromURL($temp['contact_email']["{$caseID}/{$key}/contact_email"]),
                ];
            }
        }
        if(isset($timelineData->Rows))
        {
            foreach($timelineData->Rows[0]->Properties as $key => $value)
            {
                $timeLineItems[] = self::GetTextFromURL($value["{$caseID}/{$key}"]);
            }
        }


        $timeLineItemsSimplified = [];
        foreach($timeLineItems as $timeLineItem)
        {
            $temp = new ZCiCal($timeLineItem);
            $data = $temp->getFirstChild($temp->curnode)->data;
            $dtStamp = null;
            $summary = null;
            $description = null;
            foreach($data as $t)
            {
                switch($t->name)
                {
                    case 'DTSTAMP':
                        $dtStamp = $t->values[0];
                        break;
                    case 'SUMMARY':
                        $summary = $t->values[0];
                        break;
                    case 'DESCRIPTION':
                        $description = $t->values[0];
                        break;
                }
            }
            $timeLineItemsSimplified[] = [
                "DTSTAMP"=>$dtStamp,
                "SUMMARY"=>$summary,
                "DESCRIPTION"=>$description,
            ];
        }


        $pdf = new PDFGeneration(title: 'Case Overview');
        $pdf->pdf->SetMargins(10, 10, 10);
        $pdf->pdf->Ln(5);

        // Case Title
        $pdf->SetFont_Heading();
        $pdf->pdf->Cell(0, 10, 'Case Title:', 0, 1, 'L');
        $pdf->SetFont_Regular();
        $pdf->pdf->MultiCell(0, 8, $caseTitle);
        $pdf->pdf->Ln(5);

        // Case Description
        $pdf->SetFont_Heading();
        $pdf->pdf->Cell(0, 10, 'Description:', 0, 1, 'L');
        $pdf->SetFont_Regular();
        $pdf->pdf->MultiCell(0, 8, $caseDescription);
        $pdf->pdf->Ln(10);

        // Case Workers Section
        $pdf->SetFont_Heading();
        $pdf->pdf->Cell(0, 10, 'Case Workers', 0, 1, 'C');
        $pdf->pdf->Ln(3);
        $pdf->SetFont_Regular();
        foreach ($caseWorkers as $worker) {
            $pdf->pdf->Cell(60, 8, $worker['DisplayName'], 1);
            $pdf->pdf->Cell(0, 8, $worker['ContactEmail'], 1, 1);
        }
        $pdf->pdf->Ln(10);

        // Beneficiaries Section
        $pdf->SetFont_Heading();
        $pdf->pdf->Cell(0, 10, 'Beneficiaries', 0, 1, 'C');
        $pdf->pdf->Ln(3);
        $pdf->SetFont_Regular();
        foreach ($beneficiaries as $beneficiary) {
            $pdf->pdf->Cell(60, 8, $beneficiary['DisplayName'], 1);
            $pdf->pdf->Cell(0, 8, $beneficiary['ContactEmail'], 1, 1);
        }
        $pdf->pdf->Ln(10);

        // Timeline Section
        $pdf->SetFont_Heading();
        $pdf->pdf->Cell(0, 10, 'Timeline Events', 0, 1, 'C');
        $pdf->pdf->Ln(3);
        $pdf->SetFont_Regular();
        foreach ($timeLineItemsSimplified as $eventDetails)
        {
            $pdf->pdf->MultiCell(0, 8, $eventDetails['DTSTAMP'] . ' - ' . $eventDetails['DESCRIPTION']);
            $pdf->pdf->Ln();
        }



        return $pdf;
    }
}
