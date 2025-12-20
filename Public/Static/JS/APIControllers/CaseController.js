class CaseController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetAllCases_DontDoThisLol()
    {
        let allCases = [];

        let pageNumber = 1;
        const perPage = 100;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases?page=${pageNumber}&per_page=${perPage}`,
                true,
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
        const perPage = 100;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases/mine?page=${pageNumber}&per_page=${perPage}`,
                true,
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
        const perPage = 100;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases/assigned?page=${pageNumber}&per_page=${perPage}`,
                true,
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
}
