(() => {
    document.addEventListener('DOMContentLoaded', () => {
        // Get the elements

        const entities_links = document.querySelectorAll('[data-entity-id]');
        console.log(entities_links);
        // Listen for click event
        entities_links.forEach((element) => {
            element.addEventListener('click', event => {
                event.preventDefault();
                const {
                    target
                } = event;

                let data = {
                    'messageType' : 'joomla:content-select',
                    'id' : target.getAttribute('data-entity-id'),
                    'title' : target.getAttribute('data-entity-title')
                };
                console.log(data);
                window.parent.postMessage(data);
            });
        });
    });
})();