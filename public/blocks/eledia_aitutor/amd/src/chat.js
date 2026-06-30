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
 * @module     block_eledia_aitutor/chat
 * @copyright  2026 eLeDia GmbH, Berlin
 * @author     Christopher Reimann <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Modal from 'core/modal';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {get_strings as getStrings, get_string as getString} from 'core/str';

/** @var {object} Cached localised strings. */
let strings = {};

/** @var {Array} Strings to preload: [property, key, component (default block)]. */
const STRING_DEFS = [
    ['thinking', 'thinking'],
    ['failed', 'failed'],
    ['copy', 'copy'],
    ['copied', 'copied'],
    ['retry', 'retry'],
    ['newstarted', 'newstarted'],
    ['delete', 'delete', 'core'],
    ['toolong', 'toolong'],
    ['nohistorytool', 'nohistorytool'],
    ['you', 'senderyou'],
    ['privacytitle', 'privacyguidelines'],
    ['ltmsaved', 'ltm_saved'],
    ['deletetitle', 'deleteall_confirm_title'],
    ['deleteconfirm', 'deleteall_confirm'],
    ['deletebutton', 'deleteall_confirmbutton'],
    ['extdone', 'deleteall_external_done'],
    ['extunsupported', 'deleteall_external_unsupported'],
    ['resumed', 'conversation_resumed'],
];

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
     * @param {HTMLElement} root The .eledia_aitutor-root element.
     * @param {object} config Non-secret configuration from PHP.
     */
    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.consented = !!config.consented;
        this.ltmEnabled = !!config.ltmenabled;
        this.answerStyle = config.answerstyle || 'explain';
        this.conversationId = '';
        this.busy = false;
        this.lastUserMessage = '';
        this.previousFocus = null;
        this.historyLoaded = false;
        this.expanded = false;
        this.expandHome = null;
        this.expandPlaceholder = null;

        this.panel = root.querySelector('[data-region="panel"]');
        this.window = root.querySelector('.eledia_aitutor-window');
        this.log = root.querySelector('[data-region="log"]');
        this.input = root.querySelector('[data-region="input"]');
        this.status = root.querySelector('[data-region="status"]');
        this.composer = root.querySelector('[data-region="composer"]');
        this.backdrop = root.querySelector('.eledia_aitutor-backdrop');
        this.historyPanel = root.querySelector('[data-region="history"]');
        this.expandButton = root.querySelector('[data-action="expand"]');

        this.bind();
        this.restoreStyle();
        this.restoreConversation();
    }

    /**
     * @return {string} The sessionStorage key for the active conversation.
     */
    conversationKey() {
        return 'eledia_aitutor_conv_' + this.config.contextid + '_' + (this.config.courseid || 0);
    }

    /**
     * Remember (or forget) the active conversation across page reloads.
     *
     * @param {string} conversationId The id, or '' to forget.
     * @return {void}
     */
    saveConversationPointer(conversationId) {
        try {
            if (conversationId) {
                window.sessionStorage.setItem(this.conversationKey(), conversationId);
            } else {
                window.sessionStorage.removeItem(this.conversationKey());
            }
        } catch (e) {
            // Storage unavailable (private mode): the session just won't resume.
        }
    }

    /**
     * Resume the conversation that was active before the page reloaded.
     *
     * The transcript itself lives on the RAG server; this only restores the
     * pointer (so the next message continues the thread instead of forking a
     * new one) and replays the visible messages through the history tool when
     * available. A stale pointer (conversation deleted server-side) is
     * forgotten silently.
     *
     * @return {void}
     */
    restoreConversation() {
        let stored = null;
        try {
            stored = window.sessionStorage.getItem(this.conversationKey());
        } catch (e) {
            return;
        }
        if (!stored) {
            return;
        }
        if (!this.config.historyenabled) {
            // No history tool: continue the thread without a visible replay.
            this.conversationId = stored;
            this.hideStarters();
            this.setStatus(strings.resumed);
            return;
        }
        this.loadConversation(stored).then(() => {
            this.setStatus(strings.resumed);
            return null;
        }).catch(() => {
            this.conversationId = '';
            this.saveConversationPointer('');
            this.resetLog();
            this.showStarters();
        });
    }

    /**
     * Restore the user's last answer-style choice for this block (kept in
     * sessionStorage; the server still enforces the instance lock).
     *
     * @return {void}
     */
    restoreStyle() {
        if (!this.config.allowstylechange) {
            return;
        }
        let stored = null;
        try {
            stored = window.sessionStorage.getItem('eledia_aitutor_style_' + this.config.contextid);
        } catch (e) {
            return;
        }
        if (!stored || ['explain', 'hint', 'quiz'].indexOf(stored) === -1) {
            return;
        }
        this.answerStyle = stored;
        this.panel.querySelectorAll('[data-action="style"]').forEach((chip) => {
            const active = chip.dataset.style === stored;
            chip.classList.toggle('eledia_aitutor-stylechip-active', active);
            chip.setAttribute('aria-checked', active ? 'true' : 'false');
        });
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

        // The launch button is the only action outside the panel, so bind it
        // directly. For the floating (FAB) style it is portalled to <body> so
        // it stays visible even when the block lives in a collapsed drawer; it
        // carries its brand vars inline, so it stays themed once moved.
        const launch = this.root.querySelector('[data-action="launch"]');
        if (launch) {
            if (this.config.launchfab && launch.parentNode !== document.body) {
                document.body.appendChild(launch);
            }
            // Keep a handle: once portalled to <body> the button leaves the root,
            // so this.root.querySelector can no longer find it (open/close need it
            // to toggle aria-expanded).
            this.launch = launch;
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

        // The first-use consent checkbox arms the accept button. Bound directly
        // (change events) within the panel, like the composer controls.
        const consentCheck = this.panel.querySelector('[data-region="consent-checkbox"]');
        if (consentCheck) {
            consentCheck.addEventListener('change', () => {
                const accept = this.panel.querySelector('[data-action="consent-accept"]');
                if (accept) {
                    accept.disabled = !consentCheck.checked;
                }
            });
        }

        // Escape closes overlay modes and Tab is trapped while open. Bound to the
        // panel so it keeps working after the panel is portalled out of the root.
        this.panel.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.expanded && !this.isHidden()) {
                this.shrink();
            } else if (e.key === 'Escape' && this.isOverlay() && !this.isHidden()) {
                this.close();
            } else if (e.key === 'Tab' && (this.isOverlay() || this.expanded) && !this.isHidden()) {
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
            case 'close': this.expanded ? this.shrink() : this.close(); break;
            case 'expand': this.toggleExpanded(); break;
            case 'send': this.send(); break;
            case 'newconversation': this.newConversation(); break;
            case 'style': this.setStyle(el); break;
            case 'starter': this.useStarter(el); break;
            case 'consent-accept': this.giveConsent(el); break;
            case 'privacy': this.openPrivacy(); break;
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
     * Toggle the larger centered chat view.
     *
     * @return {void}
     */
    toggleExpanded() {
        if (this.expanded) {
            this.shrink();
        } else {
            this.expand();
        }
    }

    /**
     * Move the panel into a centered, larger viewport overlay.
     *
     * @return {void}
     */
    expand() {
        this.previousFocus = document.activeElement;
        if (!this.expandPlaceholder && this.panel.parentNode !== document.body) {
            this.expandHome = {
                parent: this.panel.parentNode,
                next: this.panel.nextSibling
            };
            this.expandPlaceholder = document.createComment('eledia_aitutor-expanded-home');
            this.expandHome.parent.insertBefore(this.expandPlaceholder, this.panel);
            document.body.appendChild(this.panel);
        }
        this.panel.classList.add('eledia_aitutor-expanded');
        this.panel.removeAttribute('hidden');
        if (this.backdrop) {
            this.backdrop.removeAttribute('hidden');
        }
        document.body.classList.add('eledia_aitutor-noscroll', 'eledia_aitutor-expanded-open');
        this.expanded = true;
        this.updateExpandButton();
        window.setTimeout(() => this.input && this.input.focus(), 50);
    }

    /**
     * Restore the panel to its previous size and DOM position.
     *
     * @return {void}
     */
    shrink() {
        this.panel.classList.remove('eledia_aitutor-expanded');
        if (this.backdrop && this.config.displaymode !== 'modal') {
            this.backdrop.setAttribute('hidden', 'hidden');
        }
        if (!this.isOverlay()) {
            document.body.classList.remove('eledia_aitutor-noscroll', 'eledia_aitutor-expanded-open');
        } else {
            document.body.classList.remove('eledia_aitutor-expanded-open');
            document.body.classList.toggle('eledia_aitutor-noscroll',
                this.config.displaymode === 'fullscreen' || this.config.displaymode === 'modal');
        }
        if (this.expandHome && this.expandHome.parent) {
            this.expandHome.parent.insertBefore(this.panel, this.expandHome.next);
        }
        if (this.expandPlaceholder && this.expandPlaceholder.parentNode) {
            this.expandPlaceholder.parentNode.removeChild(this.expandPlaceholder);
        }
        this.expandHome = null;
        this.expandPlaceholder = null;
        this.expanded = false;
        this.updateExpandButton();
    }

    /**
     * Keep the expand button icon and accessibility state in sync.
     *
     * @return {void}
     */
    updateExpandButton() {
        if (!this.expandButton) {
            return;
        }
        const icon = this.expandButton.querySelector('i');
        const label = this.expanded ? this.expandButton.dataset.labelCollapse : this.expandButton.dataset.labelExpand;
        this.expandButton.setAttribute('aria-pressed', this.expanded ? 'true' : 'false');
        if (label) {
            this.expandButton.setAttribute('aria-label', label);
            this.expandButton.setAttribute('title', label);
        }
        if (icon) {
            icon.classList.toggle('fa-expand', !this.expanded);
            icon.classList.toggle('fa-compress', this.expanded);
        }
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
        this.panel.classList.add('eledia_aitutor-' + this.config.displaymode);
        this.panel.removeAttribute('hidden');
        if (this.backdrop && this.config.displaymode === 'modal') {
            this.backdrop.removeAttribute('hidden');
        }
        document.body.classList.toggle('eledia_aitutor-noscroll',
            this.config.displaymode === 'fullscreen' || this.config.displaymode === 'modal');
        if (this.launch) {
            this.launch.setAttribute('aria-expanded', 'true');
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
        if (this.expanded) {
            this.shrink();
        }
        this.panel.setAttribute('hidden', 'hidden');
        if (this.backdrop) {
            this.backdrop.setAttribute('hidden', 'hidden');
        }
        document.body.classList.remove('eledia_aitutor-noscroll');
        const launch = this.launch;
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
        if (this.busy || !this.consented) {
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
        this.hideStarters();
        this.appendUser(message);
        this.dispatch(message);
    }

    /**
     * Send a suggested starter question.
     *
     * @param {HTMLElement} el The clicked starter chip.
     * @return {void}
     */
    useStarter(el) {
        if (this.busy || !this.consented) {
            return;
        }
        this.input.value = (el.textContent || '').trim();
        this.autoGrow();
        this.send();
    }

    /**
     * Hide the starter chips (a conversation is underway).
     *
     * @return {void}
     */
    hideStarters() {
        const starters = this.panel.querySelector('[data-region="starters"]');
        if (starters) {
            starters.setAttribute('hidden', 'hidden');
        }
    }

    /**
     * Show the starter chips again (fresh conversation).
     *
     * @return {void}
     */
    showStarters() {
        const starters = this.panel.querySelector('[data-region="starters"]');
        if (starters) {
            starters.removeAttribute('hidden');
        }
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
            methodname: 'block_eledia_aitutor_send_message',
            args: {
                contextid: this.config.contextid,
                message: message,
                courseid: this.config.courseid || 0,
                conversationid: this.conversationId || '',
                answerstyle: this.answerStyle || ''
            }
        }])[0].then((response) => {
            this.hideTyping();
            this.busy = false;
            this.setStatus('');
            if (response.conversationid) {
                this.conversationId = response.conversationid;
                this.saveConversationPointer(response.conversationid);
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
        const mappedSources = (sources || []).map((s, i) => ({
            num: i + 1,
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
            showgrounding: !iserror,
            grounded: mappedSources.length > 0,
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
            failuretext: message,
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
        return Templates.render('block_eledia_aitutor/message', context).then((html) => {
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
        this.typingEl.className = 'eledia_aitutor-message eledia_aitutor-assistant eledia_aitutor-typing';
        this.typingEl.setAttribute('aria-hidden', 'true');
        this.typingEl.innerHTML = '<div class="eledia_aitutor-bubble"><span class="eledia_aitutor-dot"></span>'
            + '<span class="eledia_aitutor-dot"></span><span class="eledia_aitutor-dot"></span></div>';
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
        const md = bubble ? bubble.querySelector('.eledia_aitutor-markdown') : null;
        const text = md ? md.innerText : '';
        if (!text) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(() => this.setStatus(strings.copied))
                .catch(() => this.setStatus(strings.copied));
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
            this.setStatus(strings.copied);
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
        this.resetLog();
        this.conversationId = '';
        this.saveConversationPointer('');
        this.historyLoaded = false;
        this.showStarters();
        this.setStatus(strings.newstarted);
        this.input.focus();
    }

    /**
     * Select an answer style chip.
     *
     * @param {HTMLElement} el The clicked chip.
     * @return {void}
     */
    setStyle(el) {
        const style = el.dataset.style;
        if (!style || !this.config.allowstylechange) {
            return;
        }
        this.answerStyle = style;
        this.panel.querySelectorAll('[data-action="style"]').forEach((chip) => {
            const active = chip === el;
            chip.classList.toggle('eledia_aitutor-stylechip-active', active);
            chip.setAttribute('aria-checked', active ? 'true' : 'false');
        });
        try {
            window.sessionStorage.setItem('eledia_aitutor_style_' + this.config.contextid, style);
        } catch (e) {
            // Storage unavailable (private mode): the choice still applies for this page.
        }
        this.input.focus();
    }

    /**
     * Remove every message from the log except the welcome message.
     *
     * @return {void}
     */
    resetLog() {
        this.log.querySelectorAll('[data-region="message"]').forEach((m) => {
            if (!m.classList.contains('eledia_aitutor-welcome')) {
                m.remove();
            }
        });
    }

    /**
     * Record the user's privacy-guidelines acknowledgement and unlock the chat.
     *
     * The server stores the documented consent (timestamped row + audit event)
     * and enforces the gate independently of this UI.
     *
     * @param {HTMLElement} el The accept button.
     * @return {void}
     */
    giveConsent(el) {
        if (this.consented) {
            return;
        }
        el.disabled = true;
        Ajax.call([{
            methodname: 'block_eledia_aitutor_give_consent',
            args: {contextid: this.config.contextid}
        }])[0].then((response) => {
            if (!response.consented) {
                el.disabled = false;
                return null;
            }
            this.consented = true;
            const region = this.panel.querySelector('[data-region="consent"]');
            if (region) {
                region.remove();
            }
            if (this.input) {
                this.input.removeAttribute('disabled');
            }
            const sendBtn = this.panel.querySelector('[data-action="send"]');
            if (sendBtn) {
                sendBtn.removeAttribute('disabled');
            }
            this.input.focus();
            return null;
        }).catch((error) => {
            el.disabled = false;
            Notification.exception(error);
        });
    }

    /**
     * Open the privacy guidelines modal (accuracy warning, data flows,
     * long-term memory opt-in and the delete-my-data control).
     *
     * @return {void}
     */
    openPrivacy() {
        Templates.render('block_eledia_aitutor/privacy_info', {
            uniqid: this.config.uniqid,
            ltmenabled: this.ltmEnabled,
            candelete: !!this.config.candelete,
            // Institution-specific guidelines, already formatted/sanitised
            // server-side; replaces the built-in informational sections.
            hascustom: !!this.config.privacyhtml,
            customtext: this.config.privacyhtml || ''
        }).then((html) => Modal.create({
            title: strings.privacytitle,
            body: html,
            large: true,
            show: true,
            removeOnClose: true
        })).then((modal) => {
            this.applyBrand(modal);
            this.bindPrivacyModal(modal);
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Theme a portalled modal with the widget's brand variables.
     *
     * Core modals are appended to <body>, outside the widget root/panel, so
     * they don't inherit the --eat-* tokens. We set them inline on the modal
     * root and tag it so the themed-modal CSS applies.
     *
     * @param {Object} modal The created modal instance.
     * @return {void}
     */
    applyBrand(modal) {
        const root = modal.getRoot()[0];
        if (!root) {
            return;
        }
        root.classList.add('eledia_aitutor-modal');
        if (this.config.brandvars) {
            root.setAttribute('style',
                (root.getAttribute('style') || '') + this.config.brandvars);
        }
    }

    /**
     * Wire up the controls inside the privacy modal.
     *
     * The modal lives outside the chat panel, so it gets its own listeners.
     *
     * @param {Object} modal The created modal instance.
     * @return {void}
     */
    bindPrivacyModal(modal) {
        const root = modal.getRoot()[0];
        const toggle = root.querySelector('[data-region="ltm-toggle"]');
        if (toggle) {
            toggle.addEventListener('change', () => this.saveLtm(toggle, root));
        }
        const deletebtn = root.querySelector('[data-action="delete-all"]');
        if (deletebtn) {
            deletebtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.confirmDeleteAll(root);
            });
        }
    }

    /**
     * Persist the long-term memory opt-in via AJAX.
     *
     * @param {HTMLInputElement} toggle The checkbox.
     * @param {HTMLElement} root The modal root element.
     * @return {void}
     */
    saveLtm(toggle, root) {
        Ajax.call([{
            methodname: 'block_eledia_aitutor_set_ltm',
            args: {contextid: this.config.contextid, enabled: toggle.checked}
        }])[0].then((response) => {
            this.ltmEnabled = !!response.enabled;
            const status = root.querySelector('[data-region="ltm-status"]');
            if (status) {
                status.textContent = strings.ltmsaved;
            }
            return null;
        }).catch((error) => {
            // Revert the checkbox so the UI never lies about the stored state.
            toggle.checked = this.ltmEnabled;
            Notification.exception(error);
        });
    }

    /**
     * Ask for confirmation before erasing all tutor data.
     *
     * @param {HTMLElement} privacyroot The privacy modal root element.
     * @return {void}
     */
    confirmDeleteAll(privacyroot) {
        ModalSaveCancel.create({
            title: strings.deletetitle,
            body: strings.deleteconfirm,
            show: true,
            removeOnClose: true
        }).then((modal) => {
            this.applyBrand(modal);
            modal.setSaveButtonText(strings.deletebutton);
            modal.getRoot().on(ModalEvents.save, () => this.performDeleteAll(privacyroot));
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Delete all of the user's tutor data and reset the chat UI.
     *
     * @param {HTMLElement} privacyroot The privacy modal root element.
     * @return {void}
     */
    performDeleteAll(privacyroot) {
        Ajax.call([{
            methodname: 'block_eledia_aitutor_delete_my_data',
            args: {contextid: this.config.contextid}
        }])[0].then(async(response) => {
            this.resetLog();
            this.conversationId = '';
            this.saveConversationPointer('');
            this.historyLoaded = false;
            this.showStarters();
            if (this.historyPanel) {
                const list = this.historyPanel.querySelector('[data-region="history-list"]');
                const empty = this.historyPanel.querySelector('[data-region="history-empty"]');
                if (list) {
                    list.innerHTML = '';
                }
                if (empty) {
                    empty.hidden = false;
                }
            }
            const status = privacyroot.querySelector('[data-region="delete-status"]');
            if (status) {
                const done = await getString('deleteall_done', 'block_eledia_aitutor', response.localdeleted);
                status.textContent = done + ' '
                    + (response.externalsupported ? strings.extdone : strings.extunsupported);
            }
            return null;
        }).catch(Notification.exception);
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
            methodname: 'block_eledia_aitutor_get_conversations',
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
            return Templates.render('block_eledia_aitutor/conversation_list', {conversations: conversations});
        }).then((html) => {
            if (html) {
                list.innerHTML = html;
            }
            return null;
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
        this.loadConversation(item.getAttribute('data-conversationid')).then(() => {
            if (this.historyPanel) {
                this.historyPanel.setAttribute('hidden', 'hidden');
            }
            this.input.focus();
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Make a conversation the active one and replay its transcript.
     *
     * Shared by the history panel and the reload auto-resume.
     *
     * @param {string} conversationId The server conversation id.
     * @return {Promise}
     */
    loadConversation(conversationId) {
        return Ajax.call([{
            methodname: 'block_eledia_aitutor_get_history',
            args: {contextid: this.config.contextid, conversationid: conversationId}
        }])[0].then((response) => {
            this.conversationId = conversationId;
            this.saveConversationPointer(conversationId);
            this.resetLog();
            this.hideStarters();
            if (!response.available) {
                this.setStatus(strings.nohistorytool);
            }
            const renders = (response.messages || []).map((m) => {
                const isuser = m.role === 'user';
                // Map sources exactly as appendAssistant() does, so resumed
                // assistant turns render the same citation cards as live answers.
                const mappedSources = (m.sources || []).map((s, i) => ({
                    num: i + 1,
                    title: s.title,
                    url: s.url,
                    hasurl: !!s.url,
                    snippet: s.snippet
                }));
                return this.appendMessage({
                    isuser: isuser,
                    isassistant: !isuser,
                    sendername: isuser ? strings.you : this.config.persona,
                    text: isuser ? m.html : '',
                    html: isuser ? '' : m.html,
                    sources: mappedSources,
                    hassources: mappedSources.length > 0,
                    showgrounding: !isuser,
                    grounded: mappedSources.length > 0,
                    copylabel: strings.copy,
                    retrylabel: strings.retry
                });
            });
            return Promise.all(renders);
        });
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
            methodname: 'block_eledia_aitutor_clear_conversation',
            args: {contextid: this.config.contextid, id: id}
        }])[0].then(() => {
            if (item.getAttribute('data-conversationid') === this.conversationId) {
                this.conversationId = '';
                this.saveConversationPointer('');
            }
            item.remove();
            const list = this.historyPanel.querySelector('[data-region="history-list"]');
            const empty = this.historyPanel.querySelector('[data-region="history-empty"]');
            const remaining = list ? list.querySelectorAll('[data-region="conversation"]').length : 0;
            if (list && !remaining) {
                list.innerHTML = '';
                if (empty) {
                    empty.hidden = false;
                }
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
     * Only the element id is passed from PHP; the (potentially large) non-secret
     * config — privacy HTML, brand variables, etc. — is read from a JSON
     * data-island inside the widget, so it never inflates the js_call_amd
     * argument string (Moodle warns past 1024 chars).
     *
     * @param {string} uniqid The widget root element id.
     * @return {void}
     */
    init: function(uniqid) {
        const root = document.getElementById(uniqid);
        if (!root || root.dataset.initialised) {
            return;
        }
        root.dataset.initialised = '1';

        let config = {};
        const island = root.querySelector('[data-region="eledia_aitutor-config"]');
        if (island) {
            try {
                config = JSON.parse(island.textContent || '{}');
            } catch (e) {
                config = {};
            }
        }
        config.uniqid = uniqid;

        const requests = STRING_DEFS.map(([, key, component]) => ({
            key: key,
            component: component || 'block_eledia_aitutor'
        }));
        getStrings(requests).then((loaded) => {
            STRING_DEFS.forEach(([prop], index) => {
                strings[prop] = loaded[index];
            });
            new TutorChat(root, config);
            return null;
        }).catch(Notification.exception);
    }
};
