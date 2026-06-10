<?php
/** @var array $user */
/** @var int $userId */
/** @var int $startUserId */
?>
<div
    class="messenger-page"
    id="messengerApp"
    data-api-url="<?= e(url('api/messages.php')) ?>"
    data-user-id="<?= (int) $userId ?>"
    data-user-role="<?= e($user['role'] ?? '') ?>"
    data-start-user-id="<?= (int) $startUserId ?>"
>
    <div class="messenger-layout panel">
        <aside class="messenger-inbox" aria-label="Conversations">
            <div class="messenger-inbox-header">
                <h2 class="messenger-inbox-title"><i class="fa-solid fa-comments"></i> Messages</h2>
                <button type="button" class="btn btn-sm btn-primary" id="messengerNewBtn">
                    <i class="fa-solid fa-pen-to-square"></i> New
                </button>
            </div>
            <div class="messenger-inbox-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="messengerInboxFilter" placeholder="Search conversations..." autocomplete="off">
            </div>
            <div class="messenger-inbox-list" id="messengerInboxList">
                <div class="messenger-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </aside>

        <section class="messenger-thread" aria-label="Conversation">
            <div class="messenger-thread-empty" id="messengerThreadEmpty">
                <i class="fa-regular fa-comment-dots"></i>
                <h3>Select a conversation</h3>
                <p>Choose someone from your inbox or start a new message.</p>
            </div>

            <div class="messenger-thread-active" id="messengerThreadActive" hidden>
                <header class="messenger-thread-header">
                    <div class="messenger-thread-peer" id="messengerThreadPeer"></div>
                </header>
                <div class="messenger-messages" id="messengerMessages"></div>
                <div class="messenger-reply-banner" id="messengerReplyBanner" hidden>
                    <div class="messenger-reply-banner-content">
                        <span class="messenger-reply-banner-label"><i class="fa-solid fa-reply"></i> Replying to</span>
                        <div class="messenger-reply-banner-preview" id="messengerReplyPreview"></div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" id="messengerReplyCancel">Cancel</button>
                </div>
                <div class="messenger-edit-banner" id="messengerEditBanner" hidden>
                    <span><i class="fa-solid fa-pen"></i> Editing message</span>
                    <button type="button" class="btn btn-ghost btn-sm" id="messengerEditCancel">Cancel</button>
                </div>
                <form class="messenger-composer" id="messengerComposer">
                    <label class="sr-only" for="messengerBody">Message</label>
                    <textarea id="messengerBody" rows="2" maxlength="2000" placeholder="Write a message..." required></textarea>
                    <button type="submit" class="btn btn-primary messenger-send-btn" aria-label="Send message">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </section>
    </div>
</div>

<div class="messenger-history-popover" id="messengerHistoryPopover" hidden role="dialog" aria-labelledby="messengerHistoryTitle">
    <header class="messenger-history-header">
        <h4 id="messengerHistoryTitle">Edit history</h4>
        <button type="button" class="btn btn-ghost btn-sm" id="messengerHistoryClose" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </header>
    <div class="messenger-history-body" id="messengerHistoryBody"></div>
    <footer class="messenger-history-actions" id="messengerHistoryActions"></footer>
</div>

<div class="messenger-modal" id="messengerNewModal" hidden>
    <div class="messenger-modal-backdrop" data-messenger-modal-close></div>
    <div class="messenger-modal-panel panel" role="dialog" aria-labelledby="messengerNewTitle" aria-modal="true">
        <header class="messenger-modal-header">
            <h3 id="messengerNewTitle">New message</h3>
            <button type="button" class="btn btn-ghost btn-sm" data-messenger-modal-close aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>
        <div class="messenger-modal-body">
            <label for="messengerUserSearch">To</label>
            <div class="messenger-user-search-wrap">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="messengerUserSearch" placeholder="Search by name or email..." autocomplete="off">
            </div>
            <div class="messenger-user-results" id="messengerUserResults"></div>
        </div>
    </div>
</div>
