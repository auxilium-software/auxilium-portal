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
                `/api/v3/cases/all?page=${pageNumber}&per_page=${perPage}`,
                true,
            );
            if(statusCode === 200)
            {
                for (const temp of payload['data'])
                {
                    allCases.push(temp);
                }
                pageNumber ++;
                if(payload['has_more'] !== true)
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
