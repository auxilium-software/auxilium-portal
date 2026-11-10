class AuthenticationController
{
    #APIInstance = null;

    constructor()
    {
        this.#APIInstance = new APIInteractions();
    }


    async SetPassword(token, rawPassword)
    {
        return await this.#APIInstance.API_POST(
            '/api/v3/authentication/set-initial-password',
            {
                token: token,
                passwordSha512: await HashingUtilities.SHA512(rawPassword)
            }
        );
    }


    async Login(emailAddress, rawPassword, recaptchaToken)
    {
        return await this.#APIInstance.API_POST(
            '/api/v3/authentication/login',
            {
                emailAddress: emailAddress,
                rawPassword: rawPassword,
                recaptchaToken: recaptchaToken
            },
            false
        );
    }


    async VerifyTotp(mfaSessionToken, totpCode)
    {
        return await this.#APIInstance.API_POST(
            '/api/v3/authentication/verify-totp',
            {
                mfaSessionToken: mfaSessionToken,
                totpCode: totpCode
            },
            false
        );
    }


    async VerifyRecoveryCode(mfaSessionToken, recoveryCode)
    {
        return await this.#APIInstance.API_POST(
            '/api/v3/authentication/verify-recovery-code',
            {
                mfaSessionToken: mfaSessionToken,
                recoveryCode: recoveryCode
            },
            false
        );
    }


    async ForcedPasswordChange(passwordChangeToken, newPasswordRaw)
    {
        return await this.#APIInstance.API_POST(
            '/api/v3/authentication/forced-password-change',
            {
                passwordChangeToken: passwordChangeToken,
                passwordSha512: await HashingUtilities.SHA512(newPasswordRaw)
            },
            false
        );
    }
}
