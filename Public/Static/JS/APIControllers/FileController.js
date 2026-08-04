class FileController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    ExtractFileIDFromAuxLFSURL(auxLFSURL)
    {
        const uuidMatch = auxLFSURL.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/);

        if (!uuidMatch) {
            console.error('Invalid auxLFS URL format');
            return false;
        }

        return uuidMatch[0];
    }

    async GetSingleFileFromAuxLFSURL(parentType, parentId, auxLFSURL)
    {
        const fileID = this.ExtractFileIDFromAuxLFSURL(auxLFSURL);

        const [statusCode, payload] = await this.#APIInstance.API_GET(
            parentType === "CASE" ? `/api/v3/cases/${parentId}/files/${fileID}` : `/api/v3/users/${parentId}/files/${fileID}`,
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
