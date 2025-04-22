/*
 * jQuery Password Strength plugin for Twitter Bootstrap
 *
 * Copyright (c) 2008-2013 Tane Piper
 * Copyright (c) 2013 Alejandro Blanco
 * Dual licensed under the MIT and GPL licenses.
 */

/* global ui */

(function($) {
    'use strict';

    ui.percentage = function(options, score, maximun) {
        var result = Math.floor((100 * score) / maximun),
            min = options.ui.progressBarMinPercentage;

        result = result <= min ? min : result;
        result = result > 100 ? 100 : result;
        return result;
    };

    ui.initProgressBar = function(options, $el) {
        var $container = ui.getContainer(options, $el),
            progressbar = '<div class="progress ';

        if (options.ui.bootstrap2) {
            // Boostrap 2
            progressbar +=
                options.ui.progressBarExtraCssClasses + '"><div class="';
        } else {
            // Bootstrap 3 & 4
            progressbar +=
                options.ui.progressExtraCssClasses +
                '"><div class="' +
                options.ui.progressBarExtraCssClasses +
                ' progress-';
        }
        progressbar += 'bar">';

        if (options.ui.showVerdictsInsideProgressBar) {
            progressbar += '<span class="password-verdict"></span>';
        }

        progressbar += '</div></div>';

        if (options.ui.viewports.progress) {
            $container.find(options.ui.viewports.progress).append(progressbar);
        } else {
            $(progressbar).insertAfter($el);
        }
    };

    ui.showProgressBar = function(
        options,
        $el,
        score,
        cssClass,
        verdictCssClass,
        verdictText
    ) {
        var barPercentage;

        if (score === undefined) {
            barPercentage = options.ui.progressBarEmptyPercentage;
        } else {
            barPercentage = ui.percentage(options, score, options.ui.scores[4]);
        }
        ui.updateProgressBar(options, $el, cssClass, barPercentage);
        if (options.ui.showVerdictsInsideProgressBar) {
            ui.updateVerdict(options, $el, verdictCssClass, verdictText);
        }
    };

    ui.updateProgressBar = function(options, $el, cssClass, percentage) {
        var $progressbar = ui.getUIElements(options, $el).$progressbar,
            $bar = $progressbar.find('.progress-bar'),
            cssPrefix = 'progress-';

        if (options.ui.bootstrap2) {
            $bar = $progressbar.find('.bar');
            cssPrefix = '';
        }

        $.each(options.ui.colorClasses, function(idx, value) {
            if (options.ui.bootstrap2 || options.ui.bootstrap3) {
                $bar.removeClass(cssPrefix + 'bar-' + value);
            } else {
                $bar.removeClass('bg-' + value);
            }
        });
        if (options.ui.bootstrap2 || options.ui.bootstrap3) {
            $bar.addClass(
                cssPrefix + 'bar-' + options.ui.colorClasses[cssClass]
            );
        } else {
            $bar.addClass('bg-' + options.ui.colorClasses[cssClass]);
        }
        if (percentage > 0) {
            $bar.css('min-width', options.ui.progressBarMinWidth + 'px');
        } else {
            $bar.css('min-width', '');
        }
        $bar.css('width', percentage + '%');
    };
})(jQuery);
