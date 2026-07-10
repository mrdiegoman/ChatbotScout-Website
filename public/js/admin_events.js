(function () {
    function call(name, ...args) {
        if (typeof window[name] === 'function') {
            return window[name](...args);
        }
        return undefined;
    }

    function bool(value) {
        return value === 'true' || value === '1';
    }

    const clickHandlers = {
        refreshMessages: (button, event) => {
            event.stopPropagation();
            return call('refreshMessages', button);
        },
        refreshReviews: (button, event) => {
            event.stopPropagation();
            return call('refreshReviews', button);
        },
        refreshSubmissions: (button, event) => {
            event.stopPropagation();
            return call('refreshSubmissions', button);
        },
        scrollToOldest: button => call('scrollToOldest', button.dataset.section),
        scrollToOldestSubmissions: () => call('scrollToOldestSubmissions'),
        updateStatus: button => call('updateStatus', button.dataset.id, button.dataset.status, button),
        toggleHelpful: button => call('toggleHelpful', button.dataset.id, button),
        toggleSection: button => call('toggleSection', button, button.dataset.section),
        sendReply: button => call('sendReply', button.dataset.id, button),
        saveNotes: button => call('saveNotes', button.dataset.id, button),
        loadMoreMessages: button => call('loadMoreMessages', button),
        loadMoreReviews: button => call('loadMoreReviews', button),
        loadMoreSubmissions: button => call('loadMoreSubmissions', button),
        updateReviewStatus: button => call('updateReviewStatus', button.dataset.id, button.dataset.status, button),
        saveReviewNotes: button => call('saveReviewNotes', button.dataset.id, button),
        blockUser: button => call('blockUser', button.dataset.userId, button.dataset.blockType, button),
        unblockUser: button => call('unblockUser', button.dataset.userId, button.dataset.blockType, button),
        openAdminChatbotCreatePopup: () => call('openAdminChatbotCreatePopup'),
        closeAdminChatbotCreatePopup: () => call('closeAdminChatbotCreatePopup'),
        searchAccountForChatbot: button => call('searchAccountForChatbot', button.dataset.id, button),
        assignChatbotToAccount: button => call('assignChatbotToAccount', button.dataset.chatbotId, button.dataset.userId, button),
        acceptSubmission: button => call('acceptSubmission', button.dataset.id),
        reviewSubmission: button => call('reviewSubmission', button.dataset.id, button),
        rejectSubmission: button => call('rejectSubmission', button.dataset.id, button),
        removeSubmission: button => call('removeSubmission', button.dataset.id, button),
        saveSubmissionNotes: button => call('saveSubmissionNotes', button.dataset.id, button),
        openSubmissionEditPopup: button => call('openSubmissionEditPopup', button.dataset.id),
        openSubmissionEditFromAccept: button => call('openSubmissionEditFromAccept', button.dataset.id),
        closeSubmissionEditPopup: () => call('closeSubmissionEditPopup'),
        addSubmissionEditItem: button => call('addSubmissionEditItem', button.dataset.kind),
        deleteSubmissionEditFeature: (button, event) => call('deleteSubmissionEditFeature', button.dataset.featureId, button, event),
        deleteSubmissionEditNewItem: (button, event) => call('deleteSubmissionEditNewItem', button, event),
        showFeatureInUse: button => call('showNotification', `This feature is used by ${button.dataset.usageCount} chatbot(s).`, 'info'),
        closeAcceptPopup: () => call('closeAcceptPopup'),
        confirmAccept: button => call('confirmAccept', button.dataset.id, button),
        resolveChangePopup: button => call('resolveChangePopup', bool(button.dataset.confirmed)),
        resolveDelistPopup: button => call('resolveDelistPopup', bool(button.dataset.confirmed)),
        resolveBlockPopup: button => call('resolveBlockPopup', bool(button.dataset.confirmed)),
        addNotificationEmail: () => call('addNotificationEmail'),
        deleteNotificationEmail: button => call('deleteNotificationEmail', button.dataset.id),
        openNotifEmailsPopup: () => call('openNotifEmailsPopup'),
        closeNotifEmailsPopup: () => call('closeNotifEmailsPopup'),
        saveAdminSettings: button => call('saveAdminSettings', button),
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-admin-action]');
        if (!button) {
            return;
        }

        const handler = clickHandlers[button.dataset.adminAction];
        if (!handler) {
            return;
        }

        event.preventDefault();
        handler(button, event);
    });

    document.addEventListener('submit', event => {
        const form = event.target.closest('[data-admin-submit]');
        if (!form) {
            return;
        }

        event.preventDefault();
        const action = form.dataset.adminSubmit;
        if (action === 'createAdminChatbot') {
            return call('createAdminChatbot', event);
        }
        if (action === 'saveSubmissionEdit') {
            return call('saveSubmissionEdit', event, form.dataset.chatbotId);
        }
    });

    document.addEventListener('change', event => {
        const input = event.target.closest('[data-admin-change]');
        if (!input) {
            return;
        }

        if (input.dataset.adminChange === 'handleSubmissionEditOptionToggle') {
            call('handleSubmissionEditOptionToggle', input);
        }
    });
})();
