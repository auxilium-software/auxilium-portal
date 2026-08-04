class InfiniteScroller
{
    #loadingIndicator;
    #target;
    #currentPage = 1;
    #isLoading = false;
    #hasMoreData = true;
    #endpoint;
    #params;
    #compact;
    #renderFunction;
    #totalItems = 0;
    #apiClient;

    constructor(config)
    {
        if (!config.endpoint || !config.target)
        {
            throw new Error('Endpoint and target are required');
        }

        this.#endpoint = config.endpoint;
        this.#target = config.target;
        this.#params = config.params || {};
        this.#renderFunction = config.renderItem || this.#defaultRenderItem.bind(this);
        this.pageSize = config.pageSize || 8;
        this.dataField = config.dataField;

        this.#apiClient = new APIInteractions();

        this.#spawnLoadingIndicator();

        this.#loadData();

        this.#setupScrollListener();
    }

    #spawnLoadingIndicator()
    {
        this.#loadingIndicator = document.createElement('div');
        this.#loadingIndicator.classList.add('logical-box', 'loading-indicator');

        const indicatorBox = document.createElement('div');
        indicatorBox.classList.add('loading-placeholder', 'large-spacer');

        const loadingText = document.createElement('span');
        loadingText.textContent = 'Loading...';
        indicatorBox.appendChild(loadingText);

        this.#loadingIndicator.appendChild(indicatorBox);
        this.#target.appendChild(this.#loadingIndicator);

        this.#loadingIndicator.style.display = 'none';
    }

    #defaultRenderItem(item)
    {
        const div = document.createElement('div');
        div.classList.add('item-container');

        if (this.#compact)
        {
            div.classList.add('compact-view');
            div.innerHTML = `
                <div class="path-name-view">
                    <span class="item-title">${item.title || item.name || item.id}</span>
                    <span class="item-meta">${item.type || 'Item'}</span>
                </div>
            `;
        }
        else
        {
            div.classList.add('inline-node-view');
            div.innerHTML = `
                <div class="node-content">
                    <h3>${item.title || item.name || item.id}</h3>
                    <p>${item.description || ''}</p>
                    <div class="node-meta">
                        <span>Type: ${item.type || 'Unknown'}</span>
                        <span>Created: ${item.created_at || 'N/A'}</span>
                    </div>
                </div>
            `;
        }

        return div;
    }

    async #loadData()
    {
        if (this.#isLoading || !this.#hasMoreData)
        {
            return;
        }

        this.#isLoading = true;
        this.#loadingIndicator.style.display = 'block';

        try
        {
            const queryParams = new URLSearchParams({
                ...this.#params,
                page: this.#currentPage,
                pageSize: this.pageSize,
            });

            const fullEndpoint = `${this.#endpoint}?${queryParams}`;

            const [status, data] = await this.#apiClient.API_GET(fullEndpoint);
            if (status !== 200)
            {
                throw new Error(data || 'Request failed');
            }


            const items = data[this.dataField];
            const totalPages = data.total_pages || data.pages || data.meta?.total_pages;
            const totalItems = data.total || data.meta?.total;

            if (data.has_more !== undefined)
            {
                this.#hasMoreData = data.has_more;
            }
            else if (data.next === null || data.next === false)
            {
                this.#hasMoreData = false;
            }

            items.forEach(item =>
            {
                const element = this.#renderFunction(item);
                this.#target.insertBefore(element, this.#loadingIndicator);
                this.#totalItems++;
            });

            if (items.length < this.pageSize)
            {
                this.#hasMoreData = false;
            }

            if (totalPages && this.#currentPage >= totalPages)
            {
                this.#hasMoreData = false;
            }

            if (totalItems && this.#totalItems >= totalItems)
            {
                this.#hasMoreData = false;
            }

            this.#currentPage++;

            this.#checkLoadMore();

        } catch (error)
        {
            console.error('Error loading data:', error);
            this.#showError(error.message || 'Failed to load data');
        } finally
        {
            this.#isLoading = false;
            this.#loadingIndicator.style.display = 'none';
        }
    }

    #setupScrollListener()
    {
        let scrollTimeout;
        const scrollHandler = () =>
        {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() =>
            {
                this.#checkLoadMore();
            }, 150);
        };

        window.addEventListener('scroll', scrollHandler);

        this.scrollHandler = scrollHandler;

        if ('ResizeObserver' in window)
        {
            const resizeObserver = new ResizeObserver(() =>
            {
                this.#checkLoadMore();
            });
            resizeObserver.observe(this.#target);

            this.resizeObserver = resizeObserver;
        }
    }

    #checkLoadMore()
    {
        if (this.#isLoading || !this.#hasMoreData)
        {
            return;
        }

        const bounds = this.#target.getBoundingClientRect();
        const triggerHeight = window.innerHeight * 2;

        if (bounds.bottom < triggerHeight)
        {
            this.#loadData();
        }
    }

    #showError(message)
    {
        const errorDiv = document.createElement('div');
        errorDiv.classList.add('error-message', 'logical-box');
        errorDiv.innerHTML = `
            <div class="error-content">
                <strong>Error:</strong> ${message}
                <button onclick="this.parentElement.parentElement.remove()">Dismiss</button>
            </div>
        `;
        this.#target.insertBefore(errorDiv, this.#loadingIndicator);
    }

    refresh()
    {
        const items = this.#target.querySelectorAll('.item-container, .path-name-view, .inline-node-view, .case-item, .error-message');
        items.forEach(item => item.remove());

        // reset state
        this.#currentPage = 1;
        this.#hasMoreData = true;
        this.#totalItems = 0;
        this.#isLoading = false;

        this.#loadData();
    }

    updateParams(newParams)
    {
        this.#params = {...this.#params, ...newParams};
        this.refresh();
    }
}
