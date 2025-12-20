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
            console.log(payload);
            return payload;
        }
        else
        {
            return false;
        }
    }
}
