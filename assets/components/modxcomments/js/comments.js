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
      this.settings = { allowGuests: true, maxDepth: 5, maxLength: 5000, threadsPerPage: 20, captcha: { enabled: false } };
      this.i18n = {};
      this.comments = [];
      this.localPending = [];
      this.parent = 0;
      this.editingId = 0;
      this.deletingId = 0;
      this.captchaToken = '';
      this.turnstileWidget = null;
      this.relativeFormatter = null;
      this.page = 1;
      this.total = 0;
      this.pagination = { page: 1, pages: 1, perPage: 20, totalThreads: 0 };
      this.frequentEmoji = ['😀', '😂', '👍', '❤️', '🎉'];
      this.moreEmoji = ['😊', '😍', '🤔', '😅', '😢', '😡', '🙏', '👏', '🔥', '✅', '😉', '🤝', '💡', '🚀', '💯'];
    }

    async init() {
      this.renderShell();
      this.setLoading(true);

      try {
        const [init, list] = await Promise.all([
          this.request('web/init'),
          this.request('web/comment/getlist', { resource: this.resource, page: 1 })
        ]);

        this.csrf = init.csrf;
        this.user = init.user;
        this.settings = init.settings || this.settings;
        this.i18n = init.i18n || {};
        this.initRelativeTime();

        this.renderShell();
        this.bindShellEvents();
        this.setLoading(true);
        this.renderForm();
        this.applyList(list);
        await this.initCaptcha();
      } catch (error) {
        this.showStatus(this.humanError(error), true);
      } finally {
        this.setLoading(false);
      }
    }

    t(key, replacements = {}, fallback = '') {
      let value = this.i18n[key] || fallback || key;

      Object.keys(replacements).forEach((name) => {
        value = value.replace(new RegExp('\\{' + name + '\\}', 'g'), String(replacements[name]));
      });

      return value;
    }

    renderShell() {
      this.root.innerHTML = `
        <div class="mc-heading">
          <strong data-mc-title>${this.escape(this.t('comments', {}, 'Comments'))}</strong>
        </div>
        <div class="mc-loading" data-mc-loading aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
        <div class="mc-list" data-mc-list></div>
        <nav class="mc-pagination" data-mc-pagination aria-label="Pagination"></nav>
        <div class="mc-composer" data-mc-composer></div>
        <div class="mc-status" data-mc-status role="status" aria-live="polite"></div>
      `;
    }

    bindShellEvents() {
      const list = this.root.querySelector('[data-mc-list]');
      const pagination = this.root.querySelector('[data-mc-pagination]');

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

      pagination.addEventListener('click', (event) => {
        const button = event.target.closest('[data-mc-page]');
        if (!button || button.disabled) return;
        this.changePage(Number(button.dataset.mcPage || 1));
      });
    }

    renderForm() {
      const composer = this.root.querySelector('[data-mc-composer]');

      if (!this.user.authenticated && !this.settings.allowGuests) {
        composer.innerHTML = `<div class="mc-notice">${this.escape(this.t('sign_in', {}, 'Sign in to leave a comment.'))}</div>`;
        return;
      }

      const needsGuest = !this.user.authenticated;
      const maxLength = Number(this.settings.maxLength) || 5000;

      composer.innerHTML = `
        <form class="mc-form" data-mc-form>
          <div class="mc-form-title">${this.escape(this.t('leave_comment', {}, 'Leave a comment'))}</div>

          <div class="mc-replying" data-mc-replying hidden>
            <div class="mc-reply-preview">
              <strong data-mc-reply-label></strong>
              <span data-mc-reply-excerpt></span>
            </div>
            <button type="button" class="mc-link-button" data-mc-cancel-reply>${this.escape(this.t('cancel', {}, 'Cancel'))}</button>
          </div>

          ${needsGuest ? `
            <div class="mc-guest-fields">
              <label>
                <span>${this.escape(this.t('name', {}, 'Name'))}</span>
                <input name="author_name" maxlength="190" autocomplete="name" required>
              </label>
              <label>
                <span>${this.escape(this.t('email', {}, 'Email'))}</span>
                <input name="author_email" type="email" maxlength="254" autocomplete="email" required>
              </label>
            </div>
          ` : ''}

          <div class="mc-honeypot" aria-hidden="true">
            <label>
              <span>${this.escape(this.t('website', {}, 'Website'))}</span>
              <input name="website" type="text" tabindex="-1" autocomplete="off">
            </label>
          </div>

          <div class="mc-editor">
            <div class="mc-toolbar" data-mc-toolbar>
              <div class="mc-toolbar-main">
                <button type="button" class="mc-tool" data-mc-link-toggle title="${this.escape(this.t('insert_link', {}, 'Insert link'))}" aria-label="${this.escape(this.t('insert_link', {}, 'Insert link'))}">🔗</button>
                ${this.frequentEmoji.map((emoji) => `<button type="button" class="mc-tool mc-emoji" data-mc-emoji="${emoji}">${emoji}</button>`).join('')}
                <button type="button" class="mc-tool" data-mc-emoji-toggle title="${this.escape(this.t('more_emoji', {}, 'More emoji'))}" aria-label="${this.escape(this.t('more_emoji', {}, 'More emoji'))}">＋</button>
              </div>

              <div class="mc-link-panel" data-mc-link-panel hidden>
                <label>
                  <span>${this.escape(this.t('link_text', {}, 'Link text'))}</span>
                  <input type="text" maxlength="200" placeholder="OpenAI" data-mc-link-text>
                </label>
                <label>
                  <span>${this.escape(this.t('url', {}, 'URL'))}</span>
                  <input type="url" placeholder="https://example.com" data-mc-link-input>
                </label>
                <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-link-insert>${this.escape(this.t('insert_link_button', {}, 'Insert link'))}</button>
              </div>

              <div class="mc-emoji-panel" data-mc-emoji-panel hidden>
                ${this.moreEmoji.map((emoji) => `<button type="button" class="mc-emoji-choice" data-mc-emoji="${emoji}">${emoji}</button>`).join('')}
              </div>
            </div>

            <label class="mc-comment-field">
              <span class="mc-visually-hidden">${this.escape(this.t('comment', {}, 'Comment'))}</span>
              <textarea name="content" rows="5" maxlength="${maxLength}" placeholder="${this.escape(this.t('write_comment', {}, 'Write a comment…'))}" required></textarea>
            </label>
          </div>

          <div class="mc-form-meta">
            <span class="mc-counter" data-mc-counter>0 / ${maxLength}</span>
          </div>

          <div class="mc-captcha" data-mc-captcha></div>

          <div class="mc-actions">
            <button type="submit" class="mc-btn mc-btn-primary">${this.escape(this.t('post_comment', {}, 'Post comment'))}</button>
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

    applyList(list) {
      this.total = Number(list.total || 0);
      this.pagination = list.pagination || { page: 1, pages: 1, perPage: Number(this.settings.threadsPerPage) || 20, totalThreads: 0 };
      this.page = Number(this.pagination.page || 1);

      const title = this.root.querySelector('[data-mc-title]');
      if (title) {
        title.textContent = `${this.t('comments', {}, 'Comments')} (${this.total})`;
      }

      this.renderComments(list.comments || []);
      this.renderPagination();
    }

    renderComments(comments) {
      const serverComments = Array.isArray(comments) ? comments.slice() : [];
      const serverIds = new Set(
        serverComments
          .filter((item) => !item.localPending)
          .map((item) => Number(item.id))
      );

      this.localPending = this.localPending.filter((item) => !serverIds.has(Number(item.id)));

      const merged = serverComments.slice();
      this.localPending.forEach((pending) => {
        if (Number(pending.previewPage || this.page) !== Number(this.page)) return;

        const item = Object.assign({}, pending, { localPending: true });
        if (merged.some((entry) => Number(entry.id) === Number(item.id))) return;

        const parentId = Number(item.parent || 0);

        if (!parentId) {
          merged.push(item);
          return;
        }

        const parentIndex = merged.findIndex((entry) => Number(entry.id) === parentId);
        if (parentIndex < 0) {
          merged.push(item);
          return;
        }

        const parentDepth = Number(merged[parentIndex].depth || 0);
        let insertAt = parentIndex + 1;
        while (insertAt < merged.length && Number(merged[insertAt].depth || 0) > parentDepth) {
          insertAt++;
        }
        merged.splice(insertAt, 0, item);
      });

      this.comments = merged;
      const list = this.root.querySelector('[data-mc-list]');

      if (!this.comments.length) {
        list.innerHTML = `
          <div class="mc-empty">
            <div class="mc-empty-title">${this.escape(this.t('no_comments', {}, 'No comments yet'))}</div>
            <div class="mc-empty-text">${this.escape(this.t('no_comments_text', {}, 'Be the first to join the discussion.'))}</div>
          </div>
        `;
        return;
      }

      list.innerHTML = this.comments.map((comment) => this.renderComment(comment)).join('');
    }

    renderPagination() {
      const target = this.root.querySelector('[data-mc-pagination]');
      if (!target) return;

      const page = Number(this.pagination.page || 1);
      const pages = Number(this.pagination.pages || 1);

      if (pages <= 1) {
        target.innerHTML = '';
        return;
      }

      target.innerHTML = `
        <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>${this.escape(this.t('previous', {}, 'Previous'))}</button>
        <span>${this.escape(this.t('page', { page, pages }, `Page ${page} of ${pages}`))}</span>
        <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-page="${page + 1}" ${page >= pages ? 'disabled' : ''}>${this.escape(this.t('next', {}, 'Next'))}</button>
      `;
    }

    renderComment(comment) {
      const depth = Math.min(Number(comment.depth) || 0, Number(this.settings.maxDepth) || 5);
      const id = Number(comment.id);

      if (comment.deleted) {
        return `
          <article id="comment-${id}" class="mc-comment is-deleted" data-comment-id="${id}" style="--mc-depth:${depth}">
            <div class="mc-deleted"><a class="mc-permalink" href="#comment-${id}">#${id}</a> ${this.escape(this.t('comment_deleted', {}, 'Comment deleted'))}</div>
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
                <span class="mc-visually-hidden">${this.escape(this.t('edit_comment', {}, 'Edit comment'))}</span>
                <textarea rows="4" maxlength="${Number(this.settings.maxLength) || 5000}" data-mc-edit-text>${this.escape(comment.content)}</textarea>
              </label>
              <div class="mc-inline-actions">
                <button type="button" class="mc-btn mc-btn-primary mc-btn-small" data-mc-action="save-edit" data-id="${id}">${this.escape(this.t('save', {}, 'Save'))}</button>
                <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-action="cancel-edit" data-id="${id}">${this.escape(this.t('cancel', {}, 'Cancel'))}</button>
              </div>
            </div>
          </article>
        `;
      }

      const isPendingPreview = Boolean(comment.localPending && comment.status === 'pending');
      const actions = isPendingPreview ? '' : [
        comment.canReply ? this.actionButton('reply', id, this.t('reply', {}, 'Reply')) : '',
        comment.canEdit ? this.actionButton('edit', id, this.t('edit', {}, 'Edit')) : '',
        comment.canDelete ? this.actionButton('delete', id, this.t('delete', {}, 'Delete'), 'is-danger') : ''
      ].filter(Boolean).join('');

      const deletePanel = this.deletingId === id ? `
        <div class="mc-delete-confirm" role="alert">
          <div>
            <strong>${this.escape(this.t('delete_question', {}, 'Delete this comment?'))}</strong>
            <span>${this.escape(this.t('delete_replies', {}, 'Replies will remain in the thread.'))}</span>
          </div>
          <div class="mc-inline-actions">
            <button type="button" class="mc-btn mc-btn-danger mc-btn-small" data-mc-action="confirm-delete" data-id="${id}">${this.escape(this.t('delete', {}, 'Delete'))}</button>
            <button type="button" class="mc-btn mc-btn-secondary mc-btn-small" data-mc-action="cancel-delete" data-id="${id}">${this.escape(this.t('cancel', {}, 'Cancel'))}</button>
          </div>
        </div>
      ` : '';

      const votes = comment.votes || { up: 0, down: 0, mine: 0 };

      return `
        <article id="comment-${id}" class="mc-comment ${isPendingPreview ? 'is-pending-preview' : ''}" data-comment-id="${id}" style="--mc-depth:${depth}">
          ${this.renderCommentHeader(comment)}
          ${this.renderReplyQuote(comment)}
          <div class="mc-content">${comment.contentHtml}</div>

          ${isPendingPreview ? `
            <div class="mc-pending-note">${this.escape(this.t('awaiting_moderation', {}, 'Awaiting moderation'))}</div>
          ` : `
            <div class="mc-comment-footer">
              <div class="mc-comment-actions">${actions}</div>
              <div class="mc-votes" aria-label="${this.escape(this.t('comment_rating', {}, 'Comment rating'))}">
                <button type="button" class="mc-vote ${Number(votes.mine) === 1 ? 'is-active' : ''}" data-mc-action="vote-up" data-id="${id}" title="${this.escape(this.t('like', {}, 'Like'))}">👍 <span>${Number(votes.up) || 0}</span></button>
                <button type="button" class="mc-vote ${Number(votes.mine) === -1 ? 'is-active' : ''}" data-mc-action="vote-down" data-id="${id}" title="${this.escape(this.t('dislike', {}, 'Dislike'))}">👎 <span>${Number(votes.down) || 0}</span></button>
              </div>
            </div>
          `}

          ${deletePanel}
        </article>
      `;
    }

    renderCommentHeader(comment) {
      const guest = this.t('guest', {}, 'Guest');
      const initial = this.escape((comment.author.name || guest).trim().charAt(0).toUpperCase() || 'G');
      const id = Number(comment.id);

      return `
        <header class="mc-comment-header">
          <span class="mc-avatar" aria-hidden="true">${initial}</span>
          <span class="mc-author">${this.escape(comment.author.name || guest)}</span>
          <a class="mc-permalink" href="#comment-${id}" title="${this.escape(this.t('permalink', { id }, `Permalink to comment #${id}`))}">#${id}</a>
          <time datetime="${this.escape(comment.created)}" title="${this.escape(comment.created)}">${this.escape(this.relativeTime(comment.createdTs, comment.created))}</time>
          ${comment.localPending && comment.status === 'pending' ? `<span class="mc-pending-badge">${this.escape(this.t('pending', {}, 'Pending'))}</span>` : ''}
          ${comment.edited ? `<span class="mc-edited">${this.escape(this.t('edited', {}, 'edited'))}</span>` : ''}
        </header>
      `;
    }

    renderReplyQuote(comment) {
      const reply = comment.replyTo;
      if (!reply) return '';

      if (reply.deleted) {
        return `<div class="mc-quote is-deleted">${this.escape(this.t('reply_deleted', {}, 'Reply to a deleted comment'))}</div>`;
      }

      return `
        <div class="mc-quote">
          <strong>${this.escape(reply.author || this.t('guest', {}, 'Guest'))}</strong>
          <span>${this.escape(reply.excerpt || '')}</span>
        </div>
      `;
    }

    actionButton(action, id, label, extraClass = '') {
      return `<button type="button" class="mc-action ${extraClass}" data-mc-action="${action}" data-id="${id}">${this.escape(label)}</button>`;
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

        label.textContent = name
          ? this.t('replying_to', { name }, `Replying to ${name}`)
          : this.t('replying_to_id', { id: this.parent }, `Replying to #${this.parent}`);
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
        this.showStatus(this.t('captcha_required', {}, 'Please complete the CAPTCHA.'), true);
        return;
      }

      this.setButtonBusy(submit, true, this.t('posting', {}, 'Posting…'));
      this.showStatus('');

      try {
        const parentBeforeSubmit = this.parent;
        const created = await this.request('web/comment/create', {
          resource: this.resource,
          parent: this.parent,
          content: data.get('content') || '',
          author_name: data.get('author_name') || '',
          author_email: data.get('author_email') || '',
          website: data.get('website') || '',
          captcha_token: this.captchaToken
        }, 'POST');

        if (created.comment && created.comment.status === 'pending') {
          this.localPending.push(Object.assign({}, created.comment, {
            localPending: true,
            previewPage: this.page
          }));
        }

        form.reset();
        const counter = form.querySelector('[data-mc-counter]');
        if (counter) counter.textContent = `0 / ${Number(this.settings.maxLength) || 5000}`;

        this.setReply(0);
        this.resetCaptcha();

        if (!parentBeforeSubmit && created.comment && created.comment.status === 'published') {
          this.page = 1;
        }

        await this.reload();
        if (created.comment && created.comment.id) {
          this.scrollToComment(Number(created.comment.id));
        }
        this.showStatus(
          created.comment && created.comment.status === 'pending'
            ? this.t('submitted_pending', {}, 'Comment submitted and is awaiting moderation.')
            : this.t('submitted', {}, 'Comment submitted.')
        );
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
        this.showStatus(this.t('comment_empty', {}, 'Comment cannot be empty.'), true);
        return;
      }

      this.setButtonBusy(button, true, this.t('saving', {}, 'Saving…'));

      try {
        await this.request('web/comment/update', { id, content }, 'POST');
        this.editingId = 0;
        await this.reload();
        this.showStatus(this.t('updated', {}, 'Comment updated.'));
        this.root.dispatchEvent(new CustomEvent('comments:updated', { bubbles: true, detail: { id } }));
      } catch (error) {
        this.showStatus(this.humanError(error), true);
      } finally {
        this.setButtonBusy(button, false);
      }
    }

    async deleteComment(id, button) {
      this.setButtonBusy(button, true, this.t('deleting', {}, 'Deleting…'));

      try {
        await this.request('web/comment/delete', { id }, 'POST');
        this.deletingId = 0;
        await this.reload();
        this.showStatus(this.t('deleted', {}, 'Comment deleted.'));
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

    async changePage(page) {
      const pages = Number(this.pagination.pages || 1);
      page = Math.max(1, Math.min(pages, Number(page) || 1));
      if (page === this.page) return;

      this.page = page;
      this.setLoading(true);

      try {
        await this.reload();
        this.root.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        this.showStatus(this.humanError(error), true);
      } finally {
        this.setLoading(false);
      }
    }

    async reload() {
      const list = await this.request('web/comment/getlist', {
        resource: this.resource,
        page: this.page
      });
      this.applyList(list);
    }

    initRelativeTime() {
      if (!window.Intl || typeof Intl.RelativeTimeFormat !== 'function') return;

      try {
        this.relativeFormatter = new Intl.RelativeTimeFormat(
          this.settings.locale || document.documentElement.lang || 'en',
          { numeric: 'auto' }
        );
      } catch (error) {
        this.relativeFormatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });
      }
    }

    relativeTime(timestamp, fallback) {
      const ts = Number(timestamp || 0);
      if (!ts || !this.relativeFormatter) return fallback || '';

      const seconds = Math.round(ts - (Date.now() / 1000));
      const absolute = Math.abs(seconds);
      let value = seconds;
      let unit = 'second';

      if (absolute >= 86400) {
        value = Math.round(seconds / 86400);
        unit = 'day';
      } else if (absolute >= 3600) {
        value = Math.round(seconds / 3600);
        unit = 'hour';
      } else if (absolute >= 60) {
        value = Math.round(seconds / 60);
        unit = 'minute';
      }

      return this.relativeFormatter.format(value, unit);
    }

    scrollToComment(id) {
      const element = this.root.querySelector('#comment-' + Number(id));
      if (!element) return;

      requestAnimationFrame(() => {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        element.classList.add('is-new-comment');
        window.setTimeout(() => element.classList.remove('is-new-comment'), 2400);
      });
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
      return this.t('error.' + code, {}, code.replace(/_/g, ' '));
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
