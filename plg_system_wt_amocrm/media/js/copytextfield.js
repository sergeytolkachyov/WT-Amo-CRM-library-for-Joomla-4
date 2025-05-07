if (!window.Joomla) {
    throw new Error('Joomla API was not properly initialised!');
}

const copyToClipboardFallback = input => {
    input.focus();
    input.select();

    try {
        const copy = document.execCommand('copy');

        if (copy) {
            Joomla.renderMessages({
                message: [Joomla.Text._('Copied!')]
            });
        } else {
            Joomla.renderMessages({
                error: [Joomla.Text._('Copy failed!')]
            });
        }
    } catch (err) {
        Joomla.renderMessages({
            error: [err]
        });
    }
};

const copyToClipboard = () => {
    const buttons = document.querySelectorAll('[data-webtolk-amocrm-copy-field-value]');

    buttons.forEach((button)=>{
        button.addEventListener('click', ({
                                              currentTarget
                                          }) => {
            const input = currentTarget.previousElementSibling;

            if (!navigator.clipboard) {
                copyToClipboardFallback(input);
                return;
            }

            navigator.clipboard.writeText(input.value).then(() => {
                Joomla.renderMessages({
                    message: [Joomla.Text._('Copied!')]
                });
            }, () => {
                Joomla.renderMessages({
                    error: [Joomla.Text._('Copy fail!')]
                });
            });
        });
    });
};

const onBoot = () => {
    copyToClipboard();
    document.removeEventListener('DOMContentLoaded', onBoot);
};

document.addEventListener('DOMContentLoaded', onBoot);