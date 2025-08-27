document.addEventListener('DOMContentLoaded', () => {
    const glossaryTerms = document.querySelectorAll('.glossary');
    
    let timeoutID

    glossaryTerms.forEach(trigger => {
        const targetId = trigger.dataset.glossaryTermId;
        const popover = document.getElementById(targetId);
        
        if (!popover) return;

        // Zeige Popover beim Hover oder Fokus
        trigger.addEventListener('mouseenter', () => {
            popover.showPopover();
        });

        trigger.addEventListener('mouseleave', () => {
            clearTimeout(timeoutID);
            timeoutID = setTimeout(function() {
                popover.hidePopover();
            }, 1000);
        });

        popover.addEventListener('mouseenter', () => {
                clearTimeout(timeoutID);
        });

        popover.addEventListener('mouseleave', () => {
            popover.hidePopover();
        });
    });

});