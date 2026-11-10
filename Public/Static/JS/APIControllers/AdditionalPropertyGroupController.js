
class AdditionalPropertyGroupController
{
    static SUBJECTS = Object.freeze({
        CASES: 'cases',
        USERS: 'users',
    });

    #APIInstance = null;
    #basePath = null;
    #lastErrorDetail = null;

    constructor(subject)
    {
        if (!Object.values(AdditionalPropertyGroupController.SUBJECTS).includes(subject))
        {
            throw new Error(`Unknown additional property subject: ${subject}`);
        }

        this.#APIInstance = new APIInteractions();
        this.#basePath = `/api/v3/${subject}/additional_property_groups`;
    }

    static FromResourcePath(resourcePath)
    {
        const match = /^\/api\/v3\/(cases|users)\//.exec(resourcePath || '');
        return match ? new AdditionalPropertyGroupController(match[1]) : null;
    }

    get LastErrorDetail()
    {
        return this.#lastErrorDetail;
    }

    #result(statusCode, payload, expectedStatusCode)
    {
        if (statusCode === expectedStatusCode)
        {
            this.#lastErrorDetail = null;
            return payload;
        }

        this.#lastErrorDetail = (payload && typeof payload === 'object' && payload.detail) ? payload.detail : null;
        return false;
    }





    async GetGroups(locale = null)
    {
        const query = locale ? `?locale=${encodeURIComponent(locale)}` : '';
        const [statusCode, payload] = await this.#APIInstance.API_GET(`${this.#basePath}${query}`);
        const result = this.#result(statusCode, payload, 200);
        return Array.isArray(result) ? result : (result === false ? false : []);
    }

    async CreateGroup(canonicalName, translations = null)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            this.#basePath,
            {
                canonicalName: canonicalName,
                translations: translations,
            }
        );
        return this.#result(statusCode, payload, 201);
    }

    async RenameGroup(groupId, canonicalName)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PATCH(
            `${this.#basePath}/${encodeURIComponent(groupId)}`,
            {
                canonicalName: canonicalName,
            }
        );
        return this.#result(statusCode, payload, 200);
    }

    async ReorderGroups(orderedGroupIds)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PUT(
            `${this.#basePath}/order`,
            {
                orderedGroupIds: orderedGroupIds,
            }
        );
        return this.#result(statusCode, payload, 200);
    }

    async DeleteGroup(groupId)
    {
        const [statusCode, payload] = await this.#APIInstance.API_DELETE(
            `${this.#basePath}/${encodeURIComponent(groupId)}`
        );
        return this.#result(statusCode, payload, 200);
    }





    async SetTranslation(groupId, languageCode, translation)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PUT(
            `${this.#basePath}/${encodeURIComponent(groupId)}/translations/${encodeURIComponent(languageCode)}`,
            {
                translation: translation,
            }
        );
        return this.#result(statusCode, payload, 200);
    }

    async RemoveTranslation(groupId, languageCode)
    {
        const [statusCode, payload] = await this.#APIInstance.API_DELETE(
            `${this.#basePath}/${encodeURIComponent(groupId)}/translations/${encodeURIComponent(languageCode)}`
        );
        return this.#result(statusCode, payload, 200);
    }
}
