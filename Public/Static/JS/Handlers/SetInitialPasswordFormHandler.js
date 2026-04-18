class SetInitialPasswordFormHandler
{
    constructor()
    {
        this.form = document.getElementById('SetPasswordForm');
        this.submitButton = document.getElementById('SetPasswordButton');
        this.newPasswordField = document.getElementById('new_password');
        this.confirmPasswordField = document.getElementById('confirm_password');
        this.tokenErrorMessage = document.getElementById('TokenErrorMessage');

        // grab token from URL
        const params = new URLSearchParams(window.location.search);
        this.token = params.get('token');

        this.initializeForm();
    }

    initializeForm()
    {
        if (!this.token)
        {
            this.form.style.display = 'none';
            this.tokenErrorMessage.style.display = 'block';
            return;
        }

        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        this.newPasswordField.addEventListener('input', () => this.clearFieldError('NewPassword'));
        this.confirmPasswordField.addEventListener('input', () => this.clearFieldError('ConfirmPassword'));
        this.newPasswordField.focus();
    }

    async handleSubmit(e)
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
            const authCtrl = new AuthenticationController();
            await authCtrl.SetPassword(
                this.token,
                this.newPasswordField.value
            );

            new ToastNotification(
                await Localisation.translate('Password set successfully. You can now log in.'),
                'check-circle',
                'success'
            );

            // brief pause so they see the toast, then redirect
            setTimeout(() => {
                window.location.href = '/login';
            }, 1500);
        }
        catch (error)
        {
            console.error('Set password error:', error);

            if (error.message?.includes('expired') || error.message?.includes('Invalid'))
            {
                this.form.style.display = 'none';
                this.tokenErrorMessage.style.display = 'block';
                return;
            }

            if (error.message)
            {
                new ToastNotification(error.message, 'alert-circle', 'error');
            }
            else
            {
                new ToastNotification(
                    await Localisation.translate('Failed to set password. Please try again.'),
                    'alert-circle',
                    'error'
                );
            }
        }
        finally
        {
            this.setLoadingState(false);
        }
    }

    async validateForm()
    {
        let isValid = true;
        const password = this.newPasswordField.value;
        const confirm = this.confirmPasswordField.value;

        if (!password)
        {
            this.showFieldError('NewPassword', await Localisation.translate('Password is required'));
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

    setLoadingState(loading)
    {
        const buttonText = this.submitButton.querySelector('.button-text');
        const spinner = this.submitButton.querySelector('.loading-spinner');

        if (loading)
        {
            this.submitButton.disabled = true;
            buttonText.style.display = 'none';
            spinner.style.display = 'inline';
        }
        else
        {
            this.submitButton.disabled = false;
            buttonText.style.display = 'inline';
            spinner.style.display = 'none';
        }
    }

    showFieldError(fieldName, message)
    {
        const errorElement = document.getElementById(`ErrorReadout-${fieldName}`);
        if (errorElement)
        {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
    }

    clearFieldError(fieldName)
    {
        const errorElement = document.getElementById(`ErrorReadout-${fieldName}`);
        if (errorElement)
        {
            errorElement.style.display = 'none';
            errorElement.textContent = '';
        }
    }

    clearAllErrors()
    {
        this.clearFieldError('NewPassword');
        this.clearFieldError('ConfirmPassword');
    }
}
