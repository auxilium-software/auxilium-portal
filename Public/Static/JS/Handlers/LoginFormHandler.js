class LoginFormHandler
{
    static instance = null;

    constructor()
    {
        // The file initialises itself (see bottom), but a template may also call
        // `new LoginFormHandler()`. Returning the existing instance stops a second
        // set of submit listeners being bound (which would double-submit).
        if (LoginFormHandler.instance)
        {
            return LoginFormHandler.instance;
        }

        LoginFormHandler.instance = this;

        this.forms = {
            login: document.getElementById('LoginForm'),
            mfa: document.getElementById('MfaForm'),
            passwordChange: document.getElementById('PasswordChangeForm')
        };

        this.buttons = {
            login: document.getElementById('LoginButton'),
            mfa: document.getElementById('MfaSubmitButton'),
            passwordChange: document.getElementById('PasswordChangeButton')
        };

        this.formReadouts = {
            login: document.getElementById('ErrorReadout-LoginForm'),
            mfa: document.getElementById('ErrorReadout-MfaForm'),
            passwordChange: document.getElementById('ErrorReadout-PasswordChangeForm')
        };

        this.inputs = {
            EmailAddress: document.getElementById('email_address'),
            Password: document.getElementById('raw_password'),
            TotpToken: document.getElementById('totp_token'),
            RecoveryCode: document.getElementById('recovery_code'),
            NewPassword: document.getElementById('new_password'),
            ConfirmPassword: document.getElementById('confirm_password')
        };

        this.totpSection = document.getElementById('TotpInputSection');
        this.recoveryCodeSection = document.getElementById('RecoveryCodeSection');
        this.toggleMfaModeLink = document.getElementById('ToggleMfaMode');
        this.backToLoginLinks = [
            document.getElementById('BackToLogin'),
            document.getElementById('BackToLoginFromPwChange')
        ];
        this.recaptchaTokenField = document.getElementById('ReCAPTCHAToken');

        this.currentForm = 'login';
        this.busy = { login: false, mfa: false, passwordChange: false };
        this.useRecoveryCode = false;
        this.mfaSessionToken = null;
        this.passwordChangeToken = null;

        if (!this.forms.login || !this.forms.mfa || !this.forms.passwordChange)
        {
            console.error('LoginFormHandler: one or more login forms are missing from the page.');
            return;
        }

        this.initializeForm();
    }


    // --------------------------------------------------
    // --------------------------------------------------

    initializeForm()
    {
        this.forms.login.addEventListener('submit', (e) => this.handleLoginSubmit(e));
        this.forms.mfa.addEventListener('submit', (e) => this.handleMfaSubmit(e));
        this.forms.passwordChange.addEventListener('submit', (e) => this.handlePasswordChangeSubmit(e));

        for (const [fieldName, input] of Object.entries(this.inputs))
        {
            input?.addEventListener('input', () => {
                this.clearFieldError(fieldName);
                this.clearFormError(this.currentForm);
            });
        }

        this.inputs.EmailAddress?.addEventListener('blur', () => this.validateEmail());
        this.inputs.Password?.addEventListener('blur', () => this.validatePassword());

        this.toggleMfaModeLink?.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleMfaMode();
        });

        for (const link of this.backToLoginLinks)
        {
            link?.addEventListener('click', (e) => {
                e.preventDefault();
                this.showLogin();
            });
        }

        this.showForm('login');
    }


    // --------------------------------------------------
    // Login
    // --------------------------------------------------

    async handleLoginSubmit(e)
    {
        e.preventDefault();

        if (this.busy.login)
        {
            return;
        }

        this.clearAllErrors('login');
        this.setLoadingState('login', true);

        try
        {
            if (!await this.validateLoginForm())
            {
                return;
            }

            const recaptchaToken = await this.getReCaptchaToken();

            if (!recaptchaToken)
            {
                await this.showFormError(
                    'login',
                    await Localisation.translate('Security verification is unavailable. Please refresh the page and try again.')
                );
                return;
            }

            const authCtrl = new AuthenticationController();
            const [statusCode, response] = await authCtrl.Login(
                this.inputs.EmailAddress.value.trim(),
                this.inputs.Password.value,
                recaptchaToken
            );

            await this.handleAuthenticationResponse('login', statusCode, response);
        }
        catch (error)
        {
            await this.handleApiError('login', null, error?.message ?? null);
        }
        finally
        {
            this.setLoadingState('login', false);
        }
    }

    async handleAuthenticationResponse(formName, statusCode, response)
    {
        if (!LoginFormHandler.isSuccess(statusCode))
        {
            await this.handleApiError(formName, statusCode, response);
            return;
        }

        if (!response || typeof response !== 'object')
        {
            await this.showFormError(
                formName,
                await Localisation.translate('The server returned an invalid response. Please try again.')
            );
            this.resetReCaptcha();
            return;
        }

        if (response.mfaRequired)
        {
            this.mfaSessionToken = response.mfaSessionToken ?? null;
            await this.showMfa();
            return;
        }

        if (response.mustChangePassword)
        {
            this.passwordChangeToken = response.passwordChangeToken ?? null;
            this.showPasswordChange();
            return;
        }

        if (response.expiresIn)
        {
            await this.handleLoginSuccess();
            return;
        }

        await this.handleApiError(formName, statusCode, response);
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


    // --------------------------------------------------
    // MFA
    // --------------------------------------------------

    activeMfaField()
    {
        return this.useRecoveryCode ? 'RecoveryCode' : 'TotpToken';
    }

    async toggleMfaMode()
    {
        await this.setMfaMode(!this.useRecoveryCode);
        this.clearAllErrors('mfa');
    }

    async setMfaMode(useRecoveryCode)
    {
        this.useRecoveryCode = useRecoveryCode;

        if (this.totpSection)
        {
            this.totpSection.style.display = useRecoveryCode ? 'none' : 'block';
        }

        if (this.recoveryCodeSection)
        {
            this.recoveryCodeSection.style.display = useRecoveryCode ? 'block' : 'none';
        }

        this.focusField(this.activeMfaField());

        if (this.toggleMfaModeLink)
        {
            this.toggleMfaModeLink.textContent = useRecoveryCode
                ? await Localisation.translate('Use authenticator app instead')
                : await Localisation.translate('Use recovery code instead');
        }
    }

    async handleMfaSubmit(e)
    {
        e.preventDefault();

        if (this.busy.mfa)
        {
            return;
        }

        this.clearAllErrors('mfa');

        if (!this.mfaSessionToken)
        {
            await this.returnToLoginWithSessionExpired();
            return;
        }

        const fieldName = this.activeMfaField();
        const code = this.inputs[fieldName].value.trim();

        if (!await this.validateMfaCode(fieldName, code))
        {
            return;
        }

        this.setLoadingState('mfa', true);

        try
        {
            const authCtrl = new AuthenticationController();
            const [statusCode, response] = this.useRecoveryCode
                ? await authCtrl.VerifyRecoveryCode(this.mfaSessionToken, code)
                : await authCtrl.VerifyTotp(this.mfaSessionToken, code);

            await this.handleAuthenticationResponse('mfa', statusCode, response);
        }
        catch (error)
        {
            await this.handleApiError('mfa', null, error?.message ?? null);
        }
        finally
        {
            this.setLoadingState('mfa', false);
        }
    }

    async validateMfaCode(fieldName, code)
    {
        if (fieldName === 'RecoveryCode')
        {
            if (!code)
            {
                await this.showFieldError(fieldName, await Localisation.translate('Recovery code is required'));
                return false;
            }

            return true;
        }

        if (!code)
        {
            await this.showFieldError(fieldName, await Localisation.translate('TOTP code is required'));
            return false;
        }

        if (!/^\d{6}$/.test(code))
        {
            await this.showFieldError(fieldName, await Localisation.translate('TOTP code must be 6 digits'));
            return false;
        }

        return true;
    }


    // --------------------------------------------------
    // Forced password change
    // --------------------------------------------------

    async handlePasswordChangeSubmit(e)
    {
        e.preventDefault();

        if (this.busy.passwordChange)
        {
            return;
        }

        this.clearAllErrors('passwordChange');

        if (!this.passwordChangeToken)
        {
            await this.returnToLoginWithSessionExpired();
            return;
        }

        if (!await this.validateNewPassword())
        {
            return;
        }

        this.setLoadingState('passwordChange', true);

        try
        {
            const authCtrl = new AuthenticationController();
            const [statusCode, response] = await authCtrl.ForcedPasswordChange(
                this.passwordChangeToken,
                this.inputs.NewPassword.value
            );

            if (!LoginFormHandler.isSuccess(statusCode))
            {
                await this.handleApiError('passwordChange', statusCode, response);
                return;
            }

            new ToastNotification(
                await Localisation.translate('Password changed. Please log in with your new password.'),
                'check-circle',
                'success'
            );

            this.showLogin();
            this.setValue('Password', '');
            this.focusField('EmailAddress');
        }
        catch (error)
        {
            await this.handleApiError('passwordChange', null, error?.message ?? null);
        }
        finally
        {
            this.setLoadingState('passwordChange', false);
        }
    }


    // --------------------------------------------------
    // API error handling
    // --------------------------------------------------

    async handleApiError(formName, statusCode, response)
    {
        console.error(`${formName} error:`, statusCode, response);

        const detail = LoginFormHandler.extractDetail(response);
        const fieldErrors = (response && typeof response === 'object') ? response.fieldErrors ?? null : null;

        this.resetReCaptcha();

        if (fieldErrors)
        {
            for (const [fieldName, message] of Object.entries(fieldErrors))
            {
                await this.showFieldError(fieldName, await Localisation.translate(message));
            }

            await this.showFormError(
                formName,
                await Localisation.translate('Please correct the highlighted fields and try again.')
            );
            return;
        }

        const sessionExpired = statusCode === 401
            || detail?.includes('expired')
            || detail?.includes('Invalid MFA session');

        if (formName !== 'login' && sessionExpired)
        {
            await this.returnToLoginWithSessionExpired();
            return;
        }

        if (detail === 'PasswordResetRequired')
        {
            await this.showFormError(
                formName,
                await Localisation.translate('You need to reset your password before signing in. We have sent instructions to your email address.')
            );
            return;
        }

        if (formName === 'login' && (statusCode === 401 || detail === 'Invalid credentials'))
        {
            this.markFieldInvalid('EmailAddress');
            this.markFieldInvalid('Password');

            await this.showFormError(
                formName,
                await Localisation.translate('The email address or password you entered is incorrect.')
            );

            this.focusField('Password', true);
            return;
        }

        if (statusCode === 403)
        {
            await this.showFormError(
                formName,
                await Localisation.translate(detail ?? 'This account is not currently permitted to sign in. Please contact support.')
            );
            return;
        }

        if (statusCode === 429)
        {
            await this.showFormError(
                formName,
                await Localisation.translate('There have been too many login attempts. Please wait a moment and try again.')
            );
            return;
        }

        if (typeof statusCode === 'number' && statusCode >= 500)
        {
            await this.showFormError(
                formName,
                await Localisation.translate('The login service is temporarily unavailable. Please try again shortly.')
            );
            return;
        }

        if (formName === 'mfa')
        {
            const fieldName = this.activeMfaField();

            this.setValue(fieldName, '');
            this.markFieldInvalid(fieldName);

            await this.showFormError(
                formName,
                await Localisation.translate(detail ?? 'That code was not accepted. Check it and try again.')
            );

            this.focusField(fieldName);
            return;
        }

        if (formName === 'passwordChange')
        {
            await this.showFormError(
                formName,
                await Localisation.translate(detail ?? 'Your password could not be changed. Please try again.')
            );
            return;
        }

        await this.showFormError(
            formName,
            await Localisation.translate(detail ?? 'Something went wrong. Please try again.')
        );
    }

    async returnToLoginWithSessionExpired()
    {
        this.showLogin();
        await this.showFormError('login', await Localisation.translate('Your session has expired. Please log in again.'));
    }


    // --------------------------------------------------
    // Error rendering
    // --------------------------------------------------

    async showFormError(formName, message)
    {
        const readout = this.formReadouts[formName];

        if (!readout)
        {
            new ToastNotification(message, 'alert-circle', 'error');
            return;
        }

        readout.textContent = message;
        readout.style.display = 'block';
    }

    clearFormError(formName)
    {
        const readout = this.formReadouts[formName];

        if (readout)
        {
            readout.textContent = '';
            readout.style.display = 'none';
        }
    }

    async showFieldError(fieldName, message)
    {
        const readout = document.getElementById(`ErrorReadout-${fieldName}`);

        if (!readout)
        {
            await this.showFormError(this.currentForm, message);
            return;
        }

        readout.textContent = message;
        readout.style.display = 'block';
        this.markFieldInvalid(fieldName);
    }

    clearFieldError(fieldName)
    {
        const readout = document.getElementById(`ErrorReadout-${fieldName}`);

        if (readout)
        {
            readout.textContent = '';
            readout.style.display = 'none';
        }

        const input = this.inputs[fieldName];

        if (input)
        {
            input.classList.remove('error');
            input.removeAttribute('aria-invalid');
        }
    }

    markFieldInvalid(fieldName)
    {
        const input = this.inputs[fieldName];

        if (input)
        {
            input.classList.add('error');
            input.setAttribute('aria-invalid', 'true');
        }
    }

    clearAllErrors(formName)
    {
        this.clearFormError(formName);

        for (const readout of this.forms[formName].querySelectorAll('[id^="ErrorReadout-"]'))
        {
            this.clearFieldError(readout.id.substring('ErrorReadout-'.length));
        }

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
    // Form visibility
    // --------------------------------------------------

    showForm(formName)
    {
        this.currentForm = formName;

        for (const [name, form] of Object.entries(this.forms))
        {
            form.style.display = name === formName ? 'block' : 'none';
        }
    }

    showLogin()
    {
        this.showForm('login');

        this.mfaSessionToken = null;
        this.passwordChangeToken = null;

        for (const fieldName of ['TotpToken', 'RecoveryCode', 'NewPassword', 'ConfirmPassword'])
        {
            this.setValue(fieldName, '');
        }

        this.clearAllErrors('mfa');
        this.clearAllErrors('passwordChange');
    }

    async showMfa()
    {
        this.showForm('mfa');
        this.clearAllErrors('mfa');

        this.setValue('TotpToken', '');
        this.setValue('RecoveryCode', '');

        await this.setMfaMode(false);
    }

    showPasswordChange()
    {
        this.showForm('passwordChange');
        this.clearAllErrors('passwordChange');

        this.setValue('NewPassword', '');
        this.setValue('ConfirmPassword', '');
        this.focusField('NewPassword');
    }


    // --------------------------------------------------
    // Validation
    // --------------------------------------------------

    async validateLoginForm()
    {
        const emailOk = await this.validateEmail();
        const passwordOk = await this.validatePassword();

        return emailOk && passwordOk;
    }

    async validateEmail()
    {
        const email = this.inputs.EmailAddress.value.trim();

        if (!email)
        {
            await this.showFieldError('EmailAddress', await Localisation.translate('Email address is required'));
            return false;
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
        {
            await this.showFieldError('EmailAddress', await Localisation.translate('Please enter a valid email address'));
            return false;
        }

        this.clearFieldError('EmailAddress');
        return true;
    }

    async validatePassword()
    {
        if (!this.inputs.Password.value)
        {
            await this.showFieldError('Password', await Localisation.translate('Password is required'));
            return false;
        }

        this.clearFieldError('Password');
        return true;
    }

    async validateNewPassword()
    {
        const password = this.inputs.NewPassword.value;
        const confirmation = this.inputs.ConfirmPassword.value;
        let isValid = true;

        if (!password)
        {
            await this.showFieldError('NewPassword', await Localisation.translate('New password is required'));
            isValid = false;
        }

        if (!confirmation)
        {
            await this.showFieldError('ConfirmPassword', await Localisation.translate('Please confirm your password'));
            isValid = false;
        }
        else if (password !== confirmation)
        {
            await this.showFieldError('ConfirmPassword', await Localisation.translate('Passwords do not match'));
            isValid = false;
        }

        return isValid;
    }


    // --------------------------------------------------
    // reCAPTCHA
    // --------------------------------------------------

    async getReCaptchaToken()
    {
        if (typeof RECAPTCHA_SITE_KEY === 'undefined' || !RECAPTCHA_SITE_KEY
            || typeof grecaptcha === 'undefined' || typeof grecaptcha.execute !== 'function')
        {
            return null;
        }

        if (typeof grecaptcha.ready === 'function')
        {
            await new Promise((resolve) => grecaptcha.ready(resolve));
        }

        const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'login' });

        if (this.recaptchaTokenField)
        {
            this.recaptchaTokenField.value = token;
        }

        return token;
    }

    resetReCaptcha()
    {
        if (this.recaptchaTokenField)
        {
            this.recaptchaTokenField.value = '';
        }
    }


    // --------------------------------------------------
    // Loading state
    // --------------------------------------------------

    setLoadingState(formName, loading)
    {
        this.busy[formName] = loading;
        this.forms[formName].classList.toggle('loading', loading);

        const button = this.buttons[formName];

        if (!button)
        {
            return;
        }

        button.disabled = loading;
        button.setAttribute('aria-busy', loading ? 'true' : 'false');

        const buttonText = button.querySelector('.button-text');
        const spinner = button.querySelector('.loading-spinner');

        if (buttonText)
        {
            buttonText.style.display = loading ? 'none' : 'inline';
        }

        if (spinner)
        {
            spinner.style.display = loading ? 'inline' : 'none';
        }
    }


    // --------------------------------------------------
    // --------------------------------------------------

    setValue(fieldName, value)
    {
        const input = this.inputs[fieldName];

        if (input)
        {
            input.value = value;
        }
    }

    focusField(fieldName, select = false)
    {
        const input = this.inputs[fieldName];

        if (!input)
        {
            return;
        }

        input.focus();

        if (select)
        {
            input.select();
        }
    }

    static isSuccess(statusCode)
    {
        return typeof statusCode === 'number' && statusCode >= 200 && statusCode < 300;
    }

    static extractDetail(response)
    {
        if (typeof response === 'string')
        {
            return response || null;
        }

        return response?.detail ?? response?.message ?? null;
    }
}


function initialiseLoginFormHandler()
{
    if (!document.getElementById('LoginForm'))
    {
        return;
    }

    window.loginFormHandler = new LoginFormHandler();
}

if (document.readyState === 'loading')
{
    document.addEventListener('DOMContentLoaded', initialiseLoginFormHandler, { once: true });
}
else
{
    initialiseLoginFormHandler();
}
