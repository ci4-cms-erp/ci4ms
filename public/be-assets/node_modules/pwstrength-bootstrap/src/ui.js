/*
 * jQuery Password Strength plugin for Twitter Bootstrap
 *
 * Copyright (c) 2008-2013 Tane Piper
 * Copyright (c) 2013 Alejandro Blanco
 * Dual licensed under the MIT and GPL licenses.
 */

// eslint-disable-next-line no-implicit-globals
var ui = {};

(function($) {
    'use strict';

    var statusClasses = ['error', 'warning', 'success'],
        verdictKeys = [
            'veryWeak',
            'weak',
            'normal',
            'medium',
            'strong',
            'veryStrong'
        ];

    ui.getContainer = function(options, $el) {
        var $container;

        $container = $(options.ui.container);
        if (!($container && $container.length === 1)) {
            $container = $el.parent();
        }
        return $container;
    };

    ui.findElement = function($container, viewport, cssSelector) {
        if (viewport) {
            return $container.find(viewport).find(cssSelector);
        }
        return $container.find(cssSelector);
    };

    ui.getUIElements = function(options, $el) {
        var $container, result;

        if (options.instances.viewports) {
            return options.instances.viewports;
        }

        $container = ui.getContainer(options, $el);

        result = {};
        result.$progressbar = ui.findElement(
            $container,
            options.ui.viewports.progress,
            'div.progress'
        );
        if (options.ui.showVerdictsInsideProgressBar) {
            result.$verdict = result.$progressbar.find('span.password-verdict');
        }

        if (!options.ui.showPopover) {
            if (!options.ui.showVerdictsInsideProgressBar) {
                result.$verdict = ui.findElement(
                    $container,
                    options.ui.viewports.verdict,
                    'span.password-verdict'
                );
            }
            result.$errors = ui.findElement(
                $container,
                options.ui.viewports.errors,
                'ul.error-list'
            );
        }
        result.$score = ui.findElement(
            $container,
            options.ui.viewports.score,
            'span.password-score'
        );

        options.instances.viewports = result;
        return result;
    };

    ui.initHelper = function(options, $el, html, viewport) {
        var $container = ui.getContainer(options, $el);
        if (viewport) {
            $container.find(viewport).append(html);
        } else {
            $(html).insertAfter($el);
        }
    };

    ui.initVerdict = function(options, $el) {
        ui.initHelper(
            options,
            $el,
            '<span class="password-verdict"></span>',
            options.ui.viewports.verdict
        );
    };

    ui.initErrorList = function(options, $el) {
        ui.initHelper(
            options,
            $el,
            '<ul class="error-list"></ul>',
            options.ui.viewports.errors
        );
    };

    ui.initScore = function(options, $el) {
        ui.initHelper(
            options,
            $el,
            '<span class="password-score"></span>',
            options.ui.viewports.score
        );
    };

    ui.initUI = function(options, $el) {
        if (options.ui.showPopover) {
            ui.initPopover(options, $el);
        } else {
            if (options.ui.showErrors) {
                ui.initErrorList(options, $el);
            }
            if (
                options.ui.showVerdicts &&
                !options.ui.showVerdictsInsideProgressBar
            ) {
                ui.initVerdict(options, $el);
            }
        }
        if (options.ui.showProgressBar) {
            ui.initProgressBar(options, $el);
        }
        if (options.ui.showScore) {
            ui.initScore(options, $el);
        }
    };

    ui.updateVerdict = function(options, $el, cssClass, text) {
        var $verdict = ui.getUIElements(options, $el).$verdict;
        $verdict.removeClass(options.ui.colorClasses.join(' '));
        if (cssClass > -1) {
            $verdict.addClass(options.ui.colorClasses[cssClass]);
        }
        if (options.ui.showVerdictsInsideProgressBar) {
            $verdict.css('white-space', 'nowrap');
        }
        $verdict.html(text);
    };

    ui.updateErrors = function(options, $el, remove) {
        var $errors = ui.getUIElements(options, $el).$errors,
            html = '';

        if (!remove) {
            $.each(options.instances.errors, function(idx, err) {
                html += '<li>' + err + '</li>';
            });
        }
        $errors.html(html);
    };

    ui.updateScore = function(options, $el, score, remove) {
        var $score = ui.getUIElements(options, $el).$score,
            html = '';

        if (!remove) {
            html = score.toFixed(2);
        }
        $score.html(html);
    };

    ui.updateFieldStatus = function(options, $el, cssClass, remove) {
        var $target = $el;

        if (options.ui.bootstrap2) {
            $target = $el.parents('.control-group').first();
        } else if (options.ui.bootstrap3) {
            $target = $el.parents('.form-group').first();
        }

        $.each(statusClasses, function(idx, css) {
            css = ui.cssClassesForBS(options, css);
            $target.removeClass(css);
        });

        if (remove) {
            return;
        }

        cssClass = statusClasses[Math.floor(cssClass / 2)];
        cssClass = ui.cssClassesForBS(options, cssClass);
        $target.addClass(cssClass);
    };

    ui.cssClassesForBS = function(options, css) {
        if (options.ui.bootstrap3) {
            css = 'has-' + css;
        } else if (!options.ui.bootstrap2) {
            // BS4
            if (css === 'error') {
                css = 'danger';
            }
            css = 'border-' + css;
        }
        return css;
    };

    ui.getVerdictAndCssClass = function(options, score) {
        var level, verdict;

        if (score === undefined) {
            return ['', 0];
        }

        if (score <= options.ui.scores[0]) {
            level = 0;
        } else if (score < options.ui.scores[1]) {
            level = 1;
        } else if (score < options.ui.scores[2]) {
            level = 2;
        } else if (score < options.ui.scores[3]) {
            level = 3;
        } else if (score < options.ui.scores[4]) {
            level = 4;
        } else {
            level = 5;
        }

        verdict = verdictKeys[level];

        return [options.i18n.t(verdict), level];
    };

    ui.updateUI = function(options, $el, score) {
        var cssClass, verdictText, verdictCssClass;

        cssClass = ui.getVerdictAndCssClass(options, score);
        verdictText = score === 0 ? '' : cssClass[0];
        cssClass = cssClass[1];
        verdictCssClass = options.ui.useVerdictCssClass ? cssClass : -1;

        if (options.ui.showProgressBar) {
            ui.showProgressBar(
                options,
                $el,
                score,
                cssClass,
                verdictCssClass,
                verdictText
            );
        }

        if (options.ui.showStatus) {
            ui.updateFieldStatus(options, $el, cssClass, score === undefined);
        }

        if (options.ui.showPopover) {
            ui.updatePopover(options, $el, verdictText, score === undefined);
        } else {
            if (
                options.ui.showVerdicts &&
                !options.ui.showVerdictsInsideProgressBar
            ) {
                ui.updateVerdict(options, $el, verdictCssClass, verdictText);
            }
            if (options.ui.showErrors) {
                ui.updateErrors(options, $el, score === undefined);
            }
        }

        if (options.ui.showScore) {
            ui.updateScore(options, $el, score, score === undefined);
        }
    };
})(jQuery);
