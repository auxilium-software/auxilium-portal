class ToastNotification
{
    static lastInStack = null;
    doAfterFunc = null;

    constructor(notification, icon = null, style = null)
    {
        console.log("Displaying notification: \"" + notification + "\" with icon " + icon);

        this.doAfterFunc = () =>
        {
            ToastNotification.lastInStack = null;
            console.log("Reset!")
        };

        let doAnimate = () =>
        {
            let container = document.createElement("div");
            let textSpan = document.createElement("span");
            let textContent = document.createTextNode(notification);
            let iconWhite = false;
            let iconBlack = false;
            if (style != null)
            {
                switch (style)
                {
                    case "error":
                        iconWhite = true;
                }
            }
            if (icon != null)
            {
                let iconObj = document.createElement("span");
                iconObj.classList.add("inline-icon-shift-left");
                iconObj.classList.add("inline-icon-low-margin");
                if (iconWhite)
                {
                    iconObj.classList.add(icon + "-icon-white");
                }
                else
                {
                    if (iconBlack)
                    {
                        iconObj.classList.add(icon + "-icon-black");
                    }
                    else
                    {
                        iconObj.classList.add(icon + "-icon");
                    }
                }
                container.appendChild(iconObj);
            }
            textSpan.appendChild(textContent);
            container.appendChild(textSpan);
            if (style != null)
            {
                container.classList.add("toast-notification-" + style);
            }
            else
            {
                container.classList.add("toast-notification");
            }

            setTimeout(() =>
            {
                container.classList.remove("toast-notification-show");
                if (this.doAfterFunc != null)
                {
                    this.doAfterFunc();
                }
                setTimeout(() =>
                {
                    container.remove();
                }, 500);
            }, 3000);

            document.body.appendChild(container);

            setTimeout(() =>
            {
                container.classList.add("toast-notification-show");
            }, 50);
        };

        if (ToastNotification.lastInStack == null)
        {
            ToastNotification.lastInStack = this;
            doAnimate();
        }
        else
        {
            ToastNotification.lastInStack.doAfter(doAnimate);
            ToastNotification.lastInStack = this;
        }
    }

    doAfter(func)
    {
        this.doAfterFunc = func;
    }
}
