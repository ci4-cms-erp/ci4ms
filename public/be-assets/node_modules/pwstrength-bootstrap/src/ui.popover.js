/*
 * jQuery Password Strength plugin for Twitter Bootstrap
 *
 * Copyright (c) 2008-2013 Tane Piper
 * Copyright (c) 2013 Alejandro Blanco
 * Dual licensed under the MIT and GPL licenses.
 */

/* global ui, bootstrap, $ */

(function() {
    'use strict';

    ui.initPopover = function(options, $el) {
        try {
            $el.popover('destroy');
        } catch (error) {
            // Bootstrap 4.2.X onwards
            $el.popover('dispose');
        }
        $el.popover({
            html: true,
            placement: options.ui.popoverPlacement,
            trigger: 'manual',
            content: ' '
        });
    };

    ui.updatePopover = function(options, $el, verdictText, remove) {
        var popover = $el.data('bs.popover'),
            html = '',
            hide = true,
            bootstrap5 = false,
            itsVisible = false;

        if (
            options.ui.showVerdicts &&
            !options.ui.showVerdictsInsideProgressBar &&
            verdictText.length > 0
        ) {
            html =
                '<h5><span class="password-verdict">' +
                verdictText +
                '</span></h5>';
            hide = false;
        }
        if (options.ui.showErrors) {
            if (options.instances.errors.length > 0) {
                hide = false;
            }
            html += options.ui.popoverError(options);
        }

        if (hide || remove) {
            $el.popover('hide');
            return;
        }

        if (options.ui.bootstrap2) {
            popover = $el.data('popover');
        } else if (!popover) {
            // Bootstrap 5
            popover = bootstrap.Popover.getInstance($el[0]);
            bootstrap5 = true;
        }

        if (bootstrap5) {
            itsVisible = $(popover.tip).is(':visible');
        } else {
            itsVisible = popover.$arrow && popover.$arrow.parents('body').length > 0;
        }

        if (itsVisible) {
            if (bootstrap5) {
                $(popover.tip).find('.popover-body').html(html);
            } else {
                $el.find('+ .popover .popover-content').html(html);
            }
        } else {
            // It's hidden
            if (options.ui.bootstrap2 || options.ui.bootstrap3) {
                popover.options.content = html;
            } else if (bootstrap5) {
                popover._config.content = html;
            } else {
                popover.config.content = html;
            }
            $el.popover('show');
        }
    };
})();
