class PasswordMigrationFormHandler extends SetInitialPasswordFormHandler
{
    constructor()
    {
        super();
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
            const [statusCode, response] = await authCtrl.MigratePassword(
                this.token,
                this.newPasswordField.value
            );

            if(statusCode !== 200)
            {
                throw new GenericApiFailureException();
            }

            new ToastNotification(
                await Localisation.translate('Password migrated successfully. You can now log in.'),
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
                    await Localisation.translate('Failed to migrate password. Please try again.'),
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
}
