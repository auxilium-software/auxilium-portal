<?php

namespace Auxilium\Utilities;

use Exception;
use RuntimeException;

class CacheUtilities
{
    public static string $CacheDirectory = __DIR__ . '/../../LocalStorage/Cache';

    public static function SetFormData(string $formInstanceID, string $key, string $value): void
    {
        $formData = self::GetFormData($formInstanceID);
        $formData['Data'][$key] = $value;

        file_put_contents(
            filename: self::$CacheDirectory . "/FormData/$formInstanceID.json",
            data    : json_encode(
                          $formData,
                          JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
                      ),
        );
    }

    public static function GetFormData(string $formInstanceID): array
    {
        if(!UUIDUtilities::IsValid($formInstanceID))
        {
            throw new Exception("Invalid form ID format");
        }

        if(!self::DoesFormExistYet($formInstanceID))
        {
            throw new Exception("Form does not exist: $formInstanceID");
        }

        $filePath = self::$CacheDirectory . "/FormData/$formInstanceID.json";
        $fileContents = file_get_contents($filePath);

        if($fileContents === false)
        {
            throw new Exception("Could not read form data file");
        }

        return json_decode($fileContents, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function DoesFormExistYet(string $formInstanceID): bool
    {
        $filePath = self::$CacheDirectory . "/FormData/$formInstanceID.json";
        return file_exists($filePath);
    }

    public static function UpdateFormData(string $formInstanceID, array $data): void
    {
        $formData = self::GetFormData($formInstanceID);

        foreach($data as $key => $value)
        {
            // Skip system fields
            if(in_array($key, ['FormInstanceID', 'submit', 'action', 'jumpToPage']))
            {
                continue;
            }
            $formData['Data'][$key] = $value;
        }

        file_put_contents(
            filename: self::$CacheDirectory . "/FormData/$formInstanceID.json",
            data    : json_encode(
                          $formData,
                          JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
                      ),
        );
    }

    public static function CleanupOldForms(int $maxAgeHours = 24): void
    {
        $formDataDir = self::$CacheDirectory . "/FormData";
        if(!is_dir($formDataDir))
        {
            return;
        }

        $files = glob($formDataDir . "/*.json");
        $cutoffTime = time() - ($maxAgeHours * 3600);

        foreach($files as $file)
        {
            if(filemtime($file) < $cutoffTime)
            {
                unlink($file);
            }
        }
    }

    // Updated to accept both int and string (for 'review' page)

    public static function SetCurrentPage(string $formInstanceID, int $pageNumber): void
    {
        // Deprecated - use SetCurrentPageIndex instead
        self::SetCurrentPageIndex($formInstanceID, $pageNumber);
    }

    public static function SetCurrentPageIndex(string $formInstanceID, int|string $pageIndex): void
    {
        $formData = self::GetFormData($formInstanceID);
        $formData['CurrentPageIndex'] = $pageIndex;

        file_put_contents(
            filename: self::$CacheDirectory . "/FormData/$formInstanceID.json",
            data    : json_encode(
                          $formData,
                          JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
                      ),
        );
    }

    public static function MarkFormAsComplete(string $formInstanceID): void
    {
        unlink(filename: self::$CacheDirectory . "/FormData/$formInstanceID.json");
    }

    public static function CreateNewForm(string $formSpecName, bool $requireAuth): string
    {
        $userID = "*";
        if($requireAuth)
        {
            SecurityUtilities::RequireLogin();
            $userID = JWTUtilities::GetJwtInfo()->ID;
        }


        $formDataTemplate = [
            "FormSpecID" => $formSpecName,
            "UserID" => $userID,
            "Data" => [],
            "CurrentPageIndex" => 0,  // Track actual page index
            "Status" => "in_progress",
            "CreatedAt" => date('Y-m-d H:i:s'),
        ];

        $formInstanceID = UUIDUtilities::CreateV4();

        // Ensure directory exists
        $formDataDir = self::$CacheDirectory . "/FormData";
        if(!is_dir($formDataDir))
        {
            if(!mkdir($formDataDir, 0755, true) && !is_dir($formDataDir))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $formDataDir));
            }
        }

        file_put_contents(
            filename: self::$CacheDirectory . "/FormData/$formInstanceID.json",
            data    : json_encode(
                          $formDataTemplate,
                          JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
                      ),
        );

        return $formInstanceID;
    }
}