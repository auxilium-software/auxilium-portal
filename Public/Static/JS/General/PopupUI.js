class PopupDialog
{
    constructor(body, closeFunction)
    {
        this.popupContainer = document.createElement("div");
        this.popupContainer.classList.add("popup-window");

        this.popupBody = document.createElement("div");
        this.popupBody.classList.add("popup-window-body");
        this.popupContainer.appendChild(this.popupBody);

        if (body != null)
        {
            body.forEach((item, index) =>
            {
                this.popupBody.appendChild(item);
            });
        }

        this.popupButtons = document.createElement("div");
        this.popupButtons.classList.add("popup-window-button-box");
        this.popupContainer.appendChild(this.popupButtons);
        this.closeButton = document.createElement("a");
        this.closeButton.innerText = "Close";
        this.closeButton.classList.add("button");
        this.closeButton.style.float = "right";
        this.closeButton.addEventListener("click", () =>
        {
            this.popupContainer.remove();
            closeFunction();
        }, false);
        this.popupButtons.appendChild(this.closeButton);

        document.body.appendChild(this.popupContainer);
    }
}

class ConfirmPopupDialog extends PopupDialog
{
    constructor(body, closeFunction, acceptFunction)
    {
        super(body, closeFunction);
        this.closeButton.style.float = null;
        let confirmButton = document.createElement("a");
        this.closeButton.innerText = "Cancel";
        confirmButton.innerText = "Continue";
        confirmButton.classList.add("button");
        confirmButton.style.float = "right";
        confirmButton.addEventListener("click", () =>
        {
            this.popupContainer.remove();
            acceptFunction();
        }, false);
        this.popupButtons.appendChild(confirmButton);
    }
}

class TextPopupDialog extends PopupDialog
{
    constructor(body, choices)
    {
        super(body);
        this.choices = choices;
    }
}

class FullscreenSpinner
{
    constructor(text)
    {
        this.spinnerContainer = document.createElement("div");
        this.spinnerContainer.classList.add("fullscreen-overlay");

        if (text != null)
        {
            this.spinnerText = document.createElement("span");
            this.spinnerText.classList.add("spinner-text");
            this.spinnerText.textContent = text;
            this.spinnerContainer.appendChild(this.spinnerText);
        }

        this.spinner = document.createElement("span");
        this.spinner.classList.add("loading-spinner");
        for (let i = 0; i < 5; i++)
        {
            this.spinner.appendChild(document.createElement("div"));
        }
        this.spinnerContainer.appendChild(this.spinner);

        document.body.appendChild(this.spinnerContainer);
    }
}
