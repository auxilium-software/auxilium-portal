DROP TABLE IF EXISTS email_verification_codes;

CREATE TABLE email_verification_codes
(
    verification_code VARCHAR(64)                        NOT NULL,
    email_address     VARCHAR(1024)                      NOT NULL,
    user_uuid         VARCHAR(64)                        NOT NULL,
    send_timestamp    DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    INDEX (verification_code),
    INDEX (user_uuid)
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;

DROP TABLE IF EXISTS form_persistence_data;

CREATE TABLE form_persistence_data
(
    form_key         VARCHAR(256),
    persistence_data LONGTEXT,
    PRIMARY KEY (form_key)
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;

DROP TABLE IF EXISTS portal_sessions;

CREATE TABLE portal_sessions
(
    session_uuid    VARCHAR(64)   NOT NULL UNIQUE,
    session_key     VARCHAR(256),
    user_uuid       VARCHAR(64)   NOT NULL,
    unique_sub      VARCHAR(2048) NOT NULL,
    ip_address      VARCHAR(256),
    active          BOOLEAN  DEFAULT true,
    start_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (session_uuid),
    INDEX (session_key)
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;

DROP TABLE IF EXISTS standard_logins;

CREATE TABLE standard_logins
(
    user_uuid          VARCHAR(64)                        NOT NULL,
    email_address      VARCHAR(1024)                      NOT NULL UNIQUE,
    password           VARCHAR(256)                       NOT NULL,
    security_rules     LONGTEXT,
    creation_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (user_uuid),
    INDEX (email_address)
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;

DROP TABLE IF EXISTS oauth_logins;

CREATE TABLE oauth_logins
(
    user_uuid          VARCHAR(64)   NOT NULL,
    unique_sub         VARCHAR(2048) NOT NULL,
    security_rules     LONGTEXT               DEFAULT NULL,
    creation_timestamp DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;

DROP TABLE IF EXISTS totp_secrets;

CREATE TABLE totp_secrets
(
    totp_secret        VARCHAR(256)                       NOT NULL,
    user_uuid          VARCHAR(64)                        NOT NULL,
    device_uuid        VARCHAR(64)                        NOT NULL,
    device_name        VARCHAR(2048),
    creation_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    INDEX (user_uuid)
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;

DROP TABLE IF EXISTS totp_used_codes;

CREATE TABLE totp_used_codes
(
    totp_code     VARCHAR(16)                        NOT NULL,
    device_uuid   VARCHAR(64)                        NOT NULL,
    use_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    INDEX (totp_code),
    INDEX (device_uuid)
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;

DROP TABLE IF EXISTS invite_codes;

CREATE TABLE invite_codes
(
    invite_code VARCHAR(256) NOT NULL,
    invite_rule LONGTEXT     NOT NULL,
    INDEX (invite_code)
) DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;



