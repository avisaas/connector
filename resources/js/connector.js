(function () {
    if (window.avisaasConnectorBound) {
        return;
    }

    window.avisaasConnectorBound = true;

    var scope = '.avisaas-connector ';

    function each(list, callback) {
        Array.prototype.forEach.call(list, callback);
    }

    function setMenu(toggle, open) {
        var row = toggle.closest('.ext-row');
        var confirmBox = row.querySelector('[role="dialog"]');

        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        row.querySelector('[role="menu"]').hidden = !open;

        if (confirmBox) {
            confirmBox.hidden = true;
            confirmBox.querySelector('input').checked = false;
        }
    }

    function openConfirm(item) {
        var row = item.closest('.ext-row');
        var confirmBox = row.querySelector('[role="dialog"]');

        row.querySelector('[role="menu"]').hidden = true;
        confirmBox.hidden = false;
        confirmBox.focus();
    }

    function cancelConfirm(button) {
        var toggle = button.closest('.ext-row').querySelector('[data-connector-menu]');

        setMenu(toggle, false);
        toggle.focus();
    }

    function closeMenus(except) {
        each(document.querySelectorAll(scope + '[data-connector-menu][aria-expanded="true"]'), function (toggle) {
            if (toggle !== except) {
                setMenu(toggle, false);
            }
        });
    }

    function showTab(tab) {
        var card = tab.closest('.card');
        var entries = card.querySelector('[data-connector-entries]').children;
        var empty = card.querySelector('[data-connector-empty]');
        var wanted = ' ' + tab.getAttribute('data-connector-tab') + ' ';
        var shown = 0;
        var groupHasRows = false;

        for (var i = entries.length - 1; i >= 0; i--) {
            var entry = entries[i];

            if (entry.classList.contains('group')) {
                entry.hidden = !groupHasRows;
                groupHasRows = false;
                continue;
            }

            if (!entry.classList.contains('sub')) {
                groupHasRows = false;
            }

            entry.hidden = (' ' + entry.getAttribute('data-tabs') + ' ').indexOf(wanted) === -1;

            if (!entry.hidden) {
                shown++;
                groupHasRows = groupHasRows || entry.classList.contains('sub');
            }
        }

        empty.hidden = shown > 0;
        empty.textContent = tab.getAttribute('data-empty');

        each(card.querySelectorAll('[data-connector-tab]'), function (other) {
            other.setAttribute('aria-selected', other === tab ? 'true' : 'false');
        });
    }

    function openDetails(key) {
        var dialog = document.getElementById('connector-details-' + key);

        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    }

    var handlers = { install: 'onInstall', update: 'onUpdate', delete: 'onDelete' };
    var toggles = { enable: 'onEnable', disable: 'onDisable' };
    var verbs = { install: 'Installing', update: 'Updating', delete: 'Deleting' };
    var failedMessage = 'Something went wrong. Reload the page and try again.';
    var savedRows = {};

    function stoppedMessage(action) {
        return 'The page stopped waiting, but the ' + (action === 'delete' ? 'delete' : 'install') + ' may still be running. Reload this page in a few minutes to see the result.';
    }

    function rowFor(key) {
        return document.getElementById('connector-row-' + key);
    }

    function make(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined) {
            node.textContent = text;
        }

        return node;
    }

    function progressSlot(text) {
        var slot = make('div', 'progress');
        var bar = make('div', 'bar');
        var label = make('div', 'lbl');
        var message = make('span');

        slot.setAttribute('role', 'status');
        bar.setAttribute('aria-hidden', 'true');
        bar.appendChild(make('i', 'ind'));
        message.appendChild(make('i', 'fa fa-circle-notch spin'));
        message.appendChild(document.createTextNode(' ' + text));
        label.appendChild(message);
        slot.appendChild(bar);
        slot.appendChild(label);

        return slot;
    }

    function setActionsDisabled(disabled) {
        each(document.querySelectorAll(scope + '[data-connector-action]'), function (button) {
            button.disabled = disabled;
        });
    }

    function restoreRow(key) {
        var row = rowFor(key);

        if (row && savedRows[key]) {
            row.replaceWith(savedRows[key].cloneNode(true));
        }
    }

    function showNote(key, text) {
        var row = rowFor(key);

        if (!row) {
            return;
        }

        var note = make('p', 'note', text);

        each(row.querySelectorAll('.note'), function (old) {
            old.remove();
        });

        note.setAttribute('role', 'alert');
        row.appendChild(note);
    }

    function showFlash(jqXHR) {
        var flash = jqXHR.responseJSON && jqXHR.responseJSON.X_IGNITER_FLASH_MESSAGES;

        if (!flash || !window.jQuery.ti || !window.jQuery.ti.flashMessage) {
            return false;
        }

        each(flash, function (message) {
            window.jQuery.ti.flashMessage(message);
        });

        return true;
    }

    function settle(key, action) {
        setActionsDisabled(false);

        var row = rowFor(key);

        if (row && row.querySelector('.progress')) {
            restoreRow(key);
            showNote(key, stoppedMessage(action));
        }
    }

    function purgeChoice(button) {
        var box = button.closest('.confirm');
        var ticked = box ? box.querySelector('input').checked : button.getAttribute('data-purge') === '1';

        return ticked ? '1' : '0';
    }

    function startRun(button) {
        var action = button.getAttribute('data-connector-action');
        var key = button.getAttribute('data-product');
        var row = rowFor(key);

        if (!handlers[action] || !row || !window.jQuery) {
            return;
        }

        var payload = { product: key };

        if (action === 'delete') {
            payload.purge = purgeChoice(button);
        }

        closeMenus(null);

        var saved = row.cloneNode(true);

        each(saved.querySelectorAll('.fail, .note'), function (node) {
            node.remove();
        });

        savedRows[key] = saved;

        each(row.querySelectorAll('.fail, .note, .ac'), function (node) {
            node.remove();
        });

        row.querySelector('.st').replaceWith(progressSlot(verbs[action] + ' ' + button.getAttribute('data-name') + '. This can take a few minutes. Don\'t close this page.'));
        setActionsDisabled(true);

        window.jQuery(row).request(handlers[action], {
            data: payload,
            error: function (jqXHR) {
                if (showFlash(jqXHR)) {
                    restoreRow(key);
                }
            },
        }).always(function (data) {
            if (!(data && data.X_IGNITER_REDIRECT)) {
                settle(key, action);
            }
        });
    }

    function toggleExtension(button) {
        var key = button.getAttribute('data-product');
        var row = rowFor(key);

        if (!row || !window.jQuery) {
            return;
        }

        closeMenus(null);
        setActionsDisabled(true);

        window.jQuery(row).request(toggles[button.getAttribute('data-connector-action')], {
            data: { product: key },
            error: function (jqXHR) {
                if (!showFlash(jqXHR)) {
                    showNote(key, failedMessage);
                }
            },
        }).always(function (data) {
            if (!(data && data.X_IGNITER_REDIRECT)) {
                setActionsDisabled(false);
            }
        });
    }

    function runAction(button) {
        if (toggles[button.getAttribute('data-connector-action')]) {
            toggleExtension(button);
        } else {
            startRun(button);
        }
    }

    function selectText(node) {
        var range = document.createRange();
        var selection = window.getSelection();

        range.selectNodeContents(node);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function copyCommands(button) {
        var code = button.closest('.cmd').querySelector('code');
        var label = Array.prototype.find.call(button.childNodes, function (node) {
            return node.nodeType === Node.TEXT_NODE;
        });
        var original = label.textContent;

        function copied() {
            label.textContent = ' Copied';
            setTimeout(function () {
                label.textContent = original;
            }, 1500);
        }

        function selectInstead() {
            selectText(code);

            if (document.execCommand('copy')) {
                copied();
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code.textContent).then(copied, selectInstead);
        } else {
            selectInstead();
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        var toggle = target.closest(scope + '[data-connector-menu]');
        var tab = target.closest(scope + '[data-connector-tab]');
        var details = target.closest(scope + '[data-connector-details]');
        var ask = target.closest(scope + '[data-connector-confirm]');
        var cancel = target.closest(scope + '[data-connector-cancel]');
        var run = target.closest(scope + '[data-connector-action]');
        var copy = target.closest(scope + '[data-connector-copy]');
        var dismiss = target.closest(scope + '[data-connector-dismiss]');
        var close = target.closest(scope + '[data-connector-close]');

        if (toggle) {
            var opening = toggle.getAttribute('aria-expanded') !== 'true';

            closeMenus(toggle);
            setMenu(toggle, opening);
        } else if (tab) {
            closeMenus(null);
            showTab(tab);
        } else if (details) {
            closeMenus(null);
            openDetails(details.getAttribute('data-connector-details'));
        } else if (ask) {
            openConfirm(ask);
        } else if (cancel) {
            cancelConfirm(cancel);
        } else if (run) {
            runAction(run);
        } else if (copy) {
            copyCommands(copy);
        } else if (dismiss) {
            dismiss.closest('.fail').remove();
        } else if (close) {
            close.closest('dialog').close();
        } else if (target.matches(scope + 'dialog')) {
            target.close();
        } else if (!target.closest(scope + '.menu')) {
            closeMenus(null);
        }
    });

    document.addEventListener('keydown', function (event) {
        var open = event.key === 'Escape' ? document.querySelector(scope + '[data-connector-menu][aria-expanded="true"]') : null;

        if (open) {
            setMenu(open, false);
            open.focus();
        }
    });
})();
