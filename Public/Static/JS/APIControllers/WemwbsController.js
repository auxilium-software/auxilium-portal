class WemwbsController
{
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetAllAssessments_DontDoThisLol()
    {
        let allAssessments = [];

        let pageNumber = 1;
        const pageSize = 500;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/wemwbs?page=${pageNumber}&pageSize=${pageSize}`
            );

            if (statusCode !== 200)
            {
                return false;
            }

            for (const assessment of payload['assessments'])
            {
                allAssessments.push(assessment);
            }

            if (payload['hasMore'] !== true)
            {
                break;
            }
            pageNumber++;
        }

        return allAssessments;
    }
}
