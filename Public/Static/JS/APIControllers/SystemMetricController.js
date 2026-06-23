class SystemMetricController {
    #APIInstance = null;

    constructor() {
        this.#APIInstance = new APIInteractions();
    }

    async GetMetricSeries(metricKey, label = null)
    {
        let url = `/api/v3/system-metrics/series/${encodeURIComponent(metricKey)}`;
        if (label !== null && label !== undefined)
        {
            url += `?label=${encodeURIComponent(label)}`;
        }

        const [statusCode, response] = await this.#APIInstance.API_GET(url);
        return response;
    }

    async GetLatestSnapshot()
    {
        const [statusCode, response] = await this.#APIInstance.API_GET('/api/v3/system-metrics/latest');
        return response;
    }
}
