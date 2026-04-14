class LoginFormHandler
{
    constructor()
    {
        this.loginForm = document.getElementById('LoginForm');
        this.mfaForm = document.getElementById('MfaForm');
        this.submitButton = document.getElementById('LoginButton');
        this.mfaSubmitButton = document.getElementById('MfaSubmitButton');
        this.emailField = document.getElementById('email_address');
        this.passwordField = document.getElementById('raw_password');
        this.totpField = document.getElementById('totp_token');
        this.recoveryCodeField = document.getElementById('recovery_code');
        this.recaptchaToken = document.getElementById('ReCAPTCHAToken');

        // MFA sections
        this.totpSection = document.getElementById('TotpInputSection');
        this.recoveryCodeSection = document.getElementById('RecoveryCodeSection');
        this.toggleMfaModeLink = document.getElementById('ToggleMfaMode');
        this.backToLoginLink = document.getElementById('BackToLogin');

        // state
        this.mfaSessionToken = null;
        this.isRecoveryCodeMode = false;

        this.initializeForm();
    }

    initializeForm()
    {
        // hide MFA form initially
        this.mfaForm.style.display = 'none';

        // login form handlers
        this.loginForm.addEventListener('submit', (e) => this.handleLoginSubmit(e));
        this.emailField.addEventListener('blur', () => this.validateEmail());
        this.passwordField.addEventListener('blur', () => this.validatePassword());
        this.emailField.addEventListener('input', () => this.clearFieldError('EmailAddress'));
        this.passwordField.addEventListener('input', () => this.clearFieldError('Password'));

        // MFA form handlers
        this.mfaForm.addEventListener('submit', (e) => this.handleMfaSubmit(e));
        this.totpField.addEventListener('input', () => this.clearFieldError('TotpToken'));
        this.recoveryCodeField.addEventListener('input', () => this.clearFieldError('RecoveryCode'));

        // toggle between TOTP and recovery code
        this.toggleMfaModeLink.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleMfaMode();
        });

        // back to login
        this.backToLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            this.showLoginForm();
        });
    }


    async handleLoginSubmit(e)
    {
        e.preventDefault();
        this.clearAllErrors();

        if (!await this.validateForm())
        {
            return;
        }

        this.setLoadingState(true);

        try
        {
            const recaptchaToken = await this.getReCaptchaToken();

            const authCtrl = new AuthenticationController();
            const response = await authCtrl.Login(
                this.emailField.value.trim(),
                this.passwordField.value,
                recaptchaToken
            );

            if (response.mfaRequired)
            {
                // MFA required - store token and show MFA form
                this.mfaSessionToken = response.mfaSessionToken;
                this.showMfaForm();
                return;
            }

            if (response.expiresIn)
            {
                await this.handleLoginSuccess(response);
            }
            else
            {
                await this.handleLoginError('Unknown login error. Please try again.');
            }

        }
        catch (error)
        {
            await this.handleLoginError(error);
        }
        finally
        {
            this.setLoadingState(false);
        }
    }

    toggleMfaMode()
    {
        this.isRecoveryCodeMode = !this.isRecoveryCodeMode;

        if (this.isRecoveryCodeMode)
        {
            this.totpSection.style.display = 'none';
            this.recoveryCodeSection.style.display = 'block';
            this.toggleMfaModeLink.textContent = 'Use authenticator app instead';
            this.recoveryCodeField.focus();
        }
        else
        {
            this.totpSection.style.display = 'block';
            this.recoveryCodeSection.style.display = 'none';
            this.toggleMfaModeLink.textContent = 'Use recovery code instead';
            this.totpField.focus();
        }

        this.clearFieldError('TotpToken');
        this.clearFieldError('RecoveryCode');
    }

    async handleMfaSubmit(e)
    {
        e.preventDefault();
        this.clearFieldError('TotpToken');

        const totpCode = this.totpField.value.trim();

        if (!totpCode)
        {
            this.showFieldError('TotpToken', await Localisation.translate('TOTP code is required'));
            return;
        }

        if (!/^\d{6}$/.test(totpCode))
        {
            this.showFieldError('TotpToken', await Localisation.translate('TOTP code must be 6 digits'));
            return;
        }

        this.setMfaLoadingState(true);

        try
        {
            const authCtrl = new AuthenticationController();
            const response = await authCtrl.VerifyTotp(
                this.mfaSessionToken,
                totpCode
            );

            if (response.expiresIn)
            {
                await this.handleLoginSuccess(response);
            }
            else
            {
                await this.handleLoginError('Verification failed. Please try again.');
            }

        }
        catch (error)
        {
            await this.handleMfaError(error);
        }
        finally
        {
            this.setMfaLoadingState(false);
        }
    }

    async handleTotpSubmit()
    {
        const totpCode = this.totpField.value.trim();

        if (!totpCode)
        {
            this.showFieldError('TotpToken', await Localisation.translate('TOTP code is required'));
            return;
        }

        if (!/^\d{6}$/.test(totpCode))
        {
            this.showFieldError('TotpToken', await Localisation.translate('TOTP code must be 6 digits'));
            return;
        }

        this.setMfaLoadingState(true);

        try
        {
            const authCtrl = new AuthenticationController();
            const response = await authCtrl.VerifyTotp(
                this.mfaSessionToken,
                totpCode
            );

            if (response.expiresIn) {
                await this.handleLoginSuccess(response);
            } else {
                await this.handleMfaError('Verification failed. Please try again.');
            }

        } catch (error) {
            await this.handleMfaError(error);
        } finally {
            this.setMfaLoadingState(false);
        }
    }

    async handleRecoveryCodeSubmit() {
        const recoveryCode = this.recoveryCodeField.value.trim();

        if (!recoveryCode) {
            this.showFieldError('RecoveryCode', await Localisation.translate('Recovery code is required'));
            return;
        }

        this.setMfaLoadingState(true);

        try {
            const authCtrl = new AuthenticationController();
            const response = await authCtrl.VerifyRecoveryCode(
                this.mfaSessionToken,
                recoveryCode
            );

            if (response.expiresIn)
            {
                await this.handleLoginSuccess();
            }
            else
            {
                await this.handleMfaError('Verification failed. Please try again.');
            }

        } catch (error) {
            await this.handleMfaError(error);
            this.recoveryCodeField.value = '';
            this.recoveryCodeField.focus();
        } finally {
            this.setMfaLoadingState(false);
        }
    }

    async handleLoginSuccess()
    {
        new ToastNotification(
            await Localisation.translate('Login successful! Redirecting...'),
            'check-circle',
            'success'
        );

        setTimeout(() => {
            window.location.href = '/dashboard';
        }, 50);
    }

    showMfaForm() {
        this.loginForm.style.display = 'none';
        this.mfaForm.style.display = 'block';

        // Reset to TOTP mode
        this.isRecoveryCodeMode = false;
        this.totpSection.style.display = 'block';
        this.recoveryCodeSection.style.display = 'none';
        this.toggleMfaModeLink.textContent = 'Use recovery code instead';

        this.totpField.value = '';
        this.recoveryCodeField.value = '';
        this.totpField.focus();
    }

    showLoginForm() {
        this.mfaForm.style.display = 'none';
        this.loginForm.style.display = 'block';
        this.mfaSessionToken = null;
        this.isRecoveryCodeMode = false;
        this.totpField.value = '';
        this.recoveryCodeField.value = '';
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

    async getReCaptchaToken() {
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

    async handleLoginError(error)
    {
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
            new ToastNotification(
                await Localisation.translate('An unexpected error occurred. Please try again.'),
                'alert-circle',
                'error'
            );
        }

        this.resetReCaptcha();
    }

    async handleMfaError(error)
    {
        console.error('MFA error:', error);

        if (error.message?.includes('expired') || error.message?.includes('Invalid MFA session'))
        {
            new ToastNotification(
                await Localisation.translate('Session expired. Please log in again.'),
                'alert-circle',
                'error'
            );
            this.showLoginForm();
            return;
        }

        if (error.message)
        {
            new ToastNotification(error.message, 'alert-circle', 'error');
        }
        else
        {
            new ToastNotification(
                await Localisation.translate('Invalid code. Please try again.'),
                'alert-circle',
                'error'
            );
        }

        // clear the appropriate field
        if (this.isRecoveryCodeMode)
        {
            this.recoveryCodeField.value = '';
            this.recoveryCodeField.focus();
        }
        else
        {
            this.totpField.value = '';
            this.totpField.focus();
        }
    }

    setLoadingState(loading)
    {
        const buttonText = this.submitButton.querySelector('.button-text');
        const spinner = this.submitButton.querySelector('.loading-spinner');

        if (loading)
        {
            this.submitButton.disabled = true;
            buttonText.style.display = 'none';
            spinner.style.display = 'inline';
            this.loginForm.classList.add('loading');
        }
        else
        {
            this.submitButton.disabled = false;
            buttonText.style.display = 'inline';
            spinner.style.display = 'none';
            this.loginForm.classList.remove('loading');
        }
    }

    setMfaLoadingState(loading)
    {
        const buttonText = this.mfaSubmitButton.querySelector('.button-text');
        const spinner = this.mfaSubmitButton.querySelector('.loading-spinner');

        if (loading) {
            this.mfaSubmitButton.disabled = true;
            buttonText.style.display = 'none';
            spinner.style.display = 'inline';
        } else {
            this.mfaSubmitButton.disabled = false;
            buttonText.style.display = 'inline';
            spinner.style.display = 'none';
        }
    }


    showFieldError(fieldName, message)
    {
        const errorElement = document.getElementById(`ErrorReadout-${fieldName}`);
        const inputElement = document.getElementById(
            fieldName.toLowerCase().replace(/([A-Z])/g, '_$1').replace(/^_/, '')
        );

        if (errorElement)
        {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }

        if (inputElement)
        {
            inputElement.classList.add('error');
            inputElement.setAttribute('aria-invalid', 'true');
        }
    }

    clearFieldError(fieldName)
    {
        const errorElement = document.getElementById(`ErrorReadout-${fieldName}`);
        const inputElement = document.getElementById(
            fieldName.toLowerCase().replace(/([A-Z])/g, '_$1').replace(/^_/, '')
        );

        if (errorElement)
        {
            errorElement.style.display = 'none';
            errorElement.textContent = '';
        }

        if (inputElement)
        {
            inputElement.classList.remove('error');
            inputElement.removeAttribute('aria-invalid');
        }
    }

    clearAllErrors()
    {
        this.clearFieldError('EmailAddress');
        this.clearFieldError('Password');
        this.clearFieldError('TotpToken');
        hideGlobalError();
        hideSuccessMessage();
    }

    resetReCaptcha()
    {
        this.recaptchaToken.value = '';
        if (typeof grecaptcha !== 'undefined' && grecaptcha.reset)
        {
            grecaptcha.reset();
        }
    }
}
