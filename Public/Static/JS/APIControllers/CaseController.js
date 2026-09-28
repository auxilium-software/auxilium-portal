class CaseController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetAllCases_DontDoThisLol()
    {
        let allCases = [];

        let pageNumber = 1;
        const pageSize = 1000;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases?page=${pageNumber}&pageSize=${pageSize}`,
            );
            if(statusCode === 200)
            {
                for (const temp of payload['cases'])
                {
                    allCases.push(temp);
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

        return allCases;
    }

    async GetAllMyCases_DontDoThisLol()
    {
        let allCases = [];

        let pageNumber = 1;
        const pageSize = 500;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases/mine?page=${pageNumber}&pageSize=${pageSize}`,
            );
            if(statusCode === 200)
            {
                for (const temp of payload['cases'])
                {
                    allCases.push(temp);
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

        return allCases;
    }

    async GetAllAssignedCases_DontDoThisLol()
    {
        let allCases = [];

        let pageNumber = 1;
        const pageSize = 500;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases/assigned?page=${pageNumber}&pageSize=${pageSize}`,
            );
            if(statusCode === 200)
            {
                for (const temp of payload['cases'])
                {
                    allCases.push(temp);
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

        return allCases;
    }

    async GetSingleCase(caseID)
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/cases/${caseID}`,
        );
        return statusCode === 200 ? payload : false;
    }

    async GetCasesPage(page = 1, pageSize = 50, sortBy = 'createdAt', sortOrder = 'desc', search = '', status = '')
    {
        const parameters = new URLSearchParams({
            page: page,
            pageSize: pageSize,
            sortBy: sortBy,
            sortOrder: sortOrder,
        });

        if (search) parameters.set('search', search);
        if (status) parameters.set('status', status);

        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/cases?${parameters.toString()}`,
        );
        return statusCode === 200 ? payload : false;
    }

    async PreviewCaseMerge(survivorCaseID, duplicateCaseID)
    {
        return await this.#APIInstance.API_POST(
            `/api/v3/cases/${survivorCaseID}/merge/preview`,
            { duplicateCaseId: duplicateCaseID },
        );
    }

    async MergeCases(survivorCaseID, duplicateCaseID, fingerprint, fieldChoices, justification, totpCode)
    {
        return await this.#APIInstance.API_POST(
            `/api/v3/cases/${survivorCaseID}/merge`,
            {
                duplicateCaseId: duplicateCaseID,
                fingerprint: fingerprint,
                fieldChoices: fieldChoices,
                justification: justification,
            },
            totpCode,
        );
    }
}
