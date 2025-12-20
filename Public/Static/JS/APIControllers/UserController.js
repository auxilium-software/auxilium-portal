class UserController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetAllUsers_DontDoThisLol()
    {
        let allUsers = [];

        let pageNumber = 1;
        const perPage = 1000;

        while (true)
        {
            const [statusCode, payload] = await this.#APIInstance.API_GET(
                `/api/v3/users?page=${pageNumber}&per_page=${perPage}`,
                true,
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

    async GetSingleUser(userID)
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/users/${userID}`,
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
