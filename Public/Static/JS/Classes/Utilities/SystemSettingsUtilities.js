
class SystemSettingsUtilities
{
    static #apiInteractions = new APIInteractions();

    static async getSystemSettingByKey(systemSettingKey)
    {
        const [status, payload] = await SystemSettingsUtilities.#apiInteractions.API_GET(
            `/api/v3/system-settings/visible/${systemSettingKey}`
        );

        if (status === 200)
        {
            return payload.value;
        }

        throw new DOMException("SystemSettingsUtilities: response is not 200.");
    }
}
