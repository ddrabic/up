(function ($) {
    'use strict';

    const $container = $('#upp-warehouse-stock');
    if (!$container.length) return;

    const $content = $container.find('.upp-warehouse-stock__content');
    const show = function (html) {
        $content.html(html || '');
        $container.prop('hidden', !html);
    };

    $('.variations_form')
        .on('found_variation', function (_event, variation) {
            show(variation.upp_warehouse_stock_html || '');
        })
        .on('reset_data hide_variation', function () {
            show('');
        });
})(jQuery);
