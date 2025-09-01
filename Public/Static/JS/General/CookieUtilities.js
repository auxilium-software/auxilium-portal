
class CookieUtilities
{
    static setCookie(name, value, days)
    {
        try {
            const expirationDate = new Date();
            expirationDate.setTime(expirationDate.getTime() + (days * 24 * 60 * 60 * 1000));

            const cookieAttributes = [
                `${name}=${encodeURIComponent(value)}`,
                `path=/`,
                `expires=${expirationDate.toUTCString()}`,
                `SameSite=Lax`
            ];

            if (window.location.protocol === 'https:') {
                cookieAttributes.push('Secure');
            }

            document.cookie = cookieAttributes.join('; ');

            console.log(`Cookie '${name}' set successfully`);
        } catch (error) {
            console.error(`Failed to set cookie '${name}':`, error);
        }
    }

    static getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');

        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1, c.length);
            }
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length, c.length));
            }
        }
        return null;
    }

    static deleteCookie(name) {
        document.cookie = `${name}=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
    }
}
