class AuthenticationController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async Login(emailAddress, rawPassword, recaptchaToken)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            "/api/v3/authentication/login",
            {
                "emailAddress": emailAddress,
                "rawPassword": rawPassword,
                "recaptchaToken": recaptchaToken
            },
            false,
        );
        if(statusCode === 200)
        {
            return payload;
        }
        else
        {
            return false;
        }
    }



    async VerifyTotp(mfaSessionToken, totpCode) {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            "/api/v3/authentication/verify-totp",
            {
                mfaSessionToken: mfaSessionToken,
                totpCode: totpCode
            },
            false,
        );

        if (statusCode === 200)
        {
            return payload;
        }

        throw new Error('Verification failed');
    }



    async ForcedPasswordChange(passwordChangeToken, newPasswordRaw) {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            "/api/v3/authentication/forced-password-change",
            {
                passwordChangeToken: passwordChangeToken,
                passwordSha512: await HashingUtilities.SHA512(newPasswordRaw)
            },
            false,
        );

        if (statusCode === 200)
        {
            return payload;
        }

        throw new Error('Verification failed');
    }
}
