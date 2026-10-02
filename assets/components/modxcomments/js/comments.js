(function () {
  'use strict';

  class ModxComments {
    constructor(root) {
      this.root = root;
      this.resource = Number(root.dataset.resource || 0);
      this.context = root.dataset.context || 'web';
      this.api = root.dataset.api;
      this.csrf = '';
      this.user = { id: 0, authenticated: false, name: '' };
      this.settings = { allowGuests: true, maxDepth: 5, maxLength: 5000, captcha: { enabled: false } };
      this.comments = [];
      this.parent = 0;
      this.editingId = 0;
      this.deletingId = 0;
      this.captchaToken = '';
      this.turnstileWidget = null;
      this.frequentEmoji = ['😀', '😂', '👍', '❤️', '🎉'];
      this.moreEmoji = ['😊', '😍', '🤔', '😅', '😢', '😡', '🙏', '👏', '🔥', '✅', '😉', '🤝', '💡', '🚀', '💯'];
    }

    async init() {
      this.renderShell();
      this.bindShellEvents();
      this.setLoading(true);

      try {
        const [init, list] = await Promise.all([
          this.request('web/init'),
          this.request('web/comment/getlist', { resource: this.resource, limit: 200 })
        ]);

        this.csrf = init.csrf;
        this.user = init.user;
        this.settings = init.settings;
        this.renderForm();
        this.renderComments(list.comments || []);
        await this.initCaptcha();
      } catch (error) {
        this.showStatus(this.humanError(error), true);
      } finally {
        this.setLoading(false);
      }
    }

    renderShell() {
      this.root.innerHTML = `
        <div class="mc-loading" data-mc-loading aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
        <div class="mc-list" data-mc-list></div>
        <div class="mc-composer" data-mc-composer></div>
        <div class="mc-status" data-mc-status role="status" aria-live="polite"></div>
      `;
    }

    bindShellEvents() {
      const list = this.root.querySelector('[data-mc-list]');

      list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-mc-action]');
        if (!button) return;

        const id = Number(button.dataset.id || 0);
        const action = button.dataset.mcAction;

        if (action === 'reply') this.setReply(id);
        if (action === 'edit') this.startEdit(id);
        if (action === 'cancel-edit') this.cancelEdit();
        if (action === 'save-edit') this.saveEdit(id, button);
        if (action === 'delete') this.startDelete(id);
        if (action === 'cancel-delete') this.cancelDelete();
        if (action === 'confirm-delete') this.deleteComment(id, button);
        if (action === 'vote-up') this.vote(id, 1, button);
        if (action === 'vote-down') this.vote(id, -1, button);
      });
    }

    renderForm() {
      const composer = this.root.querySelector('[data-mc-composer]');

      if (!this.user.authenticated && !this.settings.allowGuests) {
        composer.innerHTML = '<div class="mc-notice">Sign in to leave a comment.</div>';
        return;
      }

      const needsGuest = !this.user.authenticated;
      const maxLength = Number(this.settings.maxLength) || 5000;

      composer.innerHTML = `
        <form class="mc-form" data-mc-form>
          <div class="mc-form-title">Leave a comment</div>

          <div class="mc-replying" data-mc-replying hidden>
            <div class="mc-reply-preview">
              <strong data-mc-reply-label></strong>
              <span data-mc-reply-excerpt></span>
            </div>
            <button type="button" class="mc-link-button" data-mc-cancel-reply>Cancel</button>
          </div>

          ${needsGuest ? `
            <div class="mc-guest-fields">
              <label>
                <span>Name</span>
                <input name="author_name" maxlength="190" autocomplete="name" required>
              </label>
              <label>
                <span>Email</span>
                <input name="author_email" type="email" maxlength="254" autocomplete="email" required>
              </label>
            </div>
          ` : ''}

          <div class="mc-honeypot" aria-hidden="true">
            <label>
              <span>Website</span>
              <input name="website" type="text" tabindex="-1" autocomplete="off">
            </label>
          </div>

          <div class="mc-editor">
            <div class="mc-toolbar" data-mc-toolbar>
              <div class="mc-toolbar-main">
                <button type="button" class="mc-tool" data-mc-link-toggle title="Insert link" aria-label="Insert link">🔗</button>
                ${this.frequentEmoji.map((emoji) => `<button type="button" class="mc-tool mc-emoji" data-mc-emoji="${emoji}" title="Insert ${emoji}">${emoji}</button>`).join('')}
                <button type="button" class="mc-tool" data-mc-emoji-toggle title="More emoji" aria-label="More emoji">＋</button>
              </div>

              <div class="mc-link-panel" data-mc-link-panel hidden>
                <label>
                  <span>Link text</span>
                  <input type="text" maxlength="200" placeholder="OpenAI" data-mc-link-text>
                </label>
                <label>
                  <span>URL</span>
                  <input type="url" placeholder="https://example.com" data-mc-link-input>
                </label>
                <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-link-insert>Insert link</button>
              </div>

              <div class="mc-emoji-panel" data-mc-emoji-panel hidden>
                ${this.moreEmoji.map((emoji) => `<button type="button" class="mc-emoji-choice" data-mc-emoji="${emoji}">${emoji}</button>`).join('')}
              </div>
            </div>

            <label class="mc-comment-field">
              <span class="mc-visually-hidden">Comment</span>
              <textarea name="content" rows="5" maxlength="${maxLength}" placeholder="Write a comment…" required></textarea>
            </label>
          </div>

          <div class="mc-form-meta">
            <span class="mc-counter" data-mc-counter>0 / ${maxLength}</span>
          </div>

          <div class="mc-captcha" data-mc-captcha></div>

          <div class="mc-actions">
            <button type="submit" class="mc-btn mc-btn-primary">Post comment</button>
          </div>
        </form>
      `;

      const form = composer.querySelector('[data-mc-form]');
      const textarea = form.querySelector('textarea[name="content"]');
      const counter = form.querySelector('[data-mc-counter]');

      form.addEventListener('submit', (event) => this.submit(event));
      composer.querySelector('[data-mc-cancel-reply]').addEventListener('click', () => this.setReply(0));

      textarea.addEventListener('input', () => {
        counter.textContent = `${textarea.value.length} / ${maxLength}`;
      });

      composer.querySelectorAll('[data-mc-emoji]').forEach((button) => {
        button.addEventListener('click', () => {
          this.insertAtCursor(textarea, button.dataset.mcEmoji || '');
          const panel = composer.querySelector('[data-mc-emoji-panel]');
          if (panel) panel.hidden = true;
        });
      });

      const emojiToggle = composer.querySelector('[data-mc-emoji-toggle]');
      const emojiPanel = composer.querySelector('[data-mc-emoji-panel]');
      emojiToggle.addEventListener('click', () => {
        emojiPanel.hidden = !emojiPanel.hidden;
        composer.querySelector('[data-mc-link-panel]').hidden = true;
      });

      const linkToggle = composer.querySelector('[data-mc-link-toggle]');
      const linkPanel = composer.querySelector('[data-mc-link-panel]');
      const linkInput = composer.querySelector('[data-mc-link-input]');
      const linkText = composer.querySelector('[data-mc-link-text]');
      linkToggle.addEventListener('click', () => {
        linkPanel.hidden = !linkPanel.hidden;
        emojiPanel.hidden = true;
        if (!linkPanel.hidden) linkText.focus();
      });

      composer.querySelector('[data-mc-link-insert]').addEventListener('click', () => {
        let url = linkInput.value.trim();
        let label = linkText.value.trim();

        if (!url) {
          linkInput.focus();
          return;
        }

        if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
        if (!label) label = url;

        label = label.replace(/[\[\]\r\n]/g, ' ').trim().slice(0, 200);
        this.insertAtCursor(textarea, `[${label}](${url})`);

        linkText.value = '';
        linkInput.value = '';
        linkPanel.hidden = true;
      });
    }

    renderComments(comments) {
      this.comments = comments;
      const list = this.root.querySelector('[data-mc-list]');

      if (!comments.length) {
        list.innerHTML = `
          <div class="mc-empty">
            <div class="mc-empty-title">No comments yet</div>
            <div class="mc-empty-text">Be the first to join the discussion.</div>
          </div>
        `;
        return;
      }

      list.innerHTML = comments.map((comment) => this.renderComment(comment)).join('');
    }

    renderComment(comment) {
      const depth = Math.min(Number(comment.depth) || 0, Number(this.settings.maxDepth) || 5);
      const id = Number(comment.id);

      if (comment.deleted) {
        return `
          <article id="comment-${id}" class="mc-comment is-deleted" data-comment-id="${id}" style="--mc-depth:${depth}">
            <div class="mc-deleted"><a class="mc-permalink" href="#comment-${id}">#${id}</a> Comment deleted</div>
          </article>
        `;
      }

      if (this.editingId === id) {
        return `
          <article id="comment-${id}" class="mc-comment is-editing" data-comment-id="${id}" style="--mc-depth:${depth}">
            ${this.renderCommentHeader(comment)}
            ${this.renderReplyQuote(comment)}
            <div class="mc-inline-panel">
              <label>
                <span class="mc-visually-hidden">Edit comment</span>
                <textarea rows="4" maxlength="${Number(this.settings.maxLength) || 5000}" data-mc-edit-text>${this.escape(comment.content)}</textarea>
              </label>
              <div class="mc-inline-actions">
                <button type="button" class="mc-btn mc-btn-primary mc-btn-small" data-mc-action="save-edit" data-id="${id}">Save</button>
                <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-action="cancel-edit" data-id="${id}">Cancel</button>
              </div>
            </div>
          </article>
        `;
      }

      const actions = [
        comment.canReply ? this.actionButton('reply', id, 'Reply') : '',
        comment.canEdit ? this.actionButton('edit', id, 'Edit') : '',
        comment.canDelete ? this.actionButton('delete', id, 'Delete', 'is-danger') : ''
      ].filter(Boolean).join('');

      const deletePanel = this.deletingId === id ? `
        <div class="mc-delete-confirm" role="alert">
          <div>
            <strong>Delete this comment?</strong>
            <span>Replies will remain in the thread.</span>
          </div>
          <div class="mc-inline-actions">
            <button type="button" class="mc-btn mc-btn-danger mc-btn-small" data-mc-action="confirm-delete" data-id="${id}">Delete</button>
            <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-action="cancel-delete" data-id="${id}">Cancel</button>
          </div>
        </div>
      ` : '';

      const votes = comment.votes || { up: 0, down: 0, mine: 0 };

      return `
        <article id="comment-${id}" class="mc-comment" data-comment-id="${id}" style="--mc-depth:${depth}">
          ${this.renderCommentHeader(comment)}
          ${this.renderReplyQuote(comment)}
          <div class="mc-content">${comment.contentHtml}</div>

          <div class="mc-comment-footer">
            <div class="mc-comment-actions">${actions}</div>
            <div class="mc-votes" aria-label="Comment rating">
              <button type="button" class="mc-vote ${Number(votes.mine) === 1 ? 'is-active' : ''}" data-mc-action="vote-up" data-id="${id}" title="Like">👍 <span>${Number(votes.up) || 0}</span></button>
              <button type="button" class="mc-vote ${Number(votes.mine) === -1 ? 'is-active' : ''}" data-mc-action="vote-down" data-id="${id}" title="Dislike">👎 <span>${Number(votes.down) || 0}</span></button>
            </div>
          </div>

          ${deletePanel}
        </article>
      `;
    }

    renderCommentHeader(comment) {
      const initial = this.escape((comment.author.name || 'G').trim().charAt(0).toUpperCase() || 'G');

      return `
        <header class="mc-comment-header">
          <span class="mc-avatar" aria-hidden="true">${initial}</span>
          <span class="mc-author">${this.escape(comment.author.name || 'Guest')}</span>
          <a class="mc-permalink" href="#comment-${Number(comment.id)}" title="Permalink to comment #${Number(comment.id)}">#${Number(comment.id)}</a>
          <time>${this.escape(comment.created)}</time>
          ${comment.edited ? '<span class="mc-edited">edited</span>' : ''}
        </header>
      `;
    }

    renderReplyQuote(comment) {
      const reply = comment.replyTo;
      if (!reply) return '';

      if (reply.deleted) {
        return '<div class="mc-quote is-deleted">Reply to a deleted comment</div>';
      }

      return `
        <div class="mc-quote">
          <strong>${this.escape(reply.author || 'Guest')}</strong>
          <span>${this.escape(reply.excerpt || '')}</span>
        </div>
      `;
    }

    actionButton(action, id, label, extraClass = '') {
      return `<button type="button" class="mc-action ${extraClass}" data-mc-action="${action}" data-id="${id}">${label}</button>`;
    }

    setReply(id) {
      this.parent = Number(id) || 0;
      const note = this.root.querySelector('[data-mc-replying]');
      const label = this.root.querySelector('[data-mc-reply-label]');
      const excerpt = this.root.querySelector('[data-mc-reply-excerpt]');

      if (!note || !label || !excerpt) return;

      if (this.parent) {
        const target = this.comments.find((item) => Number(item.id) === this.parent);
        const name = target && target.author ? target.author.name : '';
        const text = target && target.content ? target.content.replace(/\s+/g, ' ').trim() : '';

        label.textContent = name ? `Replying to ${name}` : `Replying to #${this.parent}`;
        excerpt.textContent = text.length > 160 ? text.slice(0, 157) + '…' : text;
        note.hidden = false;
      } else {
        note.hidden = true;
        label.textContent = '';
        excerpt.textContent = '';
      }

      const textarea = this.root.querySelector('textarea[name="content"]');
      if (textarea) textarea.focus();
    }

    insertAtCursor(textarea, text) {
      if (!textarea || !text) return;
      const start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
      const end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
      textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
      const position = start + text.length;
      textarea.focus();
      textarea.setSelectionRange(position, position);
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    startEdit(id) {
      this.editingId = Number(id);
      this.deletingId = 0;
      this.renderComments(this.comments);

      const editor = this.root.querySelector('[data-mc-edit-text]');
      if (editor) {
        editor.focus();
        editor.setSelectionRange(editor.value.length, editor.value.length);
      }
    }

    cancelEdit() {
      this.editingId = 0;
      this.renderComments(this.comments);
    }

    startDelete(id) {
      this.deletingId = Number(id);
      this.editingId = 0;
      this.renderComments(this.comments);
    }

    cancelDelete() {
      this.deletingId = 0;
      this.renderComments(this.comments);
    }

    async submit(event) {
      event.preventDefault();

      const form = event.currentTarget;
      const data = new FormData(form);
      const submit = form.querySelector('[type="submit"]');

      if (this.settings.captcha && this.settings.captcha.enabled && !this.captchaToken) {
        this.showStatus('Please complete the CAPTCHA.', true);
        return;
      }

      this.setButtonBusy(submit, true, 'Posting…');
      this.showStatus('');

      try {
        await this.request('web/comment/create', {
          resource: this.resource,
          parent: this.parent,
          content: data.get('content') || '',
          author_name: data.get('author_name') || '',
          author_email: data.get('author_email') || '',
          website: data.get('website') || '',
          captcha_token: this.captchaToken
        }, 'POST');

        form.reset();
        const counter = form.querySelector('[data-mc-counter]');
        if (counter) counter.textContent = `0 / ${Number(this.settings.maxLength) || 5000}`;

        this.setReply(0);
        this.resetCaptcha();
        await this.reload();
        this.showStatus('Comment submitted.');
        this.root.dispatchEvent(new CustomEvent('comments:created', { bubbles: true }));
      } catch (error) {
        this.resetCaptcha();
        this.showStatus(this.humanError(error), true);
      } finally {
        this.setButtonBusy(submit, false);
      }
    }

    async saveEdit(id, button) {
      const article = button.closest('[data-comment-id]');
      const textarea = article ? article.querySelector('[data-mc-edit-text]') : null;
      const content = textarea ? textarea.value.trim() : '';

      if (!content) {
        this.showStatus('Comment cannot be empty.', true);
        return;
      }

      this.setButtonBusy(button, true, 'Saving…');

      try {
        await this.request('web/comment/update', { id, content }, 'POST');
        this.editingId = 0;
        await this.reload();
        this.showStatus('Comment updated.');
        this.root.dispatchEvent(new CustomEvent('comments:updated', { bubbles: true, detail: { id } }));
      } catch (error) {
        this.showStatus(this.humanError(error), true);
      } finally {
        this.setButtonBusy(button, false);
      }
    }

    async deleteComment(id, button) {
      this.setButtonBusy(button, true, 'Deleting…');

      try {
        await this.request('web/comment/delete', { id }, 'POST');
        this.deletingId = 0;
        await this.reload();
        this.showStatus('Comment deleted.');
        this.root.dispatchEvent(new CustomEvent('comments:deleted', { bubbles: true, detail: { id } }));
      } catch (error) {
        this.showStatus(this.humanError(error), true);
      } finally {
        this.setButtonBusy(button, false);
      }
    }

    async vote(id, value, button) {
      if (!id || !value) return;
      button.disabled = true;

      try {
        const result = await this.request('web/comment/vote', { id, value }, 'POST');
        const comment = this.comments.find((item) => Number(item.id) === Number(id));

        if (comment && result.votes) {
          comment.votes = result.votes;
          this.renderComments(this.comments);
        }
      } catch (error) {
        this.showStatus(this.humanError(error), true);
      } finally {
        button.disabled = false;
      }
    }

    async reload() {
      const list = await this.request('web/comment/getlist', { resource: this.resource, limit: 200 });
      this.renderComments(list.comments || []);
    }

    async initCaptcha() {
      const captcha = this.settings.captcha || {};
      if (!captcha.enabled || !captcha.siteKey) return;

      await ModxComments.loadTurnstile();
      if (!window.turnstile) return;

      const target = this.root.querySelector('[data-mc-captcha]');
      this.turnstileWidget = window.turnstile.render(target, {
        sitekey: captcha.siteKey,
        callback: (token) => { this.captchaToken = token; },
        'expired-callback': () => { this.captchaToken = ''; },
        'error-callback': () => { this.captchaToken = ''; }
      });
    }

    resetCaptcha() {
      this.captchaToken = '';

      if (window.turnstile && this.turnstileWidget !== null) {
        window.turnstile.reset(this.turnstileWidget);
      }
    }

    setLoading(isLoading) {
      this.root.classList.toggle('is-loading', Boolean(isLoading));
    }

    setButtonBusy(button, busy, busyLabel) {
      if (!button) return;

      if (busy) {
        button.dataset.mcLabel = button.textContent;
        button.textContent = busyLabel || button.textContent;
        button.disabled = true;
      } else {
        if (button.dataset.mcLabel) button.textContent = button.dataset.mcLabel;
        button.disabled = false;
      }
    }

    async request(action, params = {}, method = 'GET') {
      const url = new URL(this.api, window.location.href);
      const options = {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      };
      const base = { action, context: this.context, ...params };

      if (method === 'GET') {
        Object.entries(base).forEach(([key, value]) => url.searchParams.set(key, value));
      } else {
        options.headers['Content-Type'] = 'application/json';
        options.headers['X-ModxComments-CSRF'] = this.csrf;
        options.body = JSON.stringify(base);
      }

      const response = await fetch(url.toString(), options);
      const json = await response.json().catch(() => null);

      if (!json || !json.success) {
        throw new Error((json && json.message) || `HTTP ${response.status}`);
      }

      return json.object || {};
    }

    humanError(error) {
      const code = error && error.message ? error.message : 'unknown_error';
      const messages = {
        csrf_invalid: 'Your session expired. Reload the page and try again.',
        authentication_required: 'Sign in to leave a comment.',
        content_required: 'Comment cannot be empty.',
        content_too_long: 'Comment is too long.',
        author_name_required: 'Please enter your name.',
        author_name_too_long: 'The name is too long.',
        author_email_required: 'Please enter your email address.',
        author_email_invalid: 'Please enter a valid email address.',
        rate_limit_exceeded: 'Too many comments. Please try again later.',
        captcha_failed: 'CAPTCHA verification failed. Please try again.',
        spam_detected: 'The comment could not be submitted.',
        permission_denied: 'You cannot modify this comment.',
        edit_window_expired: 'The editing window for this comment has expired.',
        comment_not_found: 'Comment not found.',
        vote_invalid: 'Invalid vote.'
      };

      return messages[code] || code.replace(/_/g, ' ');
    }

    showStatus(message, isError = false) {
      const status = this.root.querySelector('[data-mc-status]');
      if (!status) return;

      status.textContent = message;
      status.classList.toggle('is-error', Boolean(isError));
      status.classList.toggle('is-success', Boolean(message) && !isError);
    }

    escape(value) {
      return String(value).replace(/[&<>'"]/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
      })[ch]);
    }

    static loadTurnstile() {
      if (window.turnstile) return Promise.resolve();
      if (ModxComments.turnstilePromise) return ModxComments.turnstilePromise;

      ModxComments.turnstilePromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });

      return ModxComments.turnstilePromise;
    }
  }

  ModxComments.turnstilePromise = null;

  function boot() {
    document.querySelectorAll('[data-modx-comments]').forEach((root) => {
      if (root.dataset.initialized) return;
      root.dataset.initialized = '1';
      new ModxComments(root).init();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
