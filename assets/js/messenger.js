(function () {
    'use strict';

    var app = document.getElementById('messengerApp');
    if (!app) return;

    var apiUrl = app.getAttribute('data-api-url') || '';
    var currentUserId = parseInt(app.getAttribute('data-user-id') || '0', 10);
    var currentUserRole = app.getAttribute('data-user-role') || '';
    var startUserId = parseInt(app.getAttribute('data-start-user-id') || '0', 10);

    var inboxList = document.getElementById('messengerInboxList');
    var inboxFilter = document.getElementById('messengerInboxFilter');
    var threadEmpty = document.getElementById('messengerThreadEmpty');
    var threadActive = document.getElementById('messengerThreadActive');
    var threadPeer = document.getElementById('messengerThreadPeer');
    var messagesEl = document.getElementById('messengerMessages');
    var composer = document.getElementById('messengerComposer');
    var bodyInput = document.getElementById('messengerBody');
    var newBtn = document.getElementById('messengerNewBtn');
    var newModal = document.getElementById('messengerNewModal');
    var userSearch = document.getElementById('messengerUserSearch');
    var userResults = document.getElementById('messengerUserResults');
    var editBanner = document.getElementById('messengerEditBanner');
    var editCancelBtn = document.getElementById('messengerEditCancel');
    var sendBtn = composer ? composer.querySelector('.messenger-send-btn') : null;
    var historyPopover = document.getElementById('messengerHistoryPopover');
    var historyBody = document.getElementById('messengerHistoryBody');
    var historyActions = document.getElementById('messengerHistoryActions');
    var historyCloseBtn = document.getElementById('messengerHistoryClose');
    var replyBanner = document.getElementById('messengerReplyBanner');
    var replyPreview = document.getElementById('messengerReplyPreview');
    var replyCancelBtn = document.getElementById('messengerReplyCancel');

    var state = {
        inbox: [],
        activeConversationId: 0,
        activeOtherUser: null,
        currentUser: null,
        lastMessageId: 0,
        lastSync: '',
        editingMessageId: 0,
        replyToMessageId: 0,
        activeHistoryMessageId: 0,
        pollTimer: null,
        searchTimer: null,
        sending: false
    };

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function apiGet(params) {
        var qs = new URLSearchParams(params).toString();
        return fetch(apiUrl + (qs ? '?' + qs : ''), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) { return res.json(); });
    }

    function apiPost(params) {
        var body = new URLSearchParams(params);
        return fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken()
            },
            body: body.toString()
        }).then(function (res) { return res.json(); });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function formatTime(iso) {
        if (!iso) return '';
        var d = new Date(iso.replace(' ', 'T') + 'Z');
        if (isNaN(d.getTime())) d = new Date(iso);
        return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function avatarHtml(user, className) {
        className = className || 'messenger-avatar';
        var name = user.name || ((user.first_name || '') + ' ' + (user.last_name || '')).trim();
        var initials = user.initials || name.split(' ').map(function (p) { return p.charAt(0); }).join('').slice(0, 2).toUpperCase();
        if (user.profile_image) {
            return '<img src="' + escapeHtml(user.profile_image) + '" alt="" class="' + className + '">';
        }
        return '<span class="' + className + ' messenger-avatar-initials">' + escapeHtml(initials) + '</span>';
    }

    function previewText(body, last) {
        if (last && last.is_deleted) {
            return last.is_mine ? 'You unsent a message' : 'Message unsent';
        }
        var text = (body || '').replace(/\s+/g, ' ').trim();
        return text.length > 72 ? text.slice(0, 72) + '…' : text;
    }

    function touchSync() {
        state.lastSync = new Date().toISOString().slice(0, 19).replace('T', ' ');
    }

    function clearEditState() {
        state.editingMessageId = 0;
        if (editBanner) {
            editBanner.hidden = true;
        }
        if (bodyInput && !state.replyToMessageId) {
            bodyInput.placeholder = 'Write a message...';
        }
        if (sendBtn) {
            sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
            sendBtn.setAttribute('aria-label', 'Send message');
        }
    }

    function clearReplyState() {
        state.replyToMessageId = 0;
        if (replyBanner) {
            replyBanner.hidden = true;
        }
        if (replyPreview) {
            replyPreview.innerHTML = '';
        }
    }

    function setEditingMode(messageId, body) {
        if (messageId) {
            clearReplyState();
        }
        state.editingMessageId = messageId || 0;
        if (editBanner) {
            editBanner.hidden = !messageId;
        }
        if (bodyInput) {
            bodyInput.value = body || '';
            bodyInput.placeholder = messageId ? 'Edit your message...' : 'Write a message...';
            if (messageId) {
                bodyInput.focus();
            }
        }
        if (sendBtn) {
            sendBtn.innerHTML = messageId
                ? '<i class="fa-solid fa-check"></i>'
                : '<i class="fa-solid fa-paper-plane"></i>';
            sendBtn.setAttribute('aria-label', messageId ? 'Save edit' : 'Send message');
        }
        if (!messageId) {
            clearEditState();
        }
    }

    function cancelEdit() {
        setEditingMode(0, '');
    }

    function replySenderName(reply) {
        if (!reply) return 'Message';
        if (reply.is_mine) return 'You';
        if (reply.sender_name) return reply.sender_name;
        if (state.activeOtherUser && state.activeOtherUser.name) return state.activeOtherUser.name;
        return 'Message';
    }

    function replyPreviewText(reply) {
        if (!reply) return '';
        if (reply.is_deleted) return 'Message unsent';
        return previewText(reply.body);
    }

    function setReplyMode(messageId, replyData) {
        if (messageId) {
            clearEditState();
            if (editBanner) {
                editBanner.hidden = true;
            }
            if (bodyInput) {
                bodyInput.value = '';
                bodyInput.placeholder = 'Write a message...';
            }
        }
        state.replyToMessageId = messageId || 0;
        if (replyBanner) {
            replyBanner.hidden = !messageId;
        }
        if (replyPreview && replyData) {
            replyPreview.innerHTML =
                '<strong>' + escapeHtml(replySenderName(replyData)) + '</strong>' +
                '<span>' + escapeHtml(replyPreviewText(replyData)) + '</span>';
        } else if (replyPreview) {
            replyPreview.innerHTML = '';
        }
        if (bodyInput && messageId) {
            bodyInput.focus();
        }
        if (!messageId) {
            clearReplyState();
        }
    }

    function cancelReply() {
        setReplyMode(0, null);
    }

    function replyQuoteHtml(msg) {
        if (!msg.reply_to) return '';
        var reply = msg.reply_to;
        return '<button type="button" class="messenger-reply-quote" data-action="jump" data-message-id="' + reply.id + '">' +
            '<span class="messenger-reply-quote-name">' + escapeHtml(replySenderName(reply)) + '</span>' +
            '<span class="messenger-reply-quote-text">' + escapeHtml(replyPreviewText(reply)) + '</span>' +
        '</button>';
    }

    function messageActionsHtml(msg) {
        if (msg.is_deleted) {
            return '';
        }

        var replyBtn =
            '<button type="button" class="messenger-msg-action" data-action="reply" data-message-id="' + msg.id + '" aria-label="Reply to message">' +
                '<i class="fa-solid fa-reply"></i>' +
            '</button>';

        if (!msg.is_mine) {
            return '<div class="messenger-msg-actions is-theirs-actions">' + replyBtn + '</div>';
        }

        return '<div class="messenger-msg-actions">' +
            '<button type="button" class="messenger-msg-action" data-action="edit" data-message-id="' + msg.id + '" aria-label="Edit message">' +
                '<i class="fa-solid fa-pen"></i>' +
            '</button>' +
            '<button type="button" class="messenger-msg-action" data-action="unsend" data-message-id="' + msg.id + '" aria-label="Unsend message">' +
                '<i class="fa-solid fa-trash"></i>' +
            '</button>' +
            '<button type="button" class="messenger-msg-action" data-action="delete-for-me" data-message-id="' + msg.id + '" aria-label="Delete for me">' +
                '<i class="fa-solid fa-eye-slash"></i>' +
            '</button>' +
            replyBtn +
        '</div>';
    }

    function buildMessageRow(msg) {
        var row = document.createElement('div');
        row.className = 'messenger-msg' + (msg.is_mine ? ' is-mine' : ' is-theirs');
        if (msg.is_deleted) {
            row.classList.add('is-unsent');
        }
        row.setAttribute('data-message-id', String(msg.id));

        var bubbleHtml =
            '<div class="messenger-bubble' + (msg.is_mine ? ' is-mine' : ' is-theirs') + '">' +
                replyQuoteHtml(msg) +
                messageBodyHtml(msg) +
                '<time class="messenger-bubble-time">' + messageMetaHtml(msg) + '</time>' +
            '</div>';

        var avatar = avatarHtml(messageSender(msg), 'messenger-msg-avatar');
        var actions = messageActionsHtml(msg);
        row.innerHTML = msg.is_mine
            ? actions + bubbleHtml + avatar
            : avatar + actions + bubbleHtml;
        return row;
    }

    function replyDataFromRow(messageId) {
        var row = messagesEl.querySelector('[data-message-id="' + messageId + '"]');
        if (!row) return { id: messageId, body: '', is_deleted: false, is_mine: false, sender_name: '' };

        var bodyEl = row.querySelector('.messenger-bubble-body');
        var isUnsent = bodyEl && bodyEl.classList.contains('is-unsent');
        var isMine = row.classList.contains('is-mine');

        return {
            id: messageId,
            body: isUnsent ? '' : ((bodyEl && bodyEl.textContent) || ''),
            is_deleted: isUnsent,
            is_mine: isMine,
            sender_name: isMine
                ? ((state.currentUser && state.currentUser.name) || 'You')
                : ((state.activeOtherUser && state.activeOtherUser.name) || '')
        };
    }

    function startReply(messageId) {
        closeEditHistory();
        setReplyMode(messageId, replyDataFromRow(messageId));
    }

    function jumpToMessage(messageId) {
        var row = messagesEl.querySelector('[data-message-id="' + messageId + '"]');
        if (!row) return;
        row.classList.add('is-highlighted');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(function () {
            row.classList.remove('is-highlighted');
        }, 1400);
    }

    function messageMetaHtml(msg) {
        var parts = [escapeHtml(formatTime(msg.created_at))];
        if (msg.is_edited) {
            parts.push(
                '<button type="button" class="messenger-bubble-edited" data-action="history" data-message-id="' +
                msg.id + '" aria-label="View edit history">Edited</button>'
            );
        }
        return parts.join(' · ');
    }

    function messageBodyHtml(msg) {
        if (msg.is_deleted) {
            return '<div class="messenger-bubble-body is-unsent">Message unsent</div>';
        }
        return '<div class="messenger-bubble-body">' + escapeHtml(msg.body) + '</div>';
    }

    function attachMessageActions(row) {
        row.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var messageId = parseInt(btn.getAttribute('data-message-id'), 10);
                var action = btn.getAttribute('data-action');
                var existing = messagesEl.querySelector('[data-message-id="' + messageId + '"] .messenger-bubble-body:not(.is-unsent)');
                if (action === 'edit' && existing) {
                    closeEditHistory();
                    startEdit(messageId, existing.textContent || '');
                } else if (action === 'unsend') {
                    confirmUnsend(messageId);
                } else if (action === 'delete-for-me') {
                    confirmDeleteForMe(messageId);
                } else if (action === 'history') {
                    openEditHistory(messageId, btn);
                } else if (action === 'reply') {
                    startReply(messageId);
                } else if (action === 'jump') {
                    jumpToMessage(messageId);
                }
            });
        });
    }

    function closeEditHistory() {
        if (historyPopover) {
            historyPopover.hidden = true;
        }
        state.activeHistoryMessageId = 0;
    }

    function positionHistoryPopover(anchorEl) {
        if (!historyPopover || !anchorEl) return;
        var rect = anchorEl.getBoundingClientRect();
        var width = 280;
        var left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
        var top = rect.bottom + 8;
        if (top + 220 > window.innerHeight) {
            top = Math.max(12, rect.top - 220);
        }
        historyPopover.style.width = width + 'px';
        historyPopover.style.left = left + 'px';
        historyPopover.style.top = top + 'px';
    }

    function renderEditHistoryPopover(history, anchorEl) {
        if (!historyBody || !historyActions) return;

        var html = '';
        if (history.previous && history.previous.length) {
            history.previous.forEach(function (item, index) {
                var label = index === 0 ? 'Previous version' : 'Earlier version';
                html += '<div class="messenger-history-item">' +
                    '<div class="messenger-history-label">' + label + '</div>' +
                    '<div class="messenger-history-text">' + escapeHtml(item.body) + '</div>' +
                    '<time class="messenger-history-time">' + escapeHtml(formatTime(item.at)) + '</time>' +
                '</div>';
            });
        } else {
            html += '<div class="messenger-history-empty">No previous version was stored for this message.</div>';
        }

        html += '<div class="messenger-history-item is-current">' +
            '<div class="messenger-history-label">Current version</div>' +
            '<div class="messenger-history-text">' + escapeHtml(history.current.body) + '</div>' +
            '<time class="messenger-history-time">' + escapeHtml(formatTime(history.current.at)) + '</time>' +
        '</div>';

        historyBody.innerHTML = html;

        var actionHtml = '';
        if (history.is_mine) {
            actionHtml +=
                '<button type="button" class="btn btn-sm btn-ghost" data-history-action="edit" data-message-id="' + history.message_id + '">' +
                    '<i class="fa-solid fa-pen"></i> Edit' +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-ghost" data-history-action="unsend" data-message-id="' + history.message_id + '">' +
                    '<i class="fa-solid fa-trash"></i> Unsend' +
                '</button>';
        }
        actionHtml +=
            '<button type="button" class="btn btn-sm btn-ghost messenger-history-delete" data-history-action="delete-for-me" data-message-id="' + history.message_id + '">' +
                '<i class="fa-solid fa-eye-slash"></i> Delete for me' +
            '</button>';
        historyActions.innerHTML = actionHtml;

        attachHistoryActions();
        positionHistoryPopover(anchorEl);
        if (historyPopover) {
            historyPopover.hidden = false;
        }
    }

    function attachHistoryActions() {
        if (!historyActions) return;
        historyActions.querySelectorAll('[data-history-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var messageId = parseInt(btn.getAttribute('data-message-id'), 10);
                var action = btn.getAttribute('data-history-action');
                if (action === 'edit') {
                    closeEditHistory();
                    var existing = messagesEl.querySelector('[data-message-id="' + messageId + '"] .messenger-bubble-body:not(.is-unsent)');
                    if (existing) {
                        startEdit(messageId, existing.textContent || '');
                    }
                } else if (action === 'unsend') {
                    closeEditHistory();
                    confirmUnsend(messageId);
                } else if (action === 'delete-for-me') {
                    closeEditHistory();
                    confirmDeleteForMe(messageId);
                }
            });
        });
    }

    function openEditHistory(messageId, anchorEl) {
        state.activeHistoryMessageId = messageId;
        if (historyBody) {
            historyBody.innerHTML = '<div class="messenger-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';
        }
        if (historyActions) {
            historyActions.innerHTML = '';
        }
        positionHistoryPopover(anchorEl);
        if (historyPopover) {
            historyPopover.hidden = false;
        }

        apiGet({ action: 'edit_history', message_id: String(messageId) }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Failed to load edit history');
            renderEditHistoryPopover(data.history, anchorEl);
        }).catch(function (err) {
            closeEditHistory();
            window.alert(err.message || 'Failed to load edit history.');
        });
    }

    function removeMessageRow(messageId) {
        var row = messagesEl.querySelector('[data-message-id="' + messageId + '"]');
        if (row) {
            row.remove();
        }
    }

    function confirmDeleteForMe(messageId) {
        showConfirm('Delete this message for you? The other person will still see it.', function () {
            deleteForMe(messageId);
        });
    }

    function deleteForMe(messageId) {
        if (state.sending) return;
        state.sending = true;

        apiPost({
            action: 'delete_for_me',
            message_id: String(messageId)
        }).then(function (data) {
            state.sending = false;
            if (!data.ok) throw new Error(data.error || 'Failed to delete message');
            if (state.editingMessageId === messageId) {
                cancelEdit();
            }
            removeMessageRow(messageId);
            loadInbox();
        }).catch(function (err) {
            state.sending = false;
            window.alert(err.message || 'Failed to delete message.');
        });
    }

    function updateMessageRow(msg) {
        var row = messagesEl.querySelector('[data-message-id="' + msg.id + '"]');
        if (!row) {
            renderMessages([msg], true);
            return;
        }

        var next = buildMessageRow(msg);
        row.replaceWith(next);
        attachMessageActions(next);
    }

    function applyMessageUpdates(updates) {
        if (!updates || !updates.length) return;
        updates.forEach(updateMessageRow);
        loadInbox();
    }

    function updateUnreadBadge(count) {
        document.querySelectorAll('[data-messenger-unread]').forEach(function (el) {
            if (count > 0) {
                el.textContent = count > 99 ? '99+' : String(count);
                el.hidden = false;
            } else {
                el.hidden = true;
            }
        });
    }

    function renderInbox() {
        var filter = (inboxFilter && inboxFilter.value || '').toLowerCase().trim();
        var items = state.inbox.filter(function (row) {
            if (!filter) return true;
            var u = row.other_user || {};
            var hay = (u.name + ' ' + u.email + ' ' + (u.role_label || '')).toLowerCase();
            return hay.indexOf(filter) !== -1;
        });

        if (!items.length) {
            inboxList.innerHTML = '<div class="messenger-inbox-empty">No conversations yet.</div>';
            return;
        }

        inboxList.innerHTML = items.map(function (row) {
            var u = row.other_user || {};
            var active = row.conversation_id === state.activeConversationId ? ' is-active' : '';
            var unread = row.unread_count > 0 ? ' has-unread' : '';
            var last = row.last_message;
            var preview = last ? (last.is_mine ? 'You: ' : '') + previewText(last.body, last) : 'No messages yet';
            var time = last ? formatTime(last.created_at) : '';
            return '<button type="button" class="messenger-inbox-item' + active + unread + '" data-conversation-id="' + row.conversation_id + '">' +
                avatarHtml(u) +
                '<span class="messenger-inbox-meta">' +
                    '<span class="messenger-inbox-top">' +
                        '<span class="messenger-inbox-name">' + escapeHtml(u.name) + '</span>' +
                        (time ? '<span class="messenger-inbox-time">' + escapeHtml(time) + '</span>' : '') +
                    '</span>' +
                    '<span class="messenger-inbox-preview">' + escapeHtml(preview) + '</span>' +
                '</span>' +
                (row.unread_count > 0 ? '<span class="messenger-inbox-badge">' + row.unread_count + '</span>' : '') +
            '</button>';
        }).join('');

        inboxList.querySelectorAll('[data-conversation-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openThread(parseInt(btn.getAttribute('data-conversation-id'), 10));
            });
        });
    }

    function loadInbox(selectId) {
        return apiGet({ action: 'inbox' }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Failed to load inbox');
            state.inbox = data.inbox || [];
            state.currentUser = data.me || state.currentUser;
            renderInbox();
            if (selectId) {
                openThread(selectId);
            }
            var total = state.inbox.reduce(function (sum, row) { return sum + (row.unread_count || 0); }, 0);
            updateUnreadBadge(total);
        }).catch(function (err) {
            inboxList.innerHTML = '<div class="messenger-inbox-empty messenger-error">' + escapeHtml(err.message) + '</div>';
        });
    }

    function renderPeer(user) {
        threadPeer.innerHTML =
            avatarHtml(user, 'messenger-thread-avatar') +
            '<span class="messenger-thread-peer-meta">' +
                '<strong>' + escapeHtml(user.name) + '</strong>' +
                '<span class="messenger-thread-role">' + escapeHtml(user.role_label || '') + '</span>' +
            '</span>';
    }

    function messageSender(msg) {
        if (msg.sender && (msg.sender.profile_image || msg.sender.initials || msg.sender.name)) {
            return msg.sender;
        }
        if (msg.is_mine && state.currentUser) {
            return state.currentUser;
        }
        if (!msg.is_mine && state.activeOtherUser) {
            return state.activeOtherUser;
        }
        return { name: '', initials: '?' };
    }

    function renderMessages(messages, append) {
        if (!append) {
            messagesEl.innerHTML = '';
        }
        messages.forEach(function (msg) {
            if (msg.id <= 0) return;
            if (messagesEl.querySelector('[data-message-id="' + msg.id + '"]')) return;

            var row = buildMessageRow(msg);
            messagesEl.appendChild(row);
            attachMessageActions(row);

            if (msg.id > state.lastMessageId) {
                state.lastMessageId = msg.id;
            }
        });

        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function markRead() {
        if (!state.activeConversationId || state.lastMessageId <= 0) return;
        apiPost({
            action: 'read',
            conversation_id: String(state.activeConversationId),
            message_id: String(state.lastMessageId)
        }).then(function (data) {
            if (data.ok) {
                updateUnreadBadge(data.unread_count || 0);
                state.inbox = state.inbox.map(function (row) {
                    if (row.conversation_id === state.activeConversationId) {
                        row.unread_count = 0;
                    }
                    return row;
                });
                renderInbox();
            }
        });
    }

    function pollThread() {
        if (!state.activeConversationId) return;
        var params = {
            action: 'thread',
            conversation_id: String(state.activeConversationId),
            since_id: String(state.lastMessageId)
        };
        if (state.lastSync) {
            params.sync_since = state.lastSync;
        }
        apiGet(params).then(function (data) {
            if (!data.ok) return;
            touchSync();
            if (data.updates && data.updates.length) {
                applyMessageUpdates(data.updates);
            }
            if (data.messages && data.messages.length) {
                renderMessages(data.messages, true);
                markRead();
                loadInbox();
            }
        });
    }

    function startPolling() {
        stopPolling();
        state.pollTimer = window.setInterval(pollThread, 3000);
    }

    function stopPolling() {
        if (state.pollTimer) {
            window.clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function openThread(conversationId) {
        state.activeConversationId = conversationId;
        state.lastMessageId = 0;
        state.lastSync = '';
        cancelEdit();
        cancelReply();
        closeEditHistory();
        threadEmpty.hidden = true;
        threadActive.hidden = false;
        messagesEl.innerHTML = '<div class="messenger-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';
        renderInbox();

        apiGet({ action: 'thread', conversation_id: String(conversationId) }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Failed to load conversation');
            state.activeOtherUser = data.conversation.other_user;
            renderPeer(state.activeOtherUser);
            messagesEl.innerHTML = '';
            renderMessages(data.messages || [], false);
            touchSync();
            markRead();
            startPolling();
            bodyInput.focus();
        }).catch(function (err) {
            messagesEl.innerHTML = '<div class="messenger-error">' + escapeHtml(err.message) + '</div>';
        });
    }

    function startEdit(messageId, body) {
        setEditingMode(messageId, body);
    }

    function saveEdit(body) {
        if (state.sending || !state.editingMessageId) return;
        state.sending = true;

        apiPost({
            action: 'edit',
            message_id: String(state.editingMessageId),
            body: body
        }).then(function (data) {
            state.sending = false;
            if (!data.ok) throw new Error(data.error || 'Failed to edit message');
            if (data.message) {
                updateMessageRow(data.message);
            }
            cancelEdit();
            touchSync();
            loadInbox();
        }).catch(function (err) {
            state.sending = false;
            window.alert(err.message || 'Failed to edit message.');
        });
    }

    function confirmUnsend(messageId) {
        showConfirm('Unsend this message?', function () {
            unsendMessage(messageId);
        });
    }

    function unsendMessage(messageId) {
        if (state.sending) return;
        state.sending = true;

        apiPost({
            action: 'unsend',
            message_id: String(messageId)
        }).then(function (data) {
            state.sending = false;
            if (!data.ok) throw new Error(data.error || 'Failed to unsend message');
            if (state.editingMessageId === messageId) {
                cancelEdit();
            }
            if (data.message) {
                updateMessageRow(data.message);
            }
            touchSync();
            loadInbox();
        }).catch(function (err) {
            state.sending = false;
            window.alert(err.message || 'Failed to unsend message.');
        });
    }

    function teacherWarningKey(conversationId) {
        return 'messenger_teacher_warn_' + conversationId;
    }

    function needsTeacherWarning() {
        return currentUserRole === 'student'
            && state.activeOtherUser
            && state.activeOtherUser.role === 'teacher'
            && !sessionStorage.getItem(teacherWarningKey(state.activeConversationId));
    }

    function sendMessage(body) {
        if (state.sending || !state.activeConversationId) return;
        state.sending = true;

        apiPost({
            action: 'send',
            conversation_id: String(state.activeConversationId),
            body: body,
            reply_to_message_id: state.replyToMessageId ? String(state.replyToMessageId) : ''
        }).then(function (data) {
            state.sending = false;
            if (!data.ok) throw new Error(data.error || 'Failed to send');
            if (data.message) {
                renderMessages([data.message], true);
            }
            bodyInput.value = '';
            cancelReply();
            touchSync();
            markRead();
            loadInbox();
        }).catch(function (err) {
            state.sending = false;
            window.alert(err.message || 'Failed to send message.');
        });
    }

    function handleComposerSubmit(e) {
        e.preventDefault();
        var body = (bodyInput.value || '').trim();
        if (!body) return;

        if (state.editingMessageId) {
            saveEdit(body);
            return;
        }

        if (!state.activeConversationId) return;

        if (needsTeacherWarning()) {
            showConfirm('You are messaging a teacher.', function () {
                sessionStorage.setItem(teacherWarningKey(state.activeConversationId), '1');
                sendMessage(body);
            });
            return;
        }

        sendMessage(body);
    }

    function openNewModal() {
        newModal.hidden = false;
        userSearch.value = '';
        userResults.innerHTML = '';
        userSearch.focus();
    }

    function closeNewModal() {
        newModal.hidden = true;
    }

    function renderUserResults(users) {
        if (!users.length) {
            userResults.innerHTML = '<div class="messenger-user-empty">No users found.</div>';
            return;
        }
        userResults.innerHTML = users.map(function (u) {
            return '<button type="button" class="messenger-user-item" data-user-id="' + u.id + '">' +
                avatarHtml(u) +
                '<span class="messenger-user-meta">' +
                    '<strong>' + escapeHtml(u.name) + '</strong>' +
                    '<span>' + escapeHtml(u.role_label || '') + ' · ' + escapeHtml(u.email) + '</span>' +
                '</span>' +
            '</button>';
        }).join('');

        userResults.querySelectorAll('[data-user-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                startConversation(parseInt(btn.getAttribute('data-user-id'), 10));
            });
        });
    }

    function startConversation(recipientId) {
        apiPost({ action: 'start', recipient_id: String(recipientId) }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Failed to start conversation');
            closeNewModal();
            return loadInbox(data.conversation.conversation_id);
        }).catch(function (err) {
            userResults.innerHTML = '<div class="messenger-user-empty messenger-error">' + escapeHtml(err.message) + '</div>';
        });
    }

    function searchUsers() {
        var q = (userSearch.value || '').trim();
        if (q.length < 1) {
            userResults.innerHTML = '';
            return;
        }
        apiGet({ action: 'search_users', q: q }).then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Search failed');
            renderUserResults(data.users || []);
        }).catch(function (err) {
            userResults.innerHTML = '<div class="messenger-user-empty messenger-error">' + escapeHtml(err.message) + '</div>';
        });
    }

    if (replyCancelBtn) {
        replyCancelBtn.addEventListener('click', cancelReply);
    }
    if (editCancelBtn) {
        editCancelBtn.addEventListener('click', cancelEdit);
    }
    if (historyCloseBtn) {
        historyCloseBtn.addEventListener('click', closeEditHistory);
    }
    document.addEventListener('click', function (e) {
        if (!historyPopover || historyPopover.hidden) return;
        if (historyPopover.contains(e.target)) return;
        if (e.target.closest('[data-action="history"]')) return;
        closeEditHistory();
    });
    if (composer) {
        composer.addEventListener('submit', handleComposerSubmit);
    }
    if (bodyInput) {
        bodyInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                composer.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    }
    if (inboxFilter) {
        inboxFilter.addEventListener('input', renderInbox);
    }
    if (newBtn) {
        newBtn.addEventListener('click', openNewModal);
    }
    if (newModal) {
        newModal.querySelectorAll('[data-messenger-modal-close]').forEach(function (el) {
            el.addEventListener('click', closeNewModal);
        });
    }
    if (userSearch) {
        userSearch.addEventListener('input', function () {
            window.clearTimeout(state.searchTimer);
            state.searchTimer = window.setTimeout(searchUsers, 250);
        });
    }

    window.addEventListener('beforeunload', stopPolling);

    loadInbox().then(function () {
        if (startUserId > 0 && startUserId !== currentUserId) {
            startConversation(startUserId);
        }
    });
})();
