class CalendarPageHandler
{
    constructor(root)
    {
        this.root = root;
        this.calendarManager = new CalendarEventManager(root);
        this.sharingPanel = new CalendarSharingPanel(this.calendarManager);
        this.eventEditor = new CalendarEventEditorModal(this.calendarManager);

        this.calendarManager.onEventsChanged = () => this.sharingPanel.refresh();
        this.calendarManager.onManualEventClicked = (event) => this.eventEditor.openForEdit(event);

        this._init();
    }

    async _init()
    {
        await this.calendarManager.init();

        await Localisation.preloadTranslations([
            'New event', 'Edit event', 'Title', 'Category', 'Select a category…',
            'All day', 'Start', 'End', 'Location', 'Description',
            'Save', 'Delete', 'Cancel',
        ]);

        this.sharingPanel.refresh();

        const sharingBtn = document.getElementById('cal-sharing-btn');
        if (sharingBtn)
        {
            const label = document.getElementById('cal-sharing-btn-label');
            if (label) label.textContent = await Localisation.translate('Sharing');
            sharingBtn.addEventListener('click', () => this.sharingPanel.open());
        }

        const newEventBtn = document.getElementById('cal-new-event-btn');
        if (newEventBtn)
        {
            const label = document.getElementById('cal-new-event-btn-label');
            if (label) label.textContent = await Localisation.translate('New Event');
            newEventBtn.addEventListener('click', () => this.eventEditor.openForCreate());
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('calendar-app');
    if (!root) return;
    new CalendarPageHandler(root);
});
