class MessageController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetSingleMessageFromAuxMSGURL(caseID, auxMSGURL)
    {
        const uuidMatch = auxMSGURL.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/);

        if (!uuidMatch) {
            console.error('Invalid auxMSG URL format');
            return false;
        }

        const fileID = uuidMatch[0];

        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/cases/${caseID}/messages/${fileID}`,
            true,
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
}
