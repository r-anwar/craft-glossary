document.addEventListener('DOMContentLoaded', () => {
    const popoverMarginBottom = 18;

    const applyAndShowPopoverstyles = function (trigger, popover) {
        if (!popover || !trigger) return;

        const triggerRect = trigger.getBoundingClientRect();
        const popoverRect = popover.getBoundingClientRect();

        const headerElement = document.querySelector('wm-header[role="banner"]');

        let headerRect = null;
        if(headerElement) {
            headerRect = headerElement.getBoundingClientRect();
        }

        popover.style.left = (triggerRect.left + triggerRect.width / 2) + 'px';
        popover.style.transform = 'translateX(-50%)';

        if (triggerRect.top - (headerRect?.height > 0 ? headerRect?.height: 0) - popoverMarginBottom < 0) {
            // nicht genug Platz oben, darunter anzeigen
            popover.style.top = (triggerRect.bottom +  popoverMarginBottom) + 'px';
        } else {
            popover.style.top = (triggerRect.top - triggerRect.height - popoverMarginBottom) + 'px';
        }

        /*
        console.dir(
            [triggerRect.top, triggerRect.height, popoverRect.height, popoverMarginBottom]
        );
         */

        popover.showPopover();
    };

    const applyPopoverPositioning = function () {
        const glossaryTerms = document.querySelectorAll('.glossary');

        glossaryTerms.forEach(trigger => {
            const targetId = trigger.dataset.glossaryTermId;
            const popover = document.getElementById(targetId);

            if (!popover) return;

            // Zeige Popover beim Hover oder Fokus
            trigger.addEventListener('mouseenter', () => {
                applyAndShowPopoverstyles(trigger, popover);
            });

            trigger.addEventListener('mouseleave', () => {
                popover.hidePopover();
            });

            // Accessibility: Fokus anzeigen
            trigger.addEventListener('focus', () => {
                applyAndShowPopoverstyles(trigger, popover);
            });

            trigger.addEventListener('blur', () => {
                popover.hidePopover();
            });
        });
    }

    if (!('popover' in HTMLElement.prototype)) {
        document.querySelectorAll('span.glossary').forEach(node => {
            if( node ) node.classList.add('no-popover-support');

            /*
            simulate browser with no popover support.
            document.querySelectorAll('[popover]').forEach(el => {
                el.removeAttribute('popover');
            });
            */
        });
    } else {
        applyPopoverPositioning();
    }
});