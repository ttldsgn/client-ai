/**
 * AI Chatbot — chatbot.js
 * Vanilla JS, no dependencies. ES5-compatible for broad host support.
 */
(function () {
  'use strict';

  /* Guard: only run once even if script duplicated */
  if (window.__aicbInit) return;
  window.__aicbInit = true;

  var cfg = window.aicbData || {};
  var pos = cfg.position || 'right';

  /* ── Icons ─────────────────────────────────────────── */
  var ICONS = {
    chat: '💬',
    bot:  '🤖',
    help: '❓',
    star: '⭐'
  };
  var btnIcon = ICONS[cfg.icon] || ICONS.chat;

  /* ── Session ID (tab-scoped, not stored) ─────────────── */
  var sessionId = 'aicb-' + Math.random().toString(36).slice(2, 10);

  /* ── State ───────────────────────────────────────────── */
  var isOpen           = false;
  var isBusy           = false;
  var awaitingHandover = false; // Conversational escalation confirmation state [1.6.0]

  /* ── Build DOM ───────────────────────────────────────── */
  function init() {
    var root = document.getElementById('aicb-root');
    if (!root) return;

    root.setAttribute('data-pos', pos);
    root.setAttribute('role', 'region');
    root.setAttribute('aria-label', 'Chat widget');

    // Apply primary color as CSS variable
    if (cfg.color) {
      root.style.setProperty('--aicb-color', cfg.color);
      // Derive slightly darker shade for hover
      root.style.setProperty('--aicb-color-dark', shadeColor(cfg.color, -15));
    }

    /* Launcher: floating button OR tab */
    if (pos === 'tab-right' || pos === 'tab-left') {
      buildTab(root);
    } else {
      buildButton(root);
    }

    /* Chat window */
    buildWindow(root);

    /* Keyboard: Escape closes */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) close();
    });
  }

  function buildButton(root) {
    var btn = el('button', {
      id: 'aicb-toggle',
      'aria-label': 'Open chat',
      'aria-expanded': 'false',
      'aria-controls': 'aicb-window'
    }, btnIcon);
    btn.addEventListener('click', toggle);
    root.appendChild(btn);
  }

  function buildTab(root) {
    var tab = el('button', {
      id: 'aicb-tab',
      'aria-label': 'Open chat',
      'aria-expanded': 'false',
      'aria-controls': 'aicb-window'
    });
    var iconSpan = el('span', { 'class': 'aicb-tab-icon' }, btnIcon);
    var textNode = document.createTextNode(cfg.title || 'Chat');
    tab.appendChild(iconSpan);
    tab.appendChild(textNode);
    tab.addEventListener('click', toggle);
    root.appendChild(tab);
  }

  function buildWindow(root) {
    var win = el('div', {
      id: 'aicb-window',
      role: 'dialog',
      'aria-modal': 'true',
      'aria-label': cfg.title || 'Chat',
      hidden: 'true'
    });

    /* Header */
    var hdr = el('div', { id: 'aicb-header' });
    hdr.appendChild(el('span', { id: 'aicb-header-title' }, cfg.title || 'Chat with us'));
    var closeBtn = el('button', { id: 'aicb-close', 'aria-label': 'Close chat' }, '×');
    closeBtn.addEventListener('click', close);
    hdr.appendChild(closeBtn);
    win.appendChild(hdr);

    /* Messages */
    var msgs = el('div', {
      id: 'aicb-messages',
      role: 'log',
      'aria-live': 'polite',
      'aria-atomic': 'false',
      tabindex: '0'
    });
    win.appendChild(msgs);

    /* Form */
    var form = el('form', { id: 'aicb-form', novalidate: '' });
    form.setAttribute('aria-label', 'Send a message');

    var textarea = el('textarea', {
      id: 'aicb-input',
      placeholder: cfg.placeholder || 'Type your question…',
      rows: '1',
      maxlength: '1000',
      'aria-label': 'Your message',
      'aria-multiline': 'true'
    });

    /* Auto-grow textarea */
    textarea.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    /* Send on Enter (Shift+Enter = newline) */
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    var sendBtn = el('button', {
      id: 'aicb-send',
      type: 'button',
      'aria-label': 'Send message'
    }, '↑');
    sendBtn.addEventListener('click', sendMessage);

    form.appendChild(textarea);
    form.appendChild(sendBtn);
    win.appendChild(form);

    /* Dynamic Footer */
    if (cfg.footerText && cfg.footerText.trim() !== '') {
      win.appendChild(el('div', { id: 'aicb-footer' }, cfg.footerText));
    }

    // Append to live DOM FIRST [1.8.7] [3]
    root.appendChild(win);

    /* Welcome message (Now successfully finds aicb-messages container) [1.8.7] [3] */
    if (cfg.welcome) {
      addMsg(cfg.welcome, 'bot');
    }
  }

  /* ── Open / Close ────────────────────────────────────── */
  function open() {
    isOpen = true;
    var win = document.getElementById('aicb-window');
    var launcher = document.getElementById('aicb-toggle') || document.getElementById('aicb-tab');
    win.removeAttribute('hidden');
    win.classList.add('aicb-animate-in');
    win.addEventListener('animationend', function () {
      win.classList.remove('aicb-animate-in');
    }, { once: true });
    if (launcher) launcher.setAttribute('aria-expanded', 'true');
    // Focus input
    var inp = document.getElementById('aicb-input');
    if (inp) setTimeout(function () { inp.focus(); }, 50);
    scrollToBottom();
  }

  function close() {
    isOpen = false;
    var win = document.getElementById('aicb-window');
    var launcher = document.getElementById('aicb-toggle') || document.getElementById('aicb-tab');
    win.setAttribute('hidden', 'true');
    if (launcher) {
      launcher.setAttribute('aria-expanded', 'false');
      launcher.focus();
    }
  }

  function toggle() {
    isOpen ? close() : open();
  }

  /* ── Send message ─────────────────────────────────────── */
  function sendMessage() {
    if (isBusy) return;
    var inp = document.getElementById('aicb-input');
    var question = inp.value.trim();
    if (!question) return;

    addMsg(question, 'user');
    inp.value = '';
    inp.style.height = 'auto';
    inp.setAttribute('disabled', 'true');
    document.getElementById('aicb-send').setAttribute('disabled', 'true');

    /* Typing indicator */
    var typing = el('div', { 'class': 'aicb-typing', 'aria-hidden': 'true' });
    typing.appendChild(el('span', {}));
    typing.appendChild(el('span', {}));
    typing.appendChild(el('span', {}));
    var msgs = document.getElementById('aicb-messages');
    msgs.appendChild(typing);
    scrollToBottom();

    isBusy = true;

    /* XHR (avoids fetch polyfill concerns on older WP hosts) */
    var xhr = new XMLHttpRequest();
    xhr.open('POST', cfg.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      isBusy = false;
      if (typing.parentNode) typing.parentNode.removeChild(typing);
      inp.removeAttribute('disabled');
      document.getElementById('aicb-send').removeAttribute('disabled');
      inp.focus();

      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var data = JSON.parse(xhr.responseText);
          if (data.success && data.data && data.data.answer) {
            addMsg(data.data.answer, 'bot');

            // Show thumbs up/down feedback if enabled
            if (cfg.enableFeedback && data.data.log_id) {
              addFeedback(data.data.log_id);
            }

            // Set state based on server confirmation request [1.6.0]
            if (data.data.awaiting_confirmation) {
              awaitingHandover = true;
            } else {
              awaitingHandover = false;
            }

            // Append buttons dynamically once confirmation completes [1.6.0]
            if (data.data.handover) {
              addHandoverButtons(data.data);
            }
          } else {
            var msg = (data.data && data.data.message) ? data.data.message : 'Something went wrong. Please try again.';
            addMsg(msg, 'error');
            awaitingHandover = false;
          }
        } catch (e) {
          console.error('Chatbot parse error:', e);
          addMsg('Unexpected response. Please try again.', 'error');
          awaitingHandover = false;
        }
      } else if (xhr.status === 429) {
        addMsg('You\'ve sent too many messages. Please wait a moment.', 'error');
        awaitingHandover = false;
      } else {
        addMsg('Connection error. Please try again.', 'error');
        awaitingHandover = false;
      }
    };

    var params = encodeParams({
      action:           'aicb_chat',
      nonce:            cfg.nonce,
      question:         question,
      page_id:          cfg.pageId || 0,
      session_id:       sessionId,
      language:         cfg.language || (navigator.language || 'en'),
      confirm_handover: awaitingHandover ? 'true' : 'false' // Forward confirmation loop state safely [1.6.0]
    });
    xhr.send(params);
  }

  /* ── Helpers ─────────────────────────────────────────── */
  function addMsg(text, type) {
    var msgs = document.getElementById('aicb-messages');
    if (!msgs) return;
    var div = el('div', { 'class': 'aicb-msg aicb-' + type });
    /* Basic safe text rendering — no HTML from AI */
    div.textContent = text;
    msgs.appendChild(div);
    scrollToBottom();
  }

  /**
   * Accessible Support Handover Trigger rendering [1.4.0]
   */
  function addHandoverButtons(data) {
    var msgs = document.getElementById('aicb-messages');
    if (!msgs) return;

    var container = el('div', { 
      'class': 'aicb-handover-container', 
      'role': 'group', 
      'aria-label': 'Support options' 
    });

    if (data.primaryBtnText && data.primaryBtnUrl) {
      var pBtn = el('a', {
        'class': 'aicb-btn aicb-btn-primary',
        'href': data.primaryBtnUrl,
        'target': '_blank',
        'rel': 'noopener noreferrer',
        'role': 'button'
      }, data.primaryBtnText);
      container.appendChild(pBtn);
    }

    if (data.secondaryBtnText && data.secondaryBtnUrl) {
      var sBtn = el('a', {
        'class': 'aicb-btn aicb-btn-secondary',
        'href': data.secondaryBtnUrl,
        'target': '_blank',
        'rel': 'noopener noreferrer',
        'role': 'button'
      }, data.secondaryBtnText);
      container.appendChild(sBtn);
    }

    // Only append the container if at least one button is valid [1.5.1]
    if (container.children.length > 0) {
      msgs.appendChild(container);
      scrollToBottom();
    }
  }

  /**
   * Render thumbs up/down feedback buttons after a bot response.
   */
  function addFeedback(sid) {
    var msgs = document.getElementById('aicb-messages');
    if (!msgs) return;

    var container = el('div', {
      'class': 'aicb-feedback',
      'role': 'group',
      'aria-label': 'Was this helpful?'
    });

    var label = el('span', { 'class': 'aicb-feedback-label' }, 'Was this helpful? ');

    var upBtn = el('button', {
      'class': 'aicb-feedback-btn',
      'data-rating': '1',
      'aria-label': 'Yes, helpful',
      'type': 'button'
    }, '👍');

    var downBtn = el('button', {
      'class': 'aicb-feedback-btn',
      'data-rating': '0',
      'aria-label': 'No, not helpful',
      'type': 'button'
    }, '👎');

    container.appendChild(label);
    container.appendChild(upBtn);
    container.appendChild(downBtn);
    msgs.appendChild(container);
    scrollToBottom();

    function submitFeedback(rating) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', cfg.ajaxUrl, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
          upBtn.disabled = true;
          downBtn.disabled = true;
          upBtn.style.opacity = '0.5';
          downBtn.style.opacity = '0.5';
        }
      };
      xhr.send(encodeParams({
        action:     'aicb_feedback',
        nonce:      cfg.feedbackNonce,
        log_id:     sid,
        rating:     rating
      }));
    }

    upBtn.addEventListener('click', function () { submitFeedback(1); });
    downBtn.addEventListener('click', function () { submitFeedback(0); });
  }

  function scrollToBottom() {
    var msgs = document.getElementById('aicb-messages');
    if (msgs) msgs.scrollTop = msgs.scrollHeight;
  }

  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    for (var k in attrs) {
      if (attrs.hasOwnProperty(k)) {
        if (k === 'hidden') { node.setAttribute('hidden', ''); }
        else node.setAttribute(k, attrs[k]);
      }
    }
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function encodeParams(obj) {
    return Object.keys(obj).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]);
    }).join('&');
  }

  /* Darken hex color by amt (negative = darker) */
  function shadeColor(hex, amt) {
    var num = parseInt(hex.replace('#', ''), 16);
    var r = Math.min(255, Math.max(0, (num >> 16) + amt));
    var g = Math.min(255, Math.max(0, ((num >> 8) & 0x00FF) + amt));
    var b = Math.min(255, Math.max(0, (num & 0x0000FF) + amt));
    return '#' + [r, g, b].map(function (c) {
      return ('0' + c.toString(16)).slice(-2);
    }).join('');
  }

  /* ── Boot ────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();