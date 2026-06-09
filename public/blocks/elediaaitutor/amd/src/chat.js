// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * eLeDia.ai Tutor chat client.
 *
 * Owns all chat-panel behaviour: composing and sending messages over Moodle's
 * authenticated AJAX channel, rendering bubbles, streaming/typing state, copy,
 * retry, clear, history, and the docked/modal/fullscreen display modes. No
 * secrets are ever handled here — the browser only ever talks to Moodle, which
 * brokers the RAG/Tutor call server-side.
 *
 * @module     block_elediaaitutor/chat
 * @copyright  2026 eLeDia GmbH, Berlin
 * @author     Christopher Reimann <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_strings as getStrings} from 'core/str';

/** @var {object} Cached localised strings. */
let strings = {};

/** @var {Array} Focusable selector for the focus trap. */
const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'textarea:not([disabled])',
    'input:not([disabled])', '[tabindex]:not([tabindex="-1"])'
].join(',');

/**
 * Per-instance controller for one block.
 */
class TutorChat {
    /**
     * @param {HTMLElement} root The .elediaaitutor-root element.
     * @param {object} config Non-secret configuration from PHP.
     */
    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.conversationId = '';
        this.busy = false;
        this.lastUserMessage = '';
        this.previousFocus = null;
        this.historyLoaded = false;

        this.panel = root.querySelector('[data-region="panel"]');
        this.window = root.querySelector('.elediaaitutor-window');
        this.log = root.querySelector('[data-region="log"]');
        this.input = root.querySelector('[data-region="input"]');
        this.status = root.querySelector('[data-region="status"]');
        this.composer = root.querySelector('[data-region="composer"]');
        this.backdrop = root.querySelector('.elediaaitutor-backdrop');
        this.historyPanel = root.querySelector('[data-region="history"]');

        this.bind();
    }

    /**
     * Attach event listeners.
     *
     * @return {void}
     */
    bind() {
        // Delegate clicks for the in-panel action buttons. These listeners live
        // on the panel (not the root) because overlay modes portal the panel to
        // <body> -- i.e. out of the root -- so root-level delegation would stop
        // firing once the panel moves.
        this.panel.addEventListener('click', (e) => {
            const actionEl = e.target.closest('[data-action]');
            if (!actionEl || !this.panel.contains(actionEl)) {
                return;
            }
            this.handleAction(actionEl.getAttribute('data-action'), actionEl, e);
        });

        // The launch button is the only action outside the panel (it stays in
        // the block while the panel is portalled), so bind it directly.
        const launch = this.root.querySelector('[data-action="launch"]');
        if (launch) {
            launch.addEventListener('click', () => this.open());
        }

        if (this.composer) {
            this.composer.addEventListener('submit', (e) => {
                e.preventDefault();
                this.send();
            });
        }

        if (this.input) {
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.send();
                }
            });
            // Auto-grow the textarea.
            this.input.addEventListener('input', () => this.autoGrow());
        }

        // Escape closes overlay modes and Tab is trapped while open. Bound to the
        // panel so it keeps working after the panel is portalled out of the root.
        this.panel.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOverlay() && !this.isHidden()) {
                this.close();
            } else if (e.key === 'Tab' && this.isOverlay() && !this.isHidden()) {
                this.trapFocus(e);
            }
        });
    }

    /**
     * Route a delegated action.
     *
     * @param {string} action The data-action value.
     * @param {HTMLElement} el The clicked element.
     * @return {void}
     */
    handleAction(action, el) {
        switch (action) {
            case 'launch': this.open(); break;
            case 'close': this.close(); break;
            case 'send': this.send(); break;
            case 'newconversation': this.newConversation(); break;
            case 'history': this.toggleHistory(); break;
            case 'copy': this.copyAnswer(el); break;
            case 'retry': this.retry(el); break;
            case 'open-conversation': this.openConversation(el); break;
            case 'delete-conversation': this.deleteConversation(el); break;
            default: break;
        }
    }

    /**
     * @return {boolean} Whether the panel uses an overlay display mode.
     */
    isOverlay() {
        return this.config.displaymode !== 'embedded';
    }

    /**
     * @return {boolean} Whether the panel is currently hidden.
     */
    isHidden() {
        return this.panel.hasAttribute('hidden');
    }

    /**
     * Open the panel (overlay modes). Portals it to document.body so it cannot
     * be clipped by block-container overflow or z-index, then traps focus.
     *
     * @return {void}
     */
    open() {
        if (!this.isOverlay()) {
            return;
        }
        this.previousFocus = document.activeElement;
        if (this.panel.parentNode !== document.body) {
            document.body.appendChild(this.panel);
        }
        this.panel.classList.add('elediaaitutor-' + this.config.displaymode);
        this.panel.removeAttribute('hidden');
        if (this.backdrop && this.config.displaymode === 'modal') {
            this.backdrop.removeAttribute('hidden');
        }
        document.body.classList.toggle('elediaaitutor-noscroll',
            this.config.displaymode === 'fullscreen' || this.config.displaymode === 'modal');
        const launch = this.root.querySelector('[data-action="launch"]');
        if (launch) {
            launch.setAttribute('aria-expanded', 'true');
        }
        window.setTimeout(() => this.input && this.input.focus(), 50);
    }

    /**
     * Close the panel (overlay modes) and restore focus.
     *
     * @return {void}
     */
    close() {
        if (!this.isOverlay()) {
            return;
        }
        this.panel.setAttribute('hidden', 'hidden');
        if (this.backdrop) {
            this.backdrop.setAttribute('hidden', 'hidden');
        }
        document.body.classList.remove('elediaaitutor-noscroll');
        const launch = this.root.querySelector('[data-action="launch"]');
        if (launch) {
            launch.setAttribute('aria-expanded', 'false');
        }
        if (this.previousFocus && typeof this.previousFocus.focus === 'function') {
            this.previousFocus.focus();
        }
    }

    /**
     * Keep keyboard focus inside the open overlay.
     *
     * @param {KeyboardEvent} e The tab event.
     * @return {void}
     */
    trapFocus(e) {
        const nodes = Array.from(this.window.querySelectorAll(FOCUSABLE))
            .filter((n) => n.offsetParent !== null);
        if (!nodes.length) {
            return;
        }
        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    /**
     * Resize the textarea to fit its content (bounded by CSS max-height).
     *
     * @return {void}
     */
    autoGrow() {
        this.input.style.height = 'auto';
        this.input.style.height = Math.min(this.input.scrollHeight, 160) + 'px';
    }

    /**
     * Send the current composer message.
     *
     * @return {void}
     */
    send() {
        if (this.busy) {
            return;
        }
        const message = (this.input.value || '').trim();
        if (!message) {
            return;
        }
        if (message.length > this.config.maxlength) {
            this.setStatus(strings.toolong);
            return;
        }
        this.lastUserMessage = message;
        this.input.value = '';
        this.autoGrow();
        this.appendUser(message);
        this.dispatch(message);
    }

    /**
     * Retry the previous failed message.
     *
     * @param {HTMLElement} el The retry button.
     * @return {void}
     */
    retry(el) {
        const message = this.lastUserMessage;
        if (!message || this.busy) {
            return;
        }
        const bubble = el.closest('[data-region="message"]');
        if (bubble) {
            bubble.remove();
        }
        this.dispatch(message);
    }

    /**
     * Perform the AJAX send and render the response.
     *
     * @param {string} message The user message.
     * @return {void}
     */
    dispatch(message) {
        this.busy = true;
        this.showTyping();
        this.setStatus(strings.thinking);

        Ajax.call([{
            methodname: 'block_elediaaitutor_send_message',
            args: {
                contextid: this.config.contextid,
                message: message,
                courseid: this.config.courseid || 0,
                conversationid: this.conversationId || ''
            }
        }])[0].then((response) => {
            this.hideTyping();
            this.busy = false;
            this.setStatus('');
            if (response.conversationid) {
                this.conversationId = response.conversationid;
                this.historyLoaded = false;
            }
            return this.appendAssistant(response.answerhtml, response.sources || [], response.iserror);
        }).catch((error) => {
            this.hideTyping();
            this.busy = false;
            this.setStatus(strings.failed);
            this.appendFailure(error);
            return null;
        });
    }

    /**
     * Append a user bubble.
     *
     * @param {string} text The message text.
     * @return {Promise}
     */
    appendUser(text) {
        return this.appendMessage({isuser: true, text: text, sendername: strings.you});
    }

    /**
     * Append an assistant bubble.
     *
     * @param {string} html Server-sanitised HTML answer.
     * @param {Array} sources Source rows.
     * @param {boolean} iserror Whether the tool reported an error.
     * @return {Promise}
     */
    appendAssistant(html, sources, iserror) {
        const mappedSources = (sources || []).map((s) => ({
            title: s.title,
            url: s.url,
            hasurl: !!s.url,
            snippet: s.snippet
        }));
        return this.appendMessage({
            isassistant: true,
            sendername: this.config.persona,
            html: html,
            failed: !!iserror,
            sources: mappedSources,
            hassources: mappedSources.length > 0,
            copylabel: strings.copy,
            retrylabel: strings.retry
        });
    }

    /**
     * Append a failed-message bubble with a retry control.
     *
     * @param {object} error The AJAX error.
     * @return {Promise}
     */
    appendFailure(error) {
        const message = (error && error.message) ? error.message : strings.failed;
        return this.appendMessage({
            isassistant: true,
            sendername: this.config.persona,
            html: '<p>' + this.escape(message) + '</p>',
            failed: true,
            copylabel: strings.copy,
            retrylabel: strings.retry
        });
    }

    /**
     * Render and append a message via the Mustache template.
     *
     * @param {object} context Template context.
     * @return {Promise}
     */
    appendMessage(context) {
        // The assistant avatar image URL comes from (non-secret) config; inject
        // it here so every caller does not have to repeat it.
        if (context.avatarurl === undefined) {
            context.avatarurl = this.config.avatarurl;
        }
        return Templates.render('block_elediaaitutor/message', context).then((html) => {
            const fragment = document.createElement('div');
            fragment.innerHTML = html.trim();
            // Select the message wrapper explicitly (not just the first element)
            // so a render is robust even if any stray nodes precede it.
            const node = fragment.querySelector('[data-region="message"]') || fragment.firstElementChild;
            if (!node) {
                return null;
            }
            this.log.appendChild(node);
            this.scrollToBottom();
            return node;
        }).catch(Notification.exception);
    }

    /**
     * Show the typing indicator.
     *
     * @return {void}
     */
    showTyping() {
        if (this.typingEl) {
            return;
        }
        this.typingEl = document.createElement('div');
        this.typingEl.className = 'elediaaitutor-message elediaaitutor-assistant elediaaitutor-typing';
        this.typingEl.setAttribute('aria-hidden', 'true');
        this.typingEl.innerHTML = '<div class="elediaaitutor-bubble"><span class="elediaaitutor-dot"></span>'
            + '<span class="elediaaitutor-dot"></span><span class="elediaaitutor-dot"></span></div>';
        this.log.appendChild(this.typingEl);
        this.scrollToBottom();
    }

    /**
     * Remove the typing indicator.
     *
     * @return {void}
     */
    hideTyping() {
        if (this.typingEl) {
            this.typingEl.remove();
            this.typingEl = null;
        }
    }

    /**
     * Copy the answer text of a bubble to the clipboard.
     *
     * @param {HTMLElement} el The copy button.
     * @return {void}
     */
    copyAnswer(el) {
        const bubble = el.closest('[data-region="message"]');
        const md = bubble ? bubble.querySelector('.elediaaitutor-markdown') : null;
        const text = md ? md.innerText : '';
        if (!text) {
            return;
        }
        const done = () => this.setStatus(strings.copied);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => done());
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
            } finally {
                ta.remove();
            }
            done();
        }
    }

    /**
     * Start a new conversation.
     *
     * Resets the client state only: the message log is cleared back to the
     * welcome message and the conversation id is dropped, so the next message is
     * sent without a conversation_id -- which is how the RAG server is told to
     * begin a fresh thread. The previous conversation is preserved in history.
     *
     * @return {void}
     */
    newConversation() {
        if (!this.conversationId && this.log.querySelectorAll('[data-region="message"]').length === 0) {
            this.input.focus();
            return;
        }
        this.log.querySelectorAll('[data-region="message"]').forEach((m) => {
            if (!m.classList.contains('elediaaitutor-welcome')) {
                m.remove();
            }
        });
        this.conversationId = '';
        this.historyLoaded = false;
        this.setStatus(strings.newstarted);
        this.input.focus();
    }

    /**
     * Toggle the history panel, loading conversations on first open.
     *
     * @return {void}
     */
    toggleHistory() {
        if (!this.historyPanel) {
            return;
        }
        const willShow = this.historyPanel.hasAttribute('hidden');
        if (willShow) {
            this.historyPanel.removeAttribute('hidden');
            if (!this.historyLoaded) {
                this.loadHistory();
            }
        } else {
            this.historyPanel.setAttribute('hidden', 'hidden');
        }
    }

    /**
     * Load and render the conversation list.
     *
     * @return {void}
     */
    loadHistory() {
        const list = this.historyPanel.querySelector('[data-region="history-list"]');
        const empty = this.historyPanel.querySelector('[data-region="history-empty"]');
        Ajax.call([{
            methodname: 'block_elediaaitutor_get_conversations',
            args: {contextid: this.config.contextid, courseid: this.config.courseid || 0}
        }])[0].then((response) => {
            this.historyLoaded = true;
            const conversations = (response.conversations || []).map((c) => ({
                id: c.id,
                conversationid: c.conversationid,
                title: c.title,
                preview: c.preview,
                deletelabel: strings.delete
            }));
            empty.hidden = conversations.length > 0;
            if (!conversations.length) {
                list.innerHTML = '';
                return null;
            }
            return Templates.render('block_elediaaitutor/conversation_list', {conversations: conversations})
                .then((html) => {
                    list.innerHTML = html;
                    return null;
                });
        }).catch(Notification.exception);
    }

    /**
     * Open a stored conversation and load its messages.
     *
     * @param {HTMLElement} el The clicked item button.
     * @return {void}
     */
    openConversation(el) {
        const item = el.closest('[data-region="conversation"]');
        if (!item) {
            return;
        }
        const conversationId = item.getAttribute('data-conversationid');
        Ajax.call([{
            methodname: 'block_elediaaitutor_get_history',
            args: {contextid: this.config.contextid, conversationid: conversationId}
        }])[0].then((response) => {
            this.conversationId = conversationId;
            // Reset the log except the welcome message.
            this.log.querySelectorAll('[data-region="message"]').forEach((m) => {
                if (!m.classList.contains('elediaaitutor-welcome')) {
                    m.remove();
                }
            });
            if (!response.available) {
                this.setStatus(strings.nohistorytool);
            }
            const renders = (response.messages || []).map((m) => this.appendMessage({
                isuser: m.role === 'user',
                isassistant: m.role !== 'user',
                sendername: m.role === 'user' ? strings.you : this.config.persona,
                text: m.role === 'user' ? m.html : '',
                html: m.role === 'user' ? '' : m.html,
                copylabel: strings.copy,
                retrylabel: strings.retry
            }));
            if (this.historyPanel) {
                this.historyPanel.setAttribute('hidden', 'hidden');
            }
            this.input.focus();
            return Promise.all(renders);
        }).catch(Notification.exception);
    }

    /**
     * Delete a stored conversation.
     *
     * @param {HTMLElement} el The delete button.
     * @return {void}
     */
    deleteConversation(el) {
        const item = el.closest('[data-region="conversation"]');
        if (!item) {
            return;
        }
        const id = parseInt(item.getAttribute('data-id'), 10);
        Ajax.call([{
            methodname: 'block_elediaaitutor_clear_conversation',
            args: {contextid: this.config.contextid, id: id}
        }])[0].then(() => {
            if (item.getAttribute('data-conversationid') === this.conversationId) {
                this.conversationId = '';
            }
            item.remove();
            const list = this.historyPanel.querySelector('[data-region="history-list"]');
            const empty = this.historyPanel.querySelector('[data-region="history-empty"]');
            if (list && !list.children.length && empty) {
                empty.hidden = false;
            }
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Update the polite status/live region.
     *
     * @param {string} text Status text.
     * @return {void}
     */
    setStatus(text) {
        if (this.status) {
            this.status.textContent = text || '';
        }
    }

    /**
     * Scroll the log to the latest message.
     *
     * @return {void}
     */
    scrollToBottom() {
        const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const behavior = reduce ? 'auto' : 'smooth';
        try {
            this.log.scrollTo({top: this.log.scrollHeight, behavior: behavior});
        } catch (e) {
            this.log.scrollTop = this.log.scrollHeight;
        }
    }

    /**
     * HTML-escape a string for safe insertion.
     *
     * @param {string} text Untrusted text.
     * @return {string}
     */
    escape(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

export default {
    /**
     * Initialise a block instance.
     *
     * @param {object} config Non-secret configuration injected from PHP.
     * @return {void}
     */
    init: function(config) {
        const root = document.getElementById(config.uniqid);
        if (!root || root.dataset.initialised) {
            return;
        }
        root.dataset.initialised = '1';

        getStrings([
            {key: 'thinking', component: 'block_elediaaitutor'},
            {key: 'failed', component: 'block_elediaaitutor'},
            {key: 'copy', component: 'block_elediaaitutor'},
            {key: 'copied', component: 'block_elediaaitutor'},
            {key: 'retry', component: 'block_elediaaitutor'},
            {key: 'newstarted', component: 'block_elediaaitutor'},
            {key: 'delete', component: 'core'},
            {key: 'toolong', component: 'block_elediaaitutor'},
            {key: 'nohistorytool', component: 'block_elediaaitutor'},
            {key: 'senderyou', component: 'block_elediaaitutor'}
        ]).then((loaded) => {
            strings = {
                thinking: loaded[0], failed: loaded[1], copy: loaded[2], copied: loaded[3],
                retry: loaded[4], newstarted: loaded[5], delete: loaded[6], toolong: loaded[7],
                nohistorytool: loaded[8], you: loaded[9]
            };
            new TutorChat(root, config);
            return null;
        }).catch(Notification.exception);
    }
};
