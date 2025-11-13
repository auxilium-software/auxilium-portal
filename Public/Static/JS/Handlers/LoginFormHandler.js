class LoginFormHandler {
    constructor() {
        this.form = document.getElementById('LoginForm');
        this.submitButton = document.getElementById('LoginButton');
        this.emailField = document.getElementById('email_address');
        this.passwordField = document.getElementById('raw_password');
        this.recaptchaToken = document.getElementById('ReCAPTCHAToken');

        this.initializeForm();
    }

    initializeForm() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        this.emailField.addEventListener('blur', () => this.validateEmail());
        this.passwordField.addEventListener('blur', () => this.validatePassword());

        this.emailField.addEventListener('input', () => this.clearFieldError('EmailAddress'));
        this.passwordField.addEventListener('input', () => this.clearFieldError('Password'));
    }

    async handleSubmit(e) {
        e.preventDefault();

        this.clearAllErrors();

        if (!await this.validateForm()) {
            return;
        }

        this.setLoadingState(true);

        try {
            const recaptchaToken = await this.getReCaptchaToken();

            const authCtrl = new AuthenticationController();
            const response = await authCtrl.Login(
                this.emailField.value.trim(),
                this.passwordField.value,
                recaptchaToken
            );

            if(response !== false)
            {
                CookieUtilities.setCookie('access_token', response['access_token'], 0.0208333);
                CookieUtilities.setCookie('refresh_token', response['refresh_token'], 7);
                new ToastNotification(await Localisation.translate('Login successful! Redirecting...'), 'check-circle', 'success');

                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 1500);

            }
            else
            {
                await this.handleLoginError('Unknown login error. Please try again.');
            }

        } catch (error) {
            await this.handleLoginError(error);
        } finally {
            this.setLoadingState(false);
        }
    }

    async validateForm() {
        let isValid = true;

        if (!await this.validateEmail()) {
            isValid = false;
        }

        if (!await this.validatePassword()) {
            isValid = false;
        }

        return isValid;
    }

    async validateEmail() {
        const email = this.emailField.value.trim();

        if (!email) {
            this.showFieldError('EmailAddress', await Localisation.translate('Email address is required'));
            return false;
        }

        if (!this.isValidEmail(email)) {
            this.showFieldError('EmailAddress', await Localisation.translate('Please enter a valid email address'));
            return false;
        }

        this.clearFieldError('EmailAddress');
        return true;
    }

    async validatePassword() {
        const password = this.passwordField.value;

        if (!password) {
            this.showFieldError('Password', await Localisation.translate('Password is required'));
            return false;
        }

        this.clearFieldError('Password');
        return true;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    async getReCaptchaToken(token) {
        try {
            if (typeof grecaptcha === 'undefined') {
                throw new Error('reCAPTCHA not loaded');
            }

            const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {
                action: 'login'
            });

            this.recaptchaToken.value = token;
            return token;
        } catch (error) {
            console.error('reCAPTCHA error:', error);
            throw new Error(await Localisation.translate('Security verification failed. Please refresh the page and try again.'));
        }
    }

    async handleLoginError(error) {
        console.error('Login error:', error);

        if (error.fieldErrors)
        {
            Object.keys(error.fieldErrors).forEach(field => {
                this.showFieldError(field, error.fieldErrors[field]);
            });
        }
        else if (error.message)
        {
            new ToastNotification(error.message, 'alert-circle', 'error');
        }
        else
        {
            new ToastNotification(await Localisation.translate('An unexpected error occurred. Please try again.'), 'alert-circle', 'error');
        }

        this.resetReCaptcha();
    }

    setLoadingState(loading) {
        const buttonText = this.submitButton.querySelector('.button-text');
        const spinner = this.submitButton.querySelector('.loading-spinner');

        if (loading) {
            this.submitButton.disabled = true;
            buttonText.style.display = 'none';
            spinner.style.display = 'inline';
            this.form.classList.add('loading');
        } else {
            this.submitButton.disabled = false;
            buttonText.style.display = 'inline';
            spinner.style.display = 'none';
            this.form.classList.remove('loading');
        }
    }

    showFieldError(fieldName, message) {
        const errorElement = document.getElementById(`ErrorReadout-${fieldName}`);
        const inputElement = document.getElementById(fieldName.toLowerCase().replace(/([A-Z])/g, '_$1'));

        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }

        if (inputElement) {
            inputElement.classList.add('error');
            inputElement.setAttribute('aria-invalid', 'true');
        }
    }

    clearFieldError(fieldName) {
        const errorElement = document.getElementById(`ErrorReadout-${fieldName}`);
        const inputElement = document.getElementById(fieldName.toLowerCase().replace(/([A-Z])/g, '_$1'));

        if (errorElement) {
            errorElement.style.display = 'none';
            errorElement.textContent = '';
        }

        if (inputElement) {
            inputElement.classList.remove('error');
            inputElement.removeAttribute('aria-invalid');
        }
    }

    clearAllErrors() {
        this.clearFieldError('EmailAddress');
        this.clearFieldError('Password');
        hideGlobalError();
        hideSuccessMessage();
    }

    resetReCaptcha() {
        this.recaptchaToken.value = '';
        if (typeof grecaptcha !== 'undefined' && grecaptcha.reset) {
            grecaptcha.reset();
        }
    }
}
