class FileController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetSingleFileFromAuxLFSURL(auxLFSURL)
    {
        const uuidMatch = auxLFSURL.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/);

        if (!uuidMatch) {
            console.error('Invalid auxLFS URL format');
            return false;
        }

        const fileID = uuidMatch[0];

        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/files/${fileID}`,
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
