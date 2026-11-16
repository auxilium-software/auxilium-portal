class DataEnumeratorController
{
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    // ==================================================
    // DATA ENUMERATORS

    async GetAllEnumerators(includeInactive)
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/data-enumerators/all?includeInactive=` + (includeInactive ? 'true' : 'false')
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async CreateEnumerator(enumName, enumDescription)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/data-enumerators/`,
            { name: enumName, description: enumDescription }
        );
        if (statusCode === 201) return payload;
        return false;
    }

    async UpdateEnumerator(enumId, enumName, enumDescription)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PATCH(
            `/api/v3/data-enumerators/${enumId}`,
            { name: enumName, description: enumDescription }
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async SetEnumeratorActive(enumId, isActive)
    {
        const [statusCode, _] = await this.#APIInstance.API_PATCH(
            `/api/v3/data-enumerators/${enumId}/active`,
            { isActive: isActive }
        );
        return statusCode === 204;
    }

    // ==================================================
    // DATA ENUMERATOR VALUES

    async GetValues(enumId, includeInactive)
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/data-enumerators/${enumId}/values?includeInactive=` + (includeInactive ? 'true' : 'false')
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async CreateValue(enumId, displayName, colourHex, sortOrder)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/data-enumerators/${enumId}/values`,
            { displayName: displayName, colourHex: colourHex, sortOrder: sortOrder }
        );
        if (statusCode === 201) return payload;
        return false;
    }

    async UpdateValue(enumId, valueId, displayName, colourHex)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PATCH(
            `/api/v3/data-enumerators/${enumId}/values/${valueId}`,
            { displayName: displayName, colourHex: colourHex }
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async SetValueActive(enumId, valueId, isActive)
    {
        const [statusCode, _] = await this.#APIInstance.API_PATCH(
            `/api/v3/data-enumerators/${enumId}/values/${valueId}/active`,
            { isActive: isActive }
        );
        return statusCode === 204;
    }

    async ReorderValues(enumId, orderedValueIds)
    {
        const [statusCode, _] = await this.#APIInstance.API_PATCH(
            `/api/v3/data-enumerators/${enumId}/values/reorder`,
            { orderedValueIds: orderedValueIds }
        );
        return statusCode === 204;
    }

    // ==================================================
    // DATA ENUMERATOR TRANSLATIONS

    async GetEnumeratorTranslations(enumId)
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/data-enumerators/${enumId}/translations`
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async CreateEnumeratorTranslation(enumId, languageCode, translation)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/data-enumerators/${enumId}/translations`,
            { languageCode: languageCode, translation: translation }
        );
        if (statusCode === 201) return payload;
        return false;
    }

    async UpdateEnumeratorTranslation(enumId, translationId, languageCode, translation)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PATCH(
            `/api/v3/data-enumerators/${enumId}/translations/${translationId}`,
            { languageCode: languageCode, translation: translation }
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async DeleteEnumeratorTranslation(enumId, translationId)
    {
        const [statusCode, _] = await this.#APIInstance.API_DELETE(
            `/api/v3/data-enumerators/${enumId}/translations/${translationId}`
        );
        return statusCode === 204;
    }

    // ==================================================
    // DATA ENUMERATOR VALUE TRANSLATIONS

    async GetTranslations(enumId, enumValueId)
    {
        const [statusCode, payload] = await this.#APIInstance.API_GET(
            `/api/v3/data-enumerators/${enumId}/values/${enumValueId}/translations`
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async CreateTranslation(enumId, enumValueId, languageCode, translation)
    {
        const [statusCode, payload] = await this.#APIInstance.API_POST(
            `/api/v3/data-enumerators/${enumId}/values/${enumValueId}/translations`,
            { languageCode: languageCode, translation: translation }
        );
        if (statusCode === 201) return payload;
        return false;
    }

    async UpdateTranslation(enumId, enumValueId, enumValueTranslationId, languageCode, translation)
    {
        const [statusCode, payload] = await this.#APIInstance.API_PATCH(
            `/api/v3/data-enumerators/${enumId}/values/${enumValueId}/translations/${enumValueTranslationId}`,
            { languageCode: languageCode, translation: translation }
        );
        if (statusCode === 200) return payload;
        return false;
    }

    async DeleteTranslation(enumId, enumValueId, enumValueTranslationId)
    {
        const [statusCode, _] = await this.#APIInstance.API_DELETE(
            `/api/v3/data-enumerators/${enumId}/values/${enumValueId}/translations/${enumValueTranslationId}`
        );
        return statusCode === 204;
    }
}
