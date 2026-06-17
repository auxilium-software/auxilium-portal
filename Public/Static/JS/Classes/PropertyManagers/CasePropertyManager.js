class CasePropertyManager extends BasePropertyManager
{
    constructor(userId)
    {
        super(`/api/v3/cases/${userId}/additional_properties`);
    }


    static async editProperty(userId, propertyKey, currentContent)
    {
        const manager = new BasePropertyManager(`/api/v3/cases/${userId}/additional_properties`);
        return manager.editProperty(propertyKey, currentContent);
    }

    static async deleteProperty(userId, propertyKey, displayName)
    {
        const manager = new BasePropertyManager(`/api/v3/cases/${userId}/additional_properties`);
        return manager.deleteProperty(propertyKey, displayName);
    }

    static async editEnumProperty(caseId, propertyKey, currentValueId, enumTypeId)
    {
        const manager = new BasePropertyManager(`/api/v3/cases/${caseId}/additional_properties`);
        return manager.editEnumProperty(propertyKey, currentValueId, enumTypeId);
    }
}
