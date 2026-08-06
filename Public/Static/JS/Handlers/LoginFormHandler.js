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

        this.totpSection = document.getElementById('TotpInputSection');
        this.recoveryCodeSection = document.getElementById('RecoveryCodeSection');
        this.toggleMfaModeLink = document.getElementById('ToggleMfaMode');
        this.backToLoginLink = document.getElementById('BackToLogin');

        this.passwordChangeForm = document.getElementById('PasswordChangeForm');
        this.newPasswordField = document.getElementById('new_password');
        this.confirmPasswordField = document.getElementById('confirm_password');
        this.passwordChangeButton = document.getElementById('PasswordChangeButton');
        this.backToLoginFromPwChange = document.getElementById('BackToLoginFromPwChange');

        this.mfaSessionToken = null;
        this.passwordChangeToken = null;
        this.isRecoveryCodeMode = false;

        this.loginForm.dataset.loginHandlerInitialised = 'true';
        this.loginErrorSummary = this.createLoginErrorSummary();
        this.initialiseForm();
    }

    createLoginErrorSummary()
    {
        let errorSummary = document.getElementById('LoginErrorSummary');

        if (errorSummary)
        {
            return errorSummary;
        }

        errorSummary = document.createElement('div');
        errorSummary.id = 'LoginErrorSummary';
        errorSummary.className = 'form-error-summary';
        errorSummary.setAttribute('role', 'alert');
        errorSummary.setAttribute('aria-live', 'assertive');
        errorSummary.setAttribute('tabindex', '-1');
        errorSummary.hidden = true;

        this.loginForm.prepend(errorSummary);
        return errorSummary;
    }

    initialiseForm()
    {
        this.mfaForm.style.display = 'none';
        this.passwordChangeForm.style.display = 'none';

        this.loginForm.addEventListener('submit', (e) => this.handleLoginSubmit(e));
        this.emailField.addEventListener('blur', () => this.validateEmail());
        this.passwordField.addEventListener('blur', () => this.validatePassword());
        this.emailField.addEventListener('input', () => {
            this.clearFieldError('EmailAddress');
            this.clearLoginError();
        });
        this.passwordField.addEventListener('input', () => {
            this.clearFieldError('Password');
            this.clearLoginError();
        });

        this.mfaForm.addEventListener('submit', (e) => this.handleMfaSubmit(e));
        this.totpField.addEventListener('input', () => this.clearFieldError('TotpToken'));
        this.recoveryCodeField.addEventListener('input', () => this.clearFieldError('RecoveryCode'));
        this.toggleMfaModeLink.addEventListener('click', (e) => { e.preventDefault(); this.toggleMfaMode(); });
        this.backToLoginLink.addEventListener('click', (e) => { e.preventDefault(); this.showLoginForm(); });

        this.passwordChangeForm.addEventListener('submit', (e) => this.handlePasswordChangeSubmit(e));
        this.newPasswordField.addEventListener('input', () => this.clearFieldError('NewPassword'));
        this.confirmPasswordField.addEventListener('input', () => this.clearFieldError('ConfirmPassword'));
        this.backToLoginFromPwChange.addEventListener('click', (e) => { e.preventDefault(); this.showLoginForm(); });
    }


    // --------------------------------------------------
    // Login
    // --------------------------------------------------

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
            const result = await authCtrl.Login(
                this.emailField.value.trim(),
                this.passwordField.value,
                recaptchaToken
            );
            const [statusCode, response] = Array.isArray(result)
                ? result
                : [200, result];

            if (statusCode < 200 || statusCode >= 300)
            {
                await this.handleLoginError(response, statusCode);
                return;
            }

            if (!response || typeof response !== 'object')
            {
                await this.handleLoginError(
                    'The server returned an invalid response. Please try again.',
                    statusCode
                );
                return;
            }

            if (response.mfaRequired)
            {
                this.mfaSessionToken = response.mfaSessionToken;
                this.showMfaForm();
                return;
            }

            if (response.mustChangePassword)
            {
                this.passwordChangeToken = response.passwordChangeToken;
                this.showPasswordChangeForm();
                return;
            }

            if (response.expiresIn)
            {
                await this.handleLoginSuccess();
                return;
            }

            await this.handleLoginError(response.detail ?? 'Unknown login error. Please try again.');
        }
        catch (error)
        {
            const statusCode =
                error?.statusCode ??
                error?.status ??
                error?.response?.status ??
                null;

            const response =
                error?.response?.data ??
                error?.body ??
                error;

            await this.handleLoginError(response, statusCode);
        }
        finally
        {
            this.setLoadingState(false);
        }
    }


    // --------------------------------------------------
    // MFA
    // --------------------------------------------------

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

        if (this.isRecoveryCodeMode)
        {
            await this.handleRecoveryCodeSubmit();
        }
        else
        {
            await this.handleTotpSubmit();
        }
    }

    async handleTotpSubmit()
    {
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
            const response = await authCtrl.VerifyTotp(this.mfaSessionToken, totpCode);

            if (response.mustChangePassword)
            {
                this.passwordChangeToken = response.passwordChangeToken;
                this.showPasswordChangeForm();
                return;
            }

            if (response.expiresIn)
            {
                await this.handleLoginSuccess();
            }
            else
            {
                await this.handleMfaError('Verification failed. Please try again.');
            }
        }
        catch (error)
        {
            await this.handleMfaError(error);
            this.totpField.value = '';
            this.totpField.focus();
        }
        finally
        {
            this.setMfaLoadingState(false);
        }
    }

    async handleRecoveryCodeSubmit()
    {
        this.clearFieldError('RecoveryCode');

        const recoveryCode = this.recoveryCodeField.value.trim();

        if (!recoveryCode)
        {
            this.showFieldError('RecoveryCode', await Localisation.translate('Recovery code is required'));
            return;
        }

        this.setMfaLoadingState(true);

        try
        {
            const authCtrl = new AuthenticationController();
            const response = await authCtrl.VerifyRecoveryCode(this.mfaSessionToken, recoveryCode);

            if (response.mustChangePassword)
            {
                this.passwordChangeToken = response.passwordChangeToken;
                this.showPasswordChangeForm();
                return;
            }

            if (response.expiresIn)
            {
                await this.handleLoginSuccess();
            }
            else
            {
                await this.handleMfaError('Verification failed. Please try again.');
            }
        }
        catch (error)
        {
            await this.handleMfaError(error);
            this.recoveryCodeField.value = '';
            this.recoveryCodeField.focus();
        }
        finally
        {
            this.setMfaLoadingState(false);
        }
    }


    // --------------------------------------------------
    // Forced password change
    // --------------------------------------------------

    async handlePasswordChangeSubmit(e)
    {
        e.preventDefault();
        this.clearFieldError('NewPassword');
        this.clearFieldError('ConfirmPassword');

        if (!await this.validateNewPassword())
        {
            return;
        }

        this.setPasswordChangeLoadingState(true);

        try
        {
            const authCtrl = new AuthenticationController();
            await authCtrl.ForcedPasswordChange(this.passwordChangeToken, this.newPasswordField.value);

            new ToastNotification(
                await Localisation.translate('Password changed successfully. Please log in with your new password.'),
                'check-circle',
                'success'
            );

            this.showLoginForm();
            this.passwordField.value = '';
            this.emailField.focus();
        }
        catch (error)
        {
            console.error('Password change error:', error);

            if (error.message?.includes('expired') || error.message?.includes('Invalid'))
            {
                new ToastNotification(
                    await Localisation.translate('Session expired. Please log in again.'),
                    'alert-circle',
                    'error'
                );
                this.showLoginForm();
                return;
            }

            new ToastNotification(
                error.message ?? await Localisation.translate('Failed to change password. Please try again.'),
                'alert-circle',
                'error'
            );
        }
        finally
        {
            this.setPasswordChangeLoadingState(false);
        }
    }


    // --------------------------------------------------
    // Success / error handlers
    // --------------------------------------------------

    async handleLoginSuccess()
    {
        // Tokens are set as HttpOnly cookies by the BFF proxy before this point -  nothing to do here except redirect.

        new ToastNotification(
            await Localisation.translate('Login successful! Redirecting...'),
            'check-circle',
            'success'
        );

        setTimeout(() => { window.location.href = '/dashboard'; }, 50);
    }

    async handleLoginError(error, statusCode = null)
    {
        console.error('Login error:', error);

        const detail = typeof error === 'string'
            ? error
            : error?.detail ?? error?.message;

        if (error?.fieldErrors)
        {
            for (const [field, message] of Object.entries(error.fieldErrors))
            {
                this.showFieldError(field, message);
            }

            this.showLoginError(
                await Localisation.translate('Please correct the highlighted fields and try again.')
            );
        }
        else if (detail === 'PasswordResetRequired')
        {
            this.showLoginError(
                await Localisation.translate(
                    'You need to reset your password before signing in. We have sent instructions to your email address.'
                )
            );
        }
        else if (statusCode === 401 || detail === 'Invalid credentials')
        {
            const message = await Localisation.translate(
                'The email address or password you entered is incorrect.'
            );

            this.showLoginError(message);
            this.showFieldError('Password', message);
            this.passwordField.focus();
            this.passwordField.select();
        }
        else if (statusCode === 403)
        {
            this.showLoginError(
                detail ?? await Localisation.translate(
                    'This account is not currently permitted to sign in. Please contact support.'
                )
            );
        }
        else if (statusCode === 429)
        {
            this.showLoginError(
                await Localisation.translate(
                    'There have been too many login attempts. Please wait a moment and try again.'
                )
            );
        }
        else if (statusCode >= 500)
        {
            this.showLoginError(
                await Localisation.translate(
                    'The login service is temporarily unavailable. Please try again shortly.'
                )
            );
        }
        else
        {
            this.showLoginError(
                detail ?? await Localisation.translate(
                    'An unexpected error occurred. Please try again.'
                )
            );
        }

        this.resetReCaptcha();
    }

    showLoginError(message)
    {
        this.loginErrorSummary.textContent = message;
        this.loginErrorSummary.hidden = false;
        this.loginErrorSummary.focus();
    }

    clearLoginError()
    {
        this.loginErrorSummary.hidden = true;
        this.loginErrorSummary.textContent = '';
    }

    async handleMfaError(error)
    {
        console.error('MFA error:', error);

        if (error?.message?.includes('expired') || error?.message?.includes('Invalid MFA session'))
        {
            new ToastNotification(
                await Localisation.translate('Session expired. Please log in again.'),
                'alert-circle',
                'error'
            );
            this.showLoginForm();
            return;
        }

        new ToastNotification(
            error?.message ?? await Localisation.translate('Invalid code. Please try again.'),
            'alert-circle',
            'error'
        );
    }


    // --------------------------------------------------
    // Form visibility
    // --------------------------------------------------

    showLoginForm()
    {
        this.mfaForm.style.display = 'none';
        this.passwordChangeForm.style.display = 'none';
        this.loginForm.style.display = 'block';

        this.mfaSessionToken = null;
        this.passwordChangeToken = null;
        this.isRecoveryCodeMode = false;
        this.totpField.value = '';
        this.recoveryCodeField.value = '';
    }

    showMfaForm()
    {
        this.loginForm.style.display = 'none';
        this.passwordChangeForm.style.display = 'none';
        this.mfaForm.style.display = 'block';

        this.isRecoveryCodeMode = false;
        this.totpSection.style.display = 'block';
        this.recoveryCodeSection.style.display = 'none';
        this.toggleMfaModeLink.textContent = 'Use recovery code instead';

        this.totpField.value = '';
        this.recoveryCodeField.value = '';
        this.totpField.focus();
    }

    showPasswordChangeForm()
    {
        this.loginForm.style.display = 'none';
        this.mfaForm.style.display = 'none';
        this.passwordChangeForm.style.display = 'block';

        this.newPasswordField.value = '';
        this.confirmPasswordField.value = '';
        this.newPasswordField.focus();
    }


    // --------------------------------------------------
    // Validation
    // --------------------------------------------------

    async validateForm()
    {
        const emailOk = await this.validateEmail();
        const passwordOk = await this.validatePassword();
        return emailOk && passwordOk;
    }

    async validateEmail()
    {
        const email = this.emailField.value.trim();

        if (!email)
        {
            this.showFieldError('EmailAddress', await Localisation.translate('Email address is required'));
            return false;
        }

        if (!this.isValidEmail(email))
        {
            this.showFieldError('EmailAddress', await Localisation.translate('Please enter a valid email address'));
            return false;
        }

        this.clearFieldError('EmailAddress');
        return true;
    }

    async validatePassword()
    {
        if (!this.passwordField.value)
        {
            this.showFieldError('Password', await Localisation.translate('Password is required'));
            return false;
        }

        this.clearFieldError('Password');
        return true;
    }

    async validateNewPassword()
    {
        const password = this.newPasswordField.value;
        const confirm = this.confirmPasswordField.value;
        let isValid = true;

        if (!password)
        {
            this.showFieldError('NewPassword', await Localisation.translate('New password is required'));
            isValid = false;
        }

        if (!confirm)
        {
            this.showFieldError('ConfirmPassword', await Localisation.translate('Please confirm your password'));
            isValid = false;
        }
        else if (password !== confirm)
        {
            this.showFieldError('ConfirmPassword', await Localisation.translate('Passwords do not match'));
            isValid = false;
        }

        return isValid;
    }

    isValidEmail(email)
    {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }


    // --------------------------------------------------
    // reCAPTCHA
    // --------------------------------------------------

    async getReCaptchaToken()
    {
        if (typeof grecaptcha === 'undefined')
        {
            throw new Error(await Localisation.translate('Security verification failed. Please refresh the page and try again.'));
        }

        const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'login' });
        this.recaptchaToken.value = token;
        return token;
    }

    resetReCaptcha()
    {
        this.recaptchaToken.value = '';
    }


    // --------------------------------------------------
    // Field error UI
    // --------------------------------------------------

    showFieldError(fieldName, message)
    {
        const errorElement = document.getElementById(`ErrorReadout-${fieldName}`);
        const inputElement = this.getFieldElement(fieldName);

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
        const inputElement = this.getFieldElement(fieldName);

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

    getFieldElement(fieldName)
    {
        const fields = {
            EmailAddress: this.emailField,
            Password: this.passwordField,
            TotpToken: this.totpField,
            RecoveryCode: this.recoveryCodeField,
            NewPassword: this.newPasswordField,
            ConfirmPassword: this.confirmPasswordField
        };

        return fields[fieldName] ?? null;
    }

    clearAllErrors()
    {
        this.clearFieldError('EmailAddress');
        this.clearFieldError('Password');
        this.clearFieldError('TotpToken');
        this.clearFieldError('RecoveryCode');
        this.clearLoginError();

        if (typeof hideGlobalError === 'function')
        {
            hideGlobalError();
        }

        if (typeof hideSuccessMessage === 'function')
        {
            hideSuccessMessage();
        }
    }


    // --------------------------------------------------
    // Loading states
    // --------------------------------------------------

    setLoadingState(loading)
    {
        this._setButtonLoadingState(this.submitButton, this.loginForm, loading, 'loading');
    }

    setMfaLoadingState(loading)
    {
        this._setButtonLoadingState(this.mfaSubmitButton, null, loading);
    }

    setPasswordChangeLoadingState(loading)
    {
        this._setButtonLoadingState(this.passwordChangeButton, null, loading);
    }

    _setButtonLoadingState(button, form, loading, formClass = null)
    {
        const buttonText = button.querySelector('.button-text');
        const spinner = button.querySelector('.loading-spinner');

        button.disabled = loading;
        buttonText.style.display = loading ? 'none' : 'inline';
        spinner.style.display = loading ? 'inline' : 'none';

        if (form && formClass)
        {
            form.classList.toggle(formClass, loading);
        }
    }
}

function initialiseLoginFormHandler()
{
    const loginForm = document.getElementById('LoginForm');

    if (!loginForm || loginForm.dataset.loginHandlerInitialised === 'true')
    {
        return;
    }

    new LoginFormHandler();
}

if (document.readyState === 'loading')
{
    document.addEventListener('DOMContentLoaded', initialiseLoginFormHandler, { once: true });
}
else
{
    initialiseLoginFormHandler();
}
