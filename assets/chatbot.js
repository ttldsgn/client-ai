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

  /* ── Session ID (server-issued, stored per-tab for the session) ── */
  var sessionId = '';

  /* ── State ───────────────────────────────────────────── */
  var isOpen           = false;
  var isBusy           = false;
  var awaitingHandover = false; // Conversational escalation confirmation state [1.6.0]
  var cooldownTimer    = null;  // Cooldown after error to prevent rapid re-sends
  var isCooldown       = false; // Whether cooldown is active
  var cooldownDuration = 5000;  // Cooldown duration in milliseconds (5 seconds)
  var messages         = [];    // Stores all messages for transcript export
  var leadFormActive   = false; // Whether lead capture form is currently shown
  var transcriptActive = false; // Whether transcript export overlay is currently shown

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

    /* Persistent Handover Panel (Always show option) */
    if (cfg.enableHandover && cfg.alwaysShowButtons) {
      var persistentHandover = el('div', { 
        'class': 'aicb-handover-container', 
        'style': 'margin-top: 4px; margin-bottom: 8px; padding-left: 16px; padding-right: 16px;',
        'role': 'group', 
        'aria-label': 'Support options' 
      });

      if (cfg.primaryBtnText && cfg.primaryBtnUrl) {
        var pBtn = el('a', {
          'class': 'aicb-btn aicb-btn-primary',
          'href': cfg.primaryBtnUrl,
          'target': '_blank',
          'rel': 'noopener noreferrer',
          'role': 'button'
        }, cfg.primaryBtnText);
        persistentHandover.appendChild(pBtn);
      }

      if (cfg.secondaryBtnText && cfg.secondaryBtnUrl) {
        var sBtn = el('a', {
          'class': 'aicb-btn aicb-btn-secondary',
          'href': cfg.secondaryBtnUrl,
          'target': '_blank',
          'rel': 'noopener noreferrer',
          'role': 'button'
        }, cfg.secondaryBtnText);
        persistentHandover.appendChild(sBtn);
      }

      if (persistentHandover.children.length > 0) {
        win.appendChild(persistentHandover);
      }
    }

    /* Lead capture contact button area (shown when handover buttons appear) */
    /* This will be dynamically added via addLeadCaptureButton() */

    /* Dynamic Footer with transcript export button */
    var footerEl = null;
    if (cfg.footerText && cfg.footerText.trim() !== '') {
      footerEl = el('div', { id: 'aicb-footer' }, cfg.footerText);
    }

    /* Persistent "Need human help?" button — always visible escape hatch */
    if (cfg.showFooterHelpButton && cfg.enableLeadCapture && cfg.leadNonce) {
      var helpBtn = el('button', {
        'id': 'aicb-help-btn',
        'class': 'aicb-transcript-link',
        'aria-label': 'Request human help',
        'type': 'button'
      }, '💬 Need human help?');
      helpBtn.addEventListener('click', function () {
        if (!leadFormActive) {
          showLeadForm();
        }
      });

      if (footerEl) {
        footerEl.appendChild(el('span', { 'class': 'aicb-footer-sep' }, ' · '));
        footerEl.appendChild(helpBtn);
      } else {
        footerEl = el('div', { 'id': 'aicb-footer' });
        footerEl.appendChild(helpBtn);
      }
    }

    /* Transcript export link in footer */
    if (cfg.enableTranscript && cfg.transcriptNonce) {
      var transcriptLink = el('button', {
        'id': 'aicb-transcript-btn',
        'class': 'aicb-transcript-link',
        'aria-label': 'Email conversation transcript',
        'type': 'button'
      }, '📧 Email transcript');
      transcriptLink.addEventListener('click', showTranscriptOverlay);

      if (footerEl) {
        footerEl.appendChild(el('span', { 'class': 'aicb-footer-sep' }, ' · '));
        footerEl.appendChild(transcriptLink);
      } else {
        footerEl = el('div', { 'id': 'aicb-footer' });
        footerEl.appendChild(transcriptLink);
      }
    }

    if (footerEl) {
      win.appendChild(footerEl);
    }

    // Append to live DOM FIRST [1.8.7] [3]
    root.appendChild(win);

    /* Welcome message */
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
    /* Close any open overlays */
    if (leadFormActive) hideLeadForm();
    if (transcriptActive) hideTranscriptOverlay();
    if (launcher) {
      launcher.setAttribute('aria-expanded', 'false');
      launcher.focus();
    }
  }

  function toggle() {
    isOpen ? close() : open();
  }

  /* ── Cooldown management ─────────────────────────────── */
  function startCooldown() {
    if (isCooldown) return;
    isCooldown = true;
    var inp = document.getElementById('aicb-input');
    var sendBtn = document.getElementById('aicb-send');
    if (inp) inp.setAttribute('disabled', 'true');
    if (sendBtn) sendBtn.setAttribute('disabled', 'true');
    cooldownTimer = setTimeout(function () {
      isCooldown = false;
      cooldownTimer = null;
      if (!isBusy) {
        var inp2 = document.getElementById('aicb-input');
        var sendBtn2 = document.getElementById('aicb-send');
        if (inp2) inp2.removeAttribute('disabled');
        if (sendBtn2) sendBtn2.removeAttribute('disabled');
      }
    }, cooldownDuration);
  }

  function cancelCooldown() {
    if (cooldownTimer) {
      clearTimeout(cooldownTimer);
      cooldownTimer = null;
    }
    isCooldown = false;
  }

  /* ── Send message ─────────────────────────────────────── */
  function sendMessage() {
    if (isBusy) return;
    if (isCooldown) {
      addMsg('Please wait a moment before sending another message.', 'error');
      return;
    }
    var inp = document.getElementById('aicb-input');
    var question = inp.value.trim();
    if (!question) return;

    addMsg(question, 'user');
    inp.value = '';
    inp.style.height = 'auto';
    inp.setAttribute('disabled', 'true');
    document.getElementById('aicb-send').setAttribute('disabled', 'true');

    /* Typing indicator */
    var msgs = document.getElementById('aicb-messages');
    var typing = msgs.querySelector('.aicb-typing');
    if (!typing) {
      typing = el('div', { 'class': 'aicb-typing', 'aria-hidden': 'true' });
      typing.appendChild(el('span', {}));
      typing.appendChild(el('span', {}));
      typing.appendChild(el('span', {}));
      msgs.appendChild(typing);
    }
    scrollToBottom();

    isBusy = true;

    // Transitional latency status label (Rendered independently below typing bubble to prevent stretching)
    var statusText = msgs.querySelector('.aicb-typing-status');
    if (!statusText) {
      statusText = el('div', { 'class': 'aicb-typing-status', 'aria-live': 'polite', 'aria-atomic': 'true' });
      msgs.appendChild(statusText);
    }
    statusText.textContent = ''; // Reset
    statusText.style.display = 'none';

    var currentAttempt = 1;
    var maxAttempts = 3;
    var latencyTimer = null;

    function startLatencyTimer() {
      clearLatencyTimer();
      latencyTimer = setTimeout(function () {
        if (isBusy) {
          statusText.textContent = '⏳ Hang on, still working on it...';
          statusText.style.display = 'block';
          scrollToBottom();
        }
      }, 6000);
    }

    function clearLatencyTimer() {
      if (latencyTimer) {
        clearTimeout(latencyTimer);
        latencyTimer = null;
      }
    }

    function executeRequest() {
      startLatencyTimer();

      /* XHR (avoids fetch polyfill concerns on older WP hosts) */
      var xhr = new XMLHttpRequest();
      xhr.open('POST', cfg.ajaxUrl, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        if (xhr.status >= 200 && xhr.status < 300) {
          clearLatencyTimer();
          isBusy = false;
          if (typing.parentNode) typing.parentNode.removeChild(typing);
          if (statusText.parentNode) statusText.parentNode.removeChild(statusText);
          inp.removeAttribute('disabled');
          document.getElementById('aicb-send').removeAttribute('disabled');
          inp.focus();

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

              // Capture server-issued session ID for subsequent requests
              if (data.data.session_id) {
                sessionId = data.data.session_id;
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
        } else {
          // Intercept transient network disconnects, timeouts, or gateway drops
          var isRetryable = (xhr.status === 0 || xhr.status === 408 || xhr.status === 502 || xhr.status === 503 || xhr.status === 504);

          if (isRetryable && currentAttempt < maxAttempts) {
            currentAttempt++;
            clearLatencyTimer();

            // Settle latency indicator changes during third attempt
            if (currentAttempt === 3) {
              statusText.textContent = '⚠️ Re-attempting connection... (Attempt 3 of 3)';
              statusText.style.display = 'block';
              scrollToBottom();
            }

            // Silent backoff refire
            setTimeout(function () {
              executeRequest();
            }, 1000);
          } else {
            // Handover to standard failure state
            clearLatencyTimer();
            isBusy = false;
            if (typing.parentNode) typing.parentNode.removeChild(typing);
            if (statusText.parentNode) statusText.parentNode.removeChild(statusText);
            inp.removeAttribute('disabled');
            document.getElementById('aicb-send').removeAttribute('disabled');
            inp.focus();

            if (xhr.status === 429) {
              addMsg('You\'ve sent too many messages. Please wait a moment.', 'error');
              awaitingHandover = false;
              startCooldown();
            } else if (xhr.status === 502 || xhr.status === 503) {
              var errMsg = 'The AI service is temporarily unavailable. Please try again in a moment.';
              try {
                var errData = JSON.parse(xhr.responseText);
                if (errData.data && errData.data.message) {
                  errMsg = errData.data.message;
                }
              } catch (e) {}
              addMsg(errMsg, 'error');
              awaitingHandover = false;
              startCooldown();
            } else if (xhr.status === 500) {
              addMsg('The AI assistant is not fully configured. Please contact support.', 'error');
              awaitingHandover = false;
            } else if (xhr.status === 400 || xhr.status === 403) {
              sessionId = '';
              try {
                var errData2 = JSON.parse(xhr.responseText);
                var msg2 = (errData2.data && errData2.data.message) ? errData2.data.message : 'Something went wrong. Please try again.';
                addMsg(msg2, 'error');
              } catch (e) {
                addMsg('Something went wrong. Please try again.', 'error');
              }
              awaitingHandover = false;
            } else if (xhr.status === 0 || xhr.status === 408) {
              addMsg('Connection lost. We are having trouble reaching our AI servers. Please try again in a moment.', 'error');
              awaitingHandover = false;
            } else {
              try {
                var errData3 = JSON.parse(xhr.responseText);
                var msg3 = (errData3.data && errData3.data.message) ? errData3.data.message : 'Connection error. Please try again.';
                addMsg(msg3, 'error');
              } catch (e) {
                addMsg('Connection error. Please try again.', 'error');
              }
              awaitingHandover = false;
              startCooldown();
            }
          }
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

    executeRequest();
  }

  /* ── Lead Capture Form ───────────────────────────────── */
  function addLeadCaptureButton() {
    if (!cfg.enableLeadCapture || !cfg.leadNonce) return;
    if (leadFormActive) return;

    var msgs = document.getElementById('aicb-messages');
    if (!msgs) return;

    // Check if lead capture trigger container already exists inside messages viewport
    if (msgs.querySelector('.aicb-lead-container')) return;

    var container = el('div', {
      'class': 'aicb-lead-container',
      'role': 'group',
      'aria-label': 'Leave a message'
    });

    var leadBtn = el('button', {
      'class': 'aicb-btn aicb-btn-secondary',
      'type': 'button',
      'aria-label': 'Leave us a message'
    }, '✉️ Leave a message');
    leadBtn.addEventListener('click', showLeadForm);
    container.appendChild(leadBtn);
    msgs.appendChild(container);
    scrollToBottom();
  }

  function showLeadForm() {
    if (leadFormActive) return;
    leadFormActive = true;

    var msgs = document.getElementById('aicb-messages');
    if (!msgs) return;

    var formContainer = el('div', {
      'class': 'aicb-lead-form',
      'role': 'form',
      'aria-label': 'Contact form'
    });

    var closeForm = el('button', {
      'class': 'aicb-lead-close',
      'type': 'button',
      'aria-label': 'Close form'
    }, '×');
    closeForm.addEventListener('click', hideLeadForm);
    formContainer.appendChild(closeForm);

    var nameLabel = el('label', { 'class': 'aicb-lead-label' }, 'Your Name *');
    var nameInput = el('input', {
      'class': 'aicb-lead-input',
      'type': 'text',
      'id': 'aicb-lead-name',
      'placeholder': 'Your name',
      'aria-required': 'true',
      'autocomplete': 'name'
    });
    formContainer.appendChild(nameLabel);
    formContainer.appendChild(nameInput);

    var emailLabel = el('label', { 'class': 'aicb-lead-label' }, 'Your Email *');
    var emailInput = el('input', {
      'class': 'aicb-lead-input',
      'type': 'email',
      'id': 'aicb-lead-email',
      'placeholder': 'your@email.com',
      'aria-required': 'true',
      'autocomplete': 'email'
    });
    formContainer.appendChild(emailLabel);
    formContainer.appendChild(emailInput);

    var msgLabel = el('label', { 'class': 'aicb-lead-label' }, 'Message (optional)');
    var msgInput = el('textarea', {
      'class': 'aicb-lead-input aicb-lead-textarea',
      'id': 'aicb-lead-message',
      'placeholder': 'How can we help you?',
      'maxlength': '2000',
      'rows': '3'
    });
    formContainer.appendChild(msgLabel);
    formContainer.appendChild(msgInput);

    /* Honeypot field (hidden from users, bots fill it) */
    var hp = el('input', {
      'type': 'text',
      'name': 'website',
      'class': 'aicb-honeypot',
      'tabindex': '-1',
      'autocomplete': 'off'
    });
    formContainer.appendChild(hp);

    var submitBtn = el('button', {
      'class': 'aicb-btn aicb-btn-primary',
      'type': 'button',
      'aria-label': 'Send message'
    }, 'Send Message');
    submitBtn.addEventListener('click', submitLead);
    formContainer.appendChild(submitBtn);

    var statusMsg = el('div', { 'class': 'aicb-lead-status' });
    formContainer.appendChild(statusMsg);

    msgs.appendChild(formContainer);
    scrollToBottom();
    nameInput.focus();
  }

  function hideLeadForm() {
    leadFormActive = false;
    var form = document.querySelector('.aicb-lead-form');
    if (form && form.parentNode) form.parentNode.removeChild(form);
  }

  function submitLead() {
    var name = document.getElementById('aicb-lead-name');
    var email = document.getElementById('aicb-lead-email');
    var message = document.getElementById('aicb-lead-message');
    var statusEl = document.querySelector('.aicb-lead-status');
    var submitBtn = document.querySelector('.aicb-lead-form .aicb-btn-primary');

    if (!name || !email || !statusEl || !submitBtn) return;

    var nameVal = name.value.trim();
    var emailVal = email.value.trim();
    var msgVal = message ? message.value.trim() : '';

    if (!nameVal || !emailVal) {
      statusEl.textContent = 'Please fill in your name and email.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      return;
    }

    if (emailVal.indexOf('@') === -1 || emailVal.indexOf('.') === -1) {
      statusEl.textContent = 'Please enter a valid email address.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';
    statusEl.textContent = '';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', cfg.ajaxUrl, true);
    xhr.timeout = 15000; // 15 second timeout
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onerror = function() {
      statusEl.textContent = 'Network error. Please check your connection and try again.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Send Message';
    };
    xhr.ontimeout = function() {
      statusEl.textContent = 'Request timed out. Please try again.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Send Message';
    };
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var data = JSON.parse(xhr.responseText);
          if (data.success) {
            statusEl.textContent = 'Thank you! We will get back to you soon.';
            statusEl.className = 'aicb-lead-status aicb-lead-success';
            name.value = '';
            email.value = '';
            if (message) message.value = '';
            submitBtn.textContent = 'Sent ✓';
            setTimeout(function () {
              hideLeadForm();
            }, 2000);
          } else {
            statusEl.textContent = data.data && data.data.message ? data.data.message : 'Submission failed. Please try again.';
            statusEl.className = 'aicb-lead-status aicb-lead-error';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Message';
          }
        } catch (e) {
          statusEl.textContent = 'An error occurred. Please try again.';
          statusEl.className = 'aicb-lead-status aicb-lead-error';
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Message';
        }
      } else if (xhr.status === 429) {
        statusEl.textContent = 'Too many submissions. Please try again later.';
        statusEl.className = 'aicb-lead-status aicb-lead-error';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Message';
      } else {
        statusEl.textContent = 'Server error. Please try again.';
        statusEl.className = 'aicb-lead-status aicb-lead-error';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Message';
      }
    };

    var hpVal = (document.querySelector('.aicb-honeypot') || {}).value || '';

    xhr.send(encodeParams({
      action:     'aicb_lead_submit',
      nonce:      cfg.leadNonce,
      name:       nameVal,
      email:      emailVal,
      message:    msgVal,
      session_id: sessionId,
      page_id:    cfg.pageId || 0,
      website:    hpVal
    }));
  }

  /* ── Transcript Export ───────────────────────────────── */
  function showTranscriptOverlay() {
    if (transcriptActive) return;
    transcriptActive = true;

    var msgs = document.getElementById('aicb-messages');
    if (!msgs) return;

    var overlay = el('div', {
      'class': 'aicb-transcript-overlay',
      'role': 'dialog',
      'aria-label': 'Email conversation transcript'
    });

    var closeOverlay = el('button', {
      'class': 'aicb-lead-close',
      'type': 'button',
      'aria-label': 'Close'
    }, '×');
    closeOverlay.addEventListener('click', hideTranscriptOverlay);
    overlay.appendChild(closeOverlay);

    var title = el('p', { 'class': 'aicb-transcript-title' }, 'Enter your email to receive a copy of this conversation:');
    overlay.appendChild(title);

    var emailInput = el('input', {
      'class': 'aicb-lead-input',
      'type': 'email',
      'id': 'aicb-transcript-email',
      'placeholder': 'your@email.com',
      'aria-required': 'true',
      'autocomplete': 'email'
    });
    overlay.appendChild(emailInput);

    /* Honeypot */
    var hp = el('input', {
      'type': 'text',
      'name': 'website',
      'class': 'aicb-honeypot',
      'tabindex': '-1',
      'autocomplete': 'off'
    });
    overlay.appendChild(hp);

    var sendBtn = el('button', {
      'class': 'aicb-btn aicb-btn-primary',
      'type': 'button',
      'aria-label': 'Send transcript'
    }, 'Send Transcript');
    sendBtn.addEventListener('click', submitTranscript);
    overlay.appendChild(sendBtn);

    var statusMsg = el('div', { 'class': 'aicb-lead-status' });
    overlay.appendChild(statusMsg);

    msgs.appendChild(overlay);
    scrollToBottom();
    emailInput.focus();
  }

  function hideTranscriptOverlay() {
    transcriptActive = false;
    var overlay = document.querySelector('.aicb-transcript-overlay');
    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
  }

  function submitTranscript() {
    var emailInput = document.getElementById('aicb-transcript-email');
    var statusEl = document.querySelector('.aicb-transcript-overlay .aicb-lead-status');
    var sendBtn = document.querySelector('.aicb-transcript-overlay .aicb-btn-primary');

    if (!emailInput || !statusEl || !sendBtn) return;

    // Verify a chat session has been established before initiating dynamic email checks
    if (!sessionId || sessionId === '') {
      statusEl.textContent = 'Please send a message first.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      return;
    }

    var emailVal = emailInput.value.trim();

    if (!emailVal || emailVal.indexOf('@') === -1 || emailVal.indexOf('.') === -1) {
      statusEl.textContent = 'Please enter a valid email address.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      return;
    }

    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending…';
    statusEl.textContent = '';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', cfg.ajaxUrl, true);
    xhr.timeout = 15000; // 15 second timeout
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onerror = function() {
      statusEl.textContent = 'Network error. Please check your connection and try again.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      sendBtn.disabled = false;
      sendBtn.textContent = 'Send Transcript';
    };
    xhr.ontimeout = function() {
      statusEl.textContent = 'Request timed out. Please try again.';
      statusEl.className = 'aicb-lead-status aicb-lead-error';
      sendBtn.disabled = false;
      sendBtn.textContent = 'Send Transcript';
    };
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var data = JSON.parse(xhr.responseText);
          if (data.success) {
            statusEl.textContent = 'Transcript sent! Please check your email.';
            statusEl.className = 'aicb-lead-status aicb-lead-success';
            emailInput.value = '';
            sendBtn.textContent = 'Sent ✓';
            setTimeout(function () {
              hideTranscriptOverlay();
            }, 2500);
          } else {
            statusEl.textContent = data.data && data.data.message ? data.data.message : 'Failed. Please try again.';
            statusEl.className = 'aicb-lead-status aicb-lead-error';
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send Transcript';
          }
        } catch (e) {
          statusEl.textContent = 'An error occurred. Please try again.';
          statusEl.className = 'aicb-lead-status aicb-lead-error';
          sendBtn.disabled = false;
          sendBtn.textContent = 'Send Transcript';
        }
      } else if (xhr.status === 429) {
        statusEl.textContent = 'Too many requests. Please try again later.';
        statusEl.className = 'aicb-lead-status aicb-lead-error';
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send Transcript';
      } else {
        statusEl.textContent = 'Server error. Please try again.';
        statusEl.className = 'aicb-lead-status aicb-lead-error';
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send Transcript';
      }
    };

    var hpVal = (document.querySelector('.aicb-honeypot') || {}).value || '';

    xhr.send(encodeParams({
      action:     'aicb_export_transcript',
      nonce:      cfg.transcriptNonce,
      email:      emailVal,
      session_id: sessionId,
      website:    hpVal
    }));
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

    // Add lead capture button after handover buttons if enabled
    if (cfg.enableLeadCapture && cfg.leadNonce) {
      addLeadCaptureButton();
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
    downBtn.addEventListener('click', function () {
      submitFeedback(0);
      // Thumbs-down triggers handover offer immediately
      if (cfg.enableHandover && !awaitingHandover) {
        addMsg(cfg.handoverPrompt || "It looks like I couldn't help. Would you like to connect with our team?", 'bot');
        awaitingHandover = true;
      }
    });
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