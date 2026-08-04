class UserController
{
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetAllUsers_DontDoThisLol()
    {
        let allUsers = [];

        let pageNumber = 1;
        const pageSize = 1000;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/users?page=${pageNumber}&pageSize=${pageSize}`
            );
            if(statusCode === 200)
            {
                for (const temp of payload['users'])
                {
                    allUsers.push(temp);
                }
                pageNumber ++;
                if(payload['hasMore'] !== true)
                {
                    break;
                }
            }
            else
            {
                return false;
            }
        }


        return allUsers;
    }

    async GetUsersPage(page = 1, pageSize = 100, sortBy = 'fullName', sortOrder = 'asc', search = '')
    {
        let url = `/api/v3/users?page=${page}&pageSize=${pageSize}&sortBy=${sortBy}&sortOrder=${sortOrder}`;

        if (search) {
            url += `&search=${encodeURIComponent(search)}`;
        }

        const [statusCode, payload] = await this.#APIInstance.API_GET(
            url,
            true,
        );
        return statusCode === 200 ? payload : false;
    }

    async GetSingleUser(userID)
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/users/${userID}`,
            true,
        );
        return statusCode === 200 ? payload : false;
    }

    async GetUserStatistics(period = 'week') {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/users/statistics?period=${period}`,
            true
        );
        return statusCode === 200 ? payload : false;
    }

    async UpdateUser(userID, data, totpCode)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PATCH(
            `/api/v3/users/${userID}`, data, true, totpCode
        );
        if (statusCode === 204)
        {
            return await this.GetSingleUser(userID);
        }
        return false;
    }

    async UpdateUserPermissions(userID, data, totpCode)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PATCH(
            `/api/v3/users/${userID}/permissions`,
            data,
            true,
            totpCode
        );
        return statusCode === 200 ? payload : false;
    }

    async SetUserBlocked(userID, blocked, totpCode) {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/users/${userID}/block`,
            { blocked: blocked },
            true,
            totpCode
        );
        return statusCode === 204 ? payload : false;
    }

    async ForcePasswordReset(userID, totpCode)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/users/${userID}/force-password-reset`,
            {},
            true,
            totpCode
        );
        return statusCode === 204 ? payload : false;
    }

    async TerminateSessions(userID, totpCode)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/users/${userID}/terminate-sessions`,
            {},
            true,
            totpCode
        );
        return statusCode === 204 ? payload : false;
    }

    async SendPasswordResetEmail(userID, totpCode)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/users/${userID}/send-password-reset`,
            {},
            true,
            totpCode
        );
        return statusCode === 204 ? payload : false;
    }

    async CreateUser(
        fullName,
        emailAddress,
        language,
        totpCode
    ) {

        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/users`,
            {
                "fullName": fullName,
                "emailAddress": emailAddress,
                "languagePreference": language,
            },
            true,
            totpCode
        );
        return statusCode === 201 ? payload : false;
    }

    async DeleteUser(userID, totpCode) {

        const [statusCode, payload] = await this.#APIInstance.API_DELETE(
            `/api/v3/users/${userID}`,
            true,
            totpCode
        );
        return statusCode === 204 ? payload : false;
    }

    async GetUserAuditLog(userID) {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/users/${userID}/audit-log`,
            true
        );
        return statusCode === 200 ? payload : false;
    }

    // =========================================================================
    // TOTP Setup (for the current admin's own account)
    // =========================================================================

    async GetTotpStatus() {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/me/totp/status`,
            true
        );
        return statusCode === 200 ? payload : false;
    }

    async SetupTotp() {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/me/totp/setup`,
            {},
            true
        );
        return statusCode === 200 ? payload : false;
    }

    async EnableTotp(code) {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/me/totp/enable`,
            { code: code },
            true
        );
        return statusCode === 200 ? payload : false;
    }

    async DisableTotp(code) {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/me/totp/disable`,
            { code: code },
            true
        );
        return statusCode === 200 ? payload : false;
    }

    async GetRecoveryCodeCount() {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/me/totp/recovery-codes/count`,
            true
        );
        return statusCode === 200 ? payload : false;
    }

    async RegenerateRecoveryCodes(totpCode) {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/me/totp/recovery-codes/regenerate`,
            { code: totpCode },
            true
        );
        return statusCode === 200 ? payload : false;
    }
}
