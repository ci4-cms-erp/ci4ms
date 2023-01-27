function pageImgelfinderDialog() {
    var fm = $('<div/>').dialogelfinder({
        url: '/be-assets/plugins/elFinder/php/connector.minimal.php', // change with the url of your connector
        lang: 'en',
        width: 1024,
        height: 768,
        destroyOnClose: true,
        cssAutoLoad: [window.location.origin+'/be-assets/node_modules/elfinder-material-theme/Material/css/theme-gray.css'],
        getFileCallback: function (files, fm) {
            $('.pageimg-input').val(files.url);
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
        }
    }).dialogelfinder('instance');
}

function pageMultipleImgelfinderDialog(id) {
    console.log(id);
    var fm = $('<div/>').dialogelfinder({
        url: '/be-assets/plugins/elFinder/php/connector.minimal.php', // change with the url of your connector
        lang: 'en',
        width: '80%',
        height: 768,
        destroyOnClose: true,
        cssAutoLoad: [window.location.origin+'/be-assets/node_modules/elfinder-material-theme/Material/css/theme-gray.css'],

        getFileCallback: function (files) {
            $('[name="imgs['+id+'][pageimg]"]').val(files.url);
            $('[name="imgs['+id+'][img]').attr('src',files.url);//TODO: çözülecek
            const img = new Image();
            img.onload = function() {
                $('[name="imgs['+id+'][pageIMGHeight]"]').val(this.height)
                $('[name="imgs['+id+'][pageIMGWidth]"]').val(this.width)
            }
            img.src = files.url;
        },
        commandsOptions: {
            getfile: {
                oncomplete: 'close',
                folders: false
            }
        }
    }).dialogelfinder('instance');
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
        url: '/be-assets/plugins/elFinder/php/connector.minimal.php', // change with the url of your connector
        lang: 'en',
        width: '100%',
        height: 768,
        destroyOnClose: true,
        cssAutoLoad: [window.location.origin+'/be-assets/node_modules/elfinder-material-theme/Material/css/theme-gray.css'],
        getFileCallback: function (files, fm) {
            $('.editor').summernote('editor.insertImage', files.url);
        },
        commandsOptions: {
            getfile: {
                oncomplete: 'close',
                folders: false
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