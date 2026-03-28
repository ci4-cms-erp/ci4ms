/* ═══════════════════════════════════════════════════════════
   CI4MS — Shared JS Utilities
   ═══════════════════════════════════════════════════════════ */

// Auto-inject toast container if not present
$(function() {
    if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container"></div>');
    }
});

/**
 * showToast — Global notification function.
 * Used by all modules — no need to redefine in views.
 * @param {string} msg   Message to display
 * @param {string} type  'success' | 'error'
 */
function showToast(msg, type) {
    type = type || 'success';
    var id = 'toast-' + Date.now();
    var icon = type === 'success' ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger';
    var html = '<div id="' + id + '" class="m-toast m-toast-' + type + '"><i class="fas ' + icon + '"></i> ' + msg + '</div>';
    $('#toast-container').append(html);
    setTimeout(function() { $('#' + id).addClass('show'); }, 50);
    setTimeout(function() {
        $('#' + id).removeClass('show');
        setTimeout(function() { $('#' + id).remove(); }, 300);
    }, 3000);
}

/**
 * ci4msDtLanguage — Central DataTables language configuration.
 * Uses existing /be-assets/plugins/datatables/i18n/{locale}.json files.
 * Usage: language: ci4msDtLanguage()   or   language: ci4msDtLanguage('searchPlaceholder')
 * @param {string} [searchPlaceholder] Optional search placeholder text
 * @returns {object} DataTables language config
 */
function ci4msDtLanguage(searchPlaceholder) {
    var locale = window.CI4MS_LOCALE || 'tr';
    var cfg = {
        url: '/be-assets/plugins/datatables/i18n/' + locale + '.json',
        search: '_INPUT_',
        processing: '<i class="fas fa-spinner fa-spin"></i>',
        paginate: {
            previous: '<i class="fas fa-chevron-left"></i>',
            next: '<i class="fas fa-chevron-right"></i>'
        }
    };
    if (searchPlaceholder) {
        cfg.searchPlaceholder = searchPlaceholder;
    }
    return cfg;
}

function pageImgelfinderDialog() {
    var syncInterval;
    var fm = $('<div/>').dialogelfinder({
        url: '/backend/media/elfinderConnection',
        lang: window.CI4MS_LOCALE || 'en',
        width: 1024,
        height: 768,
        workerBaseUrl:"/be-assets/plugins/elFinder/js/worker",
        destroyOnClose: true,
        cssAutoLoad: [window.location.origin + '/be-assets/css/ci4ms-elfinder.css?v=' + Date.now()],
        getFileCallback: function (files, fm) {
            $('.pageimg-input').val(files.url.replace(location.origin,''));
            $('.pageimg').attr('src',files.url);
            const img = new Image();
            img.onload = function() {
                $('#pageIMGHeight').val(this.height)
                $('#pageIMGWidth').val(this.width)
            }
            img.src = files.url;
        },
        commandsOptions: {
            getfile: {
                oncomplete: 'close',
                folders: false,
                multiple:false
            }
        },
        soundPath: '/be-assets/plugins/elFinder/sounds',
        handlers: {
            upload: function () {
                $('.elfinder-dialog-error').hide();
            },
            open: function(event, instance) {
                // Start sync when elFinder opens
                startSync(instance);
            },
            close: function(event, instance) {
                // Stop sync when elFinder closes
                stopSync();
            },
            destroy: function(event, instance) {
                // Stop sync when elFinder is destroyed
                stopSync();
            }
        }
    }).dialogelfinder('instance');

    function startSync(instance) {
        syncInterval = setInterval(function() {
            instance.exec('sync');
        }, 1000); // 1000 ms (1 saniye)
    }

    function stopSync() {
        clearInterval(syncInterval);
    }
}

function pageMultipleImgelfinderDialog(id) {
    var syncInterval;

    var fm = $('<div/>').dialogelfinder({
        url: '/backend/media/elfinderConnection',
        lang: window.CI4MS_LOCALE || 'en',
        width: '80%',
        height: 768,
        destroyOnClose: true,
        cssAutoLoad: [window.location.origin+'/be-assets/css/ci4ms-elfinder.css?v=' + Date.now()],
        getFileCallback: function (files) {
            $('[name="imgs['+id+'][pageimg]"]').val(files.url.replace(location.origin, ''));
            $('[name="imgs['+id+'][img]"]').attr('src', files.url);
            const img = new Image();
            img.onload = function() {
                $('[name="imgs['+id+'][pageIMGHeight]"]').val(this.height);
                $('[name="imgs['+id+'][pageIMGWidth]"]').val(this.width);
            }
            img.src = files.url;
        },
        commandsOptions: {
            getfile: {
                oncomplete: 'close',
                folders: false
            }
        },
        soundPath: '/be-assets/plugins/elFinder/sounds',
        handlers: {
            upload: function () {
                $('.elfinder-dialog-error').hide();
            },
            open: function(event, instance) {
                // elFinder açıldığında sync başlat
                startSync(instance);
            },
            close: function(event, instance) {
                // elFinder kapandığında sync durdur
                stopSync();
            },
            destroy: function(event, instance) {
                // elFinder destroy edildiğinde sync durdur
                stopSync();
            }
        }
    }).dialogelfinder('instance');

    function startSync(instance) {
        syncInterval = setInterval(function() {
            instance.exec('sync');
        }, 1000); // 1000 ms (1 saniye)
    }

    function stopSync() {
        clearInterval(syncInterval);
    }
}

function multipleImgSelect(id) {
    pageMultipleImgelfinderDialog(id)
}

$('.pageIMG').click(function (){
    pageImgelfinderDialog($(this).closest('.note-editor').parent().children('.pageimg'));
});

$('.pageimg-input').change(function () {
    $('.pageimg').attr('src',$(this).val());
    const img = new Image();
    img.onload = function() {
        $('#pageIMGHeight').val(this.height)
        $('#pageIMGWidth').val(this.width)
    }
    img.src = $(this).val();
});

function elfinderDialog() {
    var fm = $('<div/>').dialogelfinder({
        url: '/backend/media/elfinderConnection',
        lang: window.CI4MS_LOCALE || 'en',
        width: '100%',
        height: 768,
        destroyOnClose: true,
        cssAutoLoad: [window.location.origin + '/be-assets/css/ci4ms-elfinder.css?v=' + Date.now()],
        getFileCallback: function (files, fm) {
            $('.editor').summernote('editor.insertImage', files.url.replace('https://'+location.hostname,''));
        },
        commandsOptions: {
            getfile: {
                oncomplete: 'close',
                folders: false
            }
        },
        soundPath: '/be-assets/plugins/elFinder/sounds',
        sync: 1000,
        handlers: {
            upload: function () {
                $('.elfinder-dialog-error').hide();
            }
        }
    }).dialogelfinder('instance');
}

function tags(data) {
    $('.keywords').tagify({
        whitelist: data,
        dropdown: {
            maxItems: 10,           // <- mixumum allowed rendered suggestions
            classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
            enabled: 0,             // <- show suggestions on focus
            closeOnSelect: false,    // <- do not hide the suggestions dropdown once an item has been selected
            position      : "text",         // place the dropdown near the typed text
            highlightFirst: true
        }
    });
}

$(document).ready(function() {
    if ($('.editor').length > 0) {
        $('.editor').summernote({
            height: 300,
            toolbar: [
                ['style', ['style']],
                ['style', ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['height', ['height']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video', 'hr', 'readmore']],
                ['media', ['elfinder']],
                ['view', ['fullscreen', 'codeview']]
            ]
        });
    }
});
