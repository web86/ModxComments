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
      this.settings = { allowGuests: true, maxDepth: 5, maxLength: 5000 };
      this.parent = 0;
    }

    async init() {
      this.renderShell();
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
      } catch (error) {
        this.showStatus(error.message || 'Failed to load comments', true);
      }
    }

    renderShell() {
      this.root.innerHTML = `
        <div class="mc-list" data-mc-list></div>
        <div class="mc-composer" data-mc-composer></div>
        <div class="mc-status" data-mc-status aria-live="polite"></div>
      `;
    }

    renderForm() {
      const composer = this.root.querySelector('[data-mc-composer]');
      const needsGuest = !this.user.authenticated;
      composer.innerHTML = `
        <form class="mc-form" data-mc-form>
          <div class="mc-replying" data-mc-replying hidden></div>
          ${needsGuest ? '<input name="author_name" maxlength="190" placeholder="Name" required><input name="author_email" type="email" maxlength="254" placeholder="Email (optional)">' : ''}
          <textarea name="content" rows="5" maxlength="${Number(this.settings.maxLength) || 5000}" placeholder="Write a comment…" required></textarea>
          <div class="mc-actions">
            <button type="submit">Send</button>
            <button type="button" data-mc-cancel-reply hidden>Cancel reply</button>
          </div>
        </form>
      `;

      composer.querySelector('[data-mc-form]').addEventListener('submit', (event) => this.submit(event));
      composer.querySelector('[data-mc-cancel-reply]').addEventListener('click', () => this.setReply(0));
    }

    renderComments(comments) {
      const list = this.root.querySelector('[data-mc-list]');
      if (!comments.length) {
        list.innerHTML = '<p class="mc-empty">No comments yet.</p>';
        return;
      }
      list.innerHTML = comments.map((comment) => `
        <article class="mc-comment" data-comment-id="${comment.id}" style="--mc-depth:${Math.min(Number(comment.depth) || 0, Number(this.settings.maxDepth) || 5)}">
          <header><strong>${this.escape(comment.author.name || 'Guest')}</strong> <time>${this.escape(comment.created)}</time></header>
          <div class="mc-content">${comment.contentHtml}</div>
          ${comment.canReply ? `<button type="button" class="mc-reply" data-reply-id="${comment.id}">Reply</button>` : ''}
        </article>
      `).join('');

      list.querySelectorAll('[data-reply-id]').forEach((button) => {
        button.addEventListener('click', () => this.setReply(Number(button.dataset.replyId)));
      });
    }

    setReply(id) {
      this.parent = Number(id) || 0;
      const note = this.root.querySelector('[data-mc-replying]');
      const cancel = this.root.querySelector('[data-mc-cancel-reply]');
      if (this.parent) {
        note.hidden = false;
        note.textContent = `Replying to #${this.parent}`;
        cancel.hidden = false;
      } else {
        note.hidden = true;
        note.textContent = '';
        cancel.hidden = true;
      }
      const textarea = this.root.querySelector('textarea[name="content"]');
      if (textarea) textarea.focus();
    }

    async submit(event) {
      event.preventDefault();
      const form = event.currentTarget;
      const data = new FormData(form);
      const payload = {
        action: 'web/comment/create',
        resource: this.resource,
        context: this.context,
        parent: this.parent,
        content: data.get('content') || '',
        author_name: data.get('author_name') || '',
        author_email: data.get('author_email') || ''
      };

      const submit = form.querySelector('[type="submit"]');
      submit.disabled = true;
      this.showStatus('');
      try {
        await this.request('web/comment/create', payload, 'POST');
        form.reset();
        this.setReply(0);
        const list = await this.request('web/comment/getlist', { resource: this.resource, limit: 200 });
        this.renderComments(list.comments || []);
        this.showStatus('Comment added.');
        this.root.dispatchEvent(new CustomEvent('comments:created', { bubbles: true }));
      } catch (error) {
        this.showStatus(error.message || 'Failed to add comment', true);
      } finally {
        submit.disabled = false;
      }
    }

    async request(action, params = {}, method = 'GET') {
      const url = new URL(this.api, window.location.href);
      const options = { method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
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

    showStatus(message, isError = false) {
      const status = this.root.querySelector('[data-mc-status]');
      if (!status) return;
      status.textContent = message;
      status.classList.toggle('is-error', Boolean(isError));
    }

    escape(value) {
      return String(value).replace(/[&<>'"]/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
      })[ch]);
    }
  }

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
