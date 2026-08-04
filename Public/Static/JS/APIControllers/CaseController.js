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
        const pageSize = 500;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases/mine?page=${pageNumber}&pageSize=${pageSize}`,
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
        const pageSize = 500;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/cases/assigned?page=${pageNumber}&pageSize=${pageSize}`,
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
