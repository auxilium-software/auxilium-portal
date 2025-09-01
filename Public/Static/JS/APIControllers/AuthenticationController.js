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
                "email_address": emailAddress,
                "raw_password": rawPassword,
                "recaptcha_token": recaptchaToken
            },
            false,
        );
        if(statusCode == 200)
        {
            return payload;
        }
        else
        {
            return false;
        }
    }
}
