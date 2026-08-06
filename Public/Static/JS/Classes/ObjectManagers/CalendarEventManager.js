class CalendarEventManager extends BaseManager
{
    static RANGE_PADDING_DAYS_BACK = 50;
    static RANGE_PADDING_DAYS_FORWARD = 50;
    static REFETCH_EDGE_DAYS = 30;

    constructor(containerEl)
    {
        super();

        this.containerEl = containerEl;
        this.basePath = '/api/v3/calendar-events';

        this.caseUrlTemplate = containerEl.dataset.caseUrlTemplate || '/cases/{caseId}';
        this.currentUserId = containerEl.dataset.currentUserId || '';

        this.categories = [];
        this.categoryByKey = {};

        this.lastLoadedEvents = [];
        this.loadedRange = { start: null, end: null };

        this.instance = null;
        this._refetchInFlight = false;

        this.onEventsChanged = null;
        this.onManualEventClicked = null;
    }

    async init()
    {
        try
        {
            await this.fetchCategories();
        }
        catch (error)
        {
            console.error('CalendarEventManager: failed to load categories', error);
        }

        if (this.categories.length === 0)
        {
            console.warn('CalendarEventManager: no categories configured yet.');
        }

        const today = new Date();
        const start = addDays(today, -CalendarEventManager.RANGE_PADDING_DAYS_BACK);
        const end = addDays(today, CalendarEventManager.RANGE_PADDING_DAYS_FORWARD);

        let initialEvents = [];
        try
        {
            initialEvents = await this.fetchEvents(start, end);
            this.loadedRange = { start, end };
            this.lastLoadedEvents = initialEvents;
        }
        catch (error)
        {
            console.error('CalendarEventManager: failed to load initial events', error);
        }

        const navigationHandler = () => this.checkRangeAndRefetch();

        this.instance = new window.calendarJs(this.containerEl.querySelector('#calendar-widget'), {
            manualEditingEnabled: false,
            useLocalStorageForEvents: false,
            popUpNotificationsEnabled: true,
            startOfWeekDay: 0, // monday
            workingDays: [0, 1, 2, 3, 4],
            weekendDays: [5, 6],
            workingHoursStart: await SystemSettingsUtilities.getSystemSettingByKey("business.workingHours.start"),
            workingHoursEnd: await SystemSettingsUtilities.getSystemSettingByKey("business.workingHours.end"),
            data: initialEvents,
            events: {
                onEventClick: (event) => this._onEventClicked(event),
                onPreviousMonth: navigationHandler,
                onNextMonth: navigationHandler,
                onPreviousYear: navigationHandler,
                onNextYear: navigationHandler,
                onToday: navigationHandler,
                onSetDate: navigationHandler,
            },
        });
        this.containerEl.querySelector('#calendar-widget').addEventListener('contextmenu', (e) => {
            e.preventDefault();
        });
    }

    async fetchEvents(start, end)
    {
        const params = new URLSearchParams({ start: start.toISOString(), end: end.toISOString() });
        const apiEvents = await this.makeRequest('GET', `${this.basePath}?${params.toString()}`);
        return apiEvents.map((ev) => this._fromApiEvent(ev));
    }

    async fetchCategories()
    {
        this.categories = await this.makeRequest('GET', `${this.basePath}/categories`);
        this.categoryByKey = Object.fromEntries(this.categories.map((c) => [c.id, c]));
    }

    checkRangeAndRefetch()
    {
        if (!this.instance || this._refetchInFlight) return;

        const displayDate = this.instance.getCurrentDisplayDate();
        const nearStartEdge = addDays(this.loadedRange.start, CalendarEventManager.REFETCH_EDGE_DAYS) > displayDate;
        const nearEndEdge = addDays(this.loadedRange.end, -CalendarEventManager.REFETCH_EDGE_DAYS) < displayDate;

        if (!nearStartEdge && !nearEndEdge) return;

        const start = addDays(displayDate, -CalendarEventManager.RANGE_PADDING_DAYS_BACK);
        const end = addDays(displayDate, CalendarEventManager.RANGE_PADDING_DAYS_FORWARD);
        this.refetchRange(start, end);
    }

    refetchRange(start, end)
    {
        if (!this.instance || this._refetchInFlight) return;

        this._refetchInFlight = true;

        this.fetchEvents(start, end)
            .then((events) => {
                this.loadedRange = { start, end };
                this.lastLoadedEvents = events;
                this.instance.setEvents(events, true, false);
                if (this.onEventsChanged)
                {
                    this.onEventsChanged();
                }
            })
            .catch((error) => console.error('CalendarEventManager: failed to refetch range', error))
            .finally(() => { this._refetchInFlight = false; });
    }

    _fromApiEvent(apiEvent)
    {
        const isCaseEvent = apiEvent.source === 'case';

        const visual = isCaseEvent
            ? { name: 'From case', colour: '#5B6B82' }
            : (this.categoryByKey[apiEvent.categoryId] || { name: 'Uncategorised', colour: '#9AA3B2' });

        return {
            id: apiEvent.id,
            title: isCaseEvent ? `🔒 ${apiEvent.title}` : apiEvent.title,
            from: new Date(apiEvent.start),
            to: new Date(apiEvent.end),
            isAllDayEvent: Boolean(apiEvent.allDay),
            description: apiEvent.description || '',
            location: apiEvent.location || '',
            group: visual.name,
            color: visual.colour,
            colorBorder: visual.colour,
            colorText: contrastTextColour(visual.colour),
            customTags: {
                source: apiEvent.source,
                sourceType: apiEvent.sourceType || null,
                categoryId: apiEvent.categoryId || null,
                caseId: apiEvent.caseId || null,
                serverId: apiEvent.id,
                isOwner: Boolean(apiEvent.isOwner),
                isShareable: Boolean(apiEvent.isShareable),
                myInviteStatus: apiEvent.myInviteStatus || null,
                invites: apiEvent.invites || [],
            },
        };
    }

    _onEventClicked(event)
    {
        try
        {
            if (!event || !event.customTags) return;

            if (event.customTags.source === 'case')
            {
                const caseId = event.customTags.caseId;
                if (caseId && window.confirm("This event comes from a case. Open the case instead?"))
                {
                    window.location.href = this.caseUrlTemplate.replace('{caseId}', encodeURIComponent(caseId));
                }
                return;
            }

            if (this.onManualEventClicked)
            {
                this.onManualEventClicked(event);
            }
        }
        catch (error)
        {
            console.error('CalendarEventManager: _onEventClicked threw', error);
        }
    }
}

function addDays(date, n)
{
    const out = new Date(date);
    out.setDate(out.getDate() + n);
    return out;
}

function contrastTextColour(hex)
{
    const c = hex.replace('#', '');
    const r = parseInt(c.substring(0, 2), 16) / 255;
    const g = parseInt(c.substring(2, 4), 16) / 255;
    const b = parseInt(c.substring(4, 6), 16) / 255;
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    return luminance > 0.55 ? '#1B2A41' : '#FFFFFF';
}
