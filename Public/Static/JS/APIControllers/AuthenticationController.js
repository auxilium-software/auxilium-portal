class AuthenticationController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }


    async SetPassword(token, rawPassword)
    {
        const passwordSha512 = await HashingUtilities.SHA512(rawPassword);

        return await this.#APIInstance.API_POST('/api/v3/authentication/set-initial-password', {
            token: token,
            passwordSha512: passwordSha512
        });
    }


    async Login(emailAddress, rawPassword, recaptchaToken)
    {
        return await this.#APIInstance.API_POST(
            "/api/v3/authentication/login",
            {
                "emailAddress": emailAddress,
                "rawPassword": rawPassword,
                // "passwordSha512": await HashingUtilities.SHA512(rawPassword),
                "recaptchaToken": recaptchaToken
            },
            false,
        );
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



    async VerifyRecoveryCode(mfaSessionToken, recoveryCode)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            "/api/v3/authentication/verify-recovery-code",
            {
                mfaSessionToken: mfaSessionToken,
                recoveryCode: recoveryCode
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
