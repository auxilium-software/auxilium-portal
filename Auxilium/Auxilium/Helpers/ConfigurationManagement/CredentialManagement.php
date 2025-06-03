<?php

namespace Auxilium\Helpers\ConfigurationManagement;

use Auxilium\Utilities\Security;

class CredentialManagement
{
    private string $CredentialsFileLocation;
    private array $Variables;
    public function __construct(bool $newInstance = false, array $newVariables = [])
    {
        $this->CredentialsFileLocation = __DIR__ . '/../../../Configuration/Configuration/Credentials.php';
        if($newInstance)
        {
            $jwtKeypair = sodium_crypto_sign_keypair();
            $localOnlyApiKey = base64_encode(Security::GeneratePseudoRandomBytes(length: 24));
            $urlMetadataJwtSecret = base64_encode(Security::GeneratePseudoRandomBytes(length: 48));
            $jwtSecret = base64_encode(sodium_crypto_sign_secretkey($jwtKeypair));
            $jwtPublic = base64_encode(sodium_crypto_sign_publickey($jwtKeypair));

            $this->Variables = [
                "INSTANCE_DOMAIN_NAME"  => $newVariables['instance-domain'],
                "INSTANCE_UUID"         => $newVariables['deegraph-rootNode'],

                "INSTANCE_CREDENTIAL_SQL_HOST"      => $newVariables['mariadb-host'],
                "INSTANCE_CREDENTIAL_SQL_USERNAME"  => $newVariables['mariadb-username'],
                "INSTANCE_CREDENTIAL_SQL_PASSWORD"  => $newVariables['mariadb-password'],
                "INSTANCE_CREDENTIAL_SQL_DATABASE"  => $newVariables['mariadb-database'],

                "INSTANCE_CREDENTIAL_DDS_HOST"          => $newVariables['deegraph-host'],
                "INSTANCE_CREDENTIAL_DDS_PORT"          => $newVariables['deegraph-port'],
                "INSTANCE_CREDENTIAL_DDS_LOGIN_NODE"    => $newVariables['deegraph-loginNode'],
                "INSTANCE_CREDENTIAL_DDS_TOKEN"         => $newVariables['deegraph-token'],

                "INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY" => $localOnlyApiKey,

                "INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET"       => $urlMetadataJwtSecret,
                "INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY"    => $jwtSecret,
                "INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY"     => $jwtPublic,

                "INSTANCE_CREDENTIAL_LOCAL_IP_RANGES" => [
                    "127.0.0.0/8"
                ],

                "INSTANCE_CREDENTIAL_OPENID_SOURCES"    => [],
                "INSTANCE_CREDENTIAL_OPENID_CACHE_TIME" => 60,

                "INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS" => [
                    "primary" => [
                        "type" => "MS_APP_GRAPH",
                        "username" => "REDACTED",
                        "external_smtp_address" => "REDACTED",
                        "password" => 'REDACTED',
                        "user_guid" => "REDACTED",
                        "tenant_guid" => "REDACTED",
                        "client_guid" => "REDACTED",
                        "client_secret" => 'REDACTED'
                    ]
                ],

                "ACCEPT_SELF_SIGNED_CERTIFICATES" => false,
            ];
        }
        else
        {
            $this->Variables = [
                "INSTANCE_DOMAIN_NAME"  => INSTANCE_DOMAIN_NAME,
                "INSTANCE_UUID"         => INSTANCE_UUID,

                "INSTANCE_CREDENTIAL_SQL_HOST"      => INSTANCE_CREDENTIAL_SQL_HOST,
                "INSTANCE_CREDENTIAL_SQL_USERNAME"  => INSTANCE_CREDENTIAL_SQL_USERNAME,
                "INSTANCE_CREDENTIAL_SQL_PASSWORD"  => INSTANCE_CREDENTIAL_SQL_PASSWORD,
                "INSTANCE_CREDENTIAL_SQL_DATABASE"  => INSTANCE_CREDENTIAL_SQL_DATABASE,

                "INSTANCE_CREDENTIAL_DDS_HOST"          => INSTANCE_CREDENTIAL_DDS_HOST,
                "INSTANCE_CREDENTIAL_DDS_PORT"          => INSTANCE_CREDENTIAL_DDS_PORT,
                "INSTANCE_CREDENTIAL_DDS_LOGIN_NODE"    => INSTANCE_CREDENTIAL_DDS_LOGIN_NODE,
                "INSTANCE_CREDENTIAL_DDS_TOKEN"         => INSTANCE_CREDENTIAL_DDS_TOKEN,

                "INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY" => INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY,

                "INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET"       => INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET,
                "INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY"    => INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY,
                "INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY"     => INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY,

                "INSTANCE_CREDENTIAL_LOCAL_IP_RANGES" => INSTANCE_CREDENTIAL_LOCAL_IP_RANGES,

                "INSTANCE_CREDENTIAL_OPENID_SOURCES"    => INSTANCE_CREDENTIAL_OPENID_SOURCES,
                "INSTANCE_CREDENTIAL_OPENID_CACHE_TIME" => INSTANCE_CREDENTIAL_OPENID_CACHE_TIME,

                "INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS" => INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS,

                "ACCEPT_SELF_SIGNED_CERTIFICATES" => ACCEPT_SELF_SIGNED_CERTIFICATES,
            ];
        }
    }

    public function OverwriteVariable(string $key, string $value): void
    {
        $this->Variables[$key] = $value;
    }

    public function Write()
    {
        $ipRanges = var_export($this->Variables["INSTANCE_CREDENTIAL_LOCAL_IP_RANGES"], true);
        $openIDSources = var_export($this->Variables["INSTANCE_CREDENTIAL_OPENID_SOURCES"], true);
        $emailAccounts = var_export($this->Variables["INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS"], true);
        $acceptSelfSigned = $this->Variables["ACCEPT_SELF_SIGNED_CERTIFICATES"] ? 'true' : 'false';

        $fileContents = <<<PHP
<?php
const INSTANCE_DOMAIN_NAME  = "{$this->Variables["INSTANCE_DOMAIN_NAME"]}";
const INSTANCE_UUID         = "{$this->Variables["INSTANCE_UUID"]}";

const INSTANCE_CREDENTIAL_SQL_HOST      = "{$this->Variables["INSTANCE_CREDENTIAL_SQL_HOST"]}";
const INSTANCE_CREDENTIAL_SQL_USERNAME  = "{$this->Variables["INSTANCE_CREDENTIAL_SQL_USERNAME"]}";
const INSTANCE_CREDENTIAL_SQL_PASSWORD  = "{$this->Variables["INSTANCE_CREDENTIAL_SQL_PASSWORD"]}";
const INSTANCE_CREDENTIAL_SQL_DATABASE  = "{$this->Variables["INSTANCE_CREDENTIAL_SQL_DATABASE"]}";

const INSTANCE_CREDENTIAL_DDS_HOST          = "{$this->Variables["INSTANCE_CREDENTIAL_DDS_HOST"]}";
const INSTANCE_CREDENTIAL_DDS_PORT          = {$this->Variables["INSTANCE_CREDENTIAL_DDS_PORT"]};
const INSTANCE_CREDENTIAL_DDS_LOGIN_NODE    = "{$this->Variables["INSTANCE_CREDENTIAL_DDS_LOGIN_NODE"]}";
const INSTANCE_CREDENTIAL_DDS_TOKEN         = "{$this->Variables["INSTANCE_CREDENTIAL_DDS_TOKEN"]}";

const INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY            = "{$this->Variables["INSTANCE_CREDENTIAL_LOCAL_ONLY_API_KEY"]}";
const INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET       = "{$this->Variables["INSTANCE_CREDENTIAL_URL_METADATA_JWT_SECRET"]}";
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY    = "{$this->Variables["INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PRIVATE_KEY"]}";
const INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY     = "{$this->Variables["INSTANCE_CREDENTIAL_AUTH_JWT_EDDSA_PUBLIC_KEY"]}";
const INSTANCE_CREDENTIAL_LOCAL_IP_RANGES               = $ipRanges;

const INSTANCE_CREDENTIAL_OPENID_SOURCES    = $openIDSources;
const INSTANCE_CREDENTIAL_OPENID_CACHE_TIME = {$this->Variables["INSTANCE_CREDENTIAL_OPENID_CACHE_TIME"]};

const INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS    = $emailAccounts;

const ACCEPT_SELF_SIGNED_CERTIFICATES   = $acceptSelfSigned;

PHP;

        file_put_contents($this->CredentialsFileLocation, $fileContents);
    }
}
