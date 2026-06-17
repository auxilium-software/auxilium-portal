class SystemMetricController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }


    async GetDatabaseSizeMetrics()
    {
        const [statusCode, response] = await this.#APIInstance.API_GET('/api/v3/system-metrics/database-size');
        return response;
    }
    async GetLfsSizeMetrics()
    {
        const [statusCode, response] = await this.#APIInstance.API_GET('/api/v3/system-metrics/lfs-size');
        return response;
    }
}
