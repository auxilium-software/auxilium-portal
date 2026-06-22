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
    async GetApiCpuUsageMetrics()
    {
        const [statusCode, response] = await this.#APIInstance.API_GET('/api/v3/system-metrics/api-cpu-usage');
        return response;
    }
    async GetApiRamUsageMetrics()
    {
        const [statusCode, response] = await this.#APIInstance.API_GET('/api/v3/system-metrics/api-ram-usage');
        return response;
    }
    async GetTaskRunnerCpuUsageMetrics()
    {
        const [statusCode, response] = await this.#APIInstance.API_GET('/api/v3/system-metrics/task-runner-cpu-usage');
        return response;
    }
    async GetTaskRunnerRamUsageMetrics()
    {
        const [statusCode, response] = await this.#APIInstance.API_GET('/api/v3/system-metrics/task-runner-ram-usage');
        return response;
    }
}
