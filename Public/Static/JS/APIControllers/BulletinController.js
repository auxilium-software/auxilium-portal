
class BulletinController
{
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async Admin_GetAllBulletins()
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/system-bulletin/all`,
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

    async Admin_CreateBulletin(
        severity,
        title, content,
        isDismissible,
        startsAt, endsAt,
        targetAudience, specificUserId
    )
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/system-bulletin`,
            {
                severity: severity,
                title: title,
                content: content,
                isDismissible: isDismissible,
                startsAt: startsAt,
                endsAt: endsAt,
                targetAudience: targetAudience,
                specificUserId: specificUserId,
            },
            true
        );
    }
}
