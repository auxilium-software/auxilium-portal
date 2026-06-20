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
        const pageSize = 1000;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/wemwbs?page=${pageNumber}&pageSize=${pageSize}`
            );
            if(statusCode === 200)
            {
                for (const temp of payload['assessments'])
                {
                    allAssessments.push(temp);
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


        return allAssessments;
    }
}
