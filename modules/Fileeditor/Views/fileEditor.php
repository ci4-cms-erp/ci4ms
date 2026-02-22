<?php echo $this->extend('Modules\Backend\Views\base') ?>
<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>
<?php echo $this->section('head') ?>
<?php echo link_tag('be-assets/node_modules/jquery.fancytree/dist/skin-bootstrap/ui.fancytree.min.css') ?>
<?php echo link_tag('be-assets/plugins/jquery-ui/jquery-ui.min.css') ?>
<?php echo link_tag('be-assets/node_modules/jquery-contextmenu/dist/jquery.contextMenu.min.css') ?>
<style {csp-style-nonce}>
    .ui-menu kbd {
        /* Keyboard shortcuts for ui-contextmenu titles */
        float: right;
    }
</style>
<?php echo $this->endSection() ?>
<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right"></ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-3">
                    <div class="row">
                        <div class="col-12 input-group">
                            <input name="search" class="form-control" placeholder="Filter..." autocomplete="off">
                            <button id="btnResetSearch" class="btn btn-dark">&times;</button>
                        </div>
                        <div id="fileTree" class="col-12 p-1"></div>
                    </div>
                </div>
                <div class="col-9">
                    <div id="editorContainer" class="h-100"></div>
                </div>

                <div class="col-12 float-end">
                    <button id="saveFile" class="btn btn-primary float-right"><?php echo lang('Backend.save') ?></button>
                </div>
            </div>
        </div>
</section>
<!-- /.content -->
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag('be-assets/plugins/jquery-ui/jquery-ui.min.js') ?>
<?php echo script_tag('be-assets/node_modules/jquery.fancytree/dist/jquery.fancytree.min.js') ?>
<?php echo script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.edit.js') ?>
<?php echo script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.filter.js') ?>
<?php echo script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.dnd.js') ?>
<?php echo script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.glyph.js') ?>
<?php echo script_tag('be-assets/node_modules/jquery-contextmenu/dist/jquery.contextMenu.min.js') ?>
<?php echo script_tag('be-assets/node_modules/jquery-contextmenu/dist/jquery.ui.position.min.js') ?>
<?php echo script_tag('be-assets/node_modules/monaco-editor/min/vs/loader.js') ?>
<script {csp-script-nonce}>
    $('body').addClass('sidebar-collapse');

    let currentPath = '';
    let editor;

    // Monaco Editor Initialization
    require.config({
        paths: {
            'vs': '/be-assets/node_modules/monaco-editor/min/vs'
        }
    });
    require(['vs/editor/editor.main'], function() {
        editor = monaco.editor.create($('#editorContainer')[0], {
            value: "",
            language: 'php',
            theme: "vs-dark",
            automaticLayout: true
        });
    });

    const $saveButton = $('#saveFile');

    function filterNodes(nodes) {
        return nodes.filter(function(node) {
            const title = node.title.toLowerCase(); // Küçük harfe dönüştür (case-insensitive)
            // Gizli dosyaları/klasörleri filtrele ('.' ile başlar)
            if (title.startsWith('.')) {
                return false; // Gizle
            }
            // Özel filtre: .github klasörünü gizle
            if (node.folder && title === '.github') {
                return false; // Gizle
            }
            return true; // Göster
        });
    }
    // Load file list
    $('#fileTree').fancytree({
        source: {
            url: '<?php echo route_to("listfiles") ?>', // Endpoint to fetch file list
            cache: false, // Disable cache for live updates
            postProcess: function(event, data) {
                // Dönen veriyi filtrele
                data.result = filterNodes(data.result);
                data.result.sort(function(a, b) {
                    return a.title.localeCompare(b.title, undefined, {
                        sensitivity: 'base'
                    });
                });
            }
        },
        extensions: ["edit", "filter", "glyph"],
        quicksearch: true,
        filter: {
            autoApply: true, // Re-apply last filter if lazy data is loaded
            autoExpand: true, // Expand all branches that contain matches while filtered
            counter: true, // Show a badge with number of matching child nodes near parent icons
            fuzzy: false, // Match single characters in order, e.g. 'fb' will match 'FooBar'
            hideExpandedCounter: true, // Hide counter badge if parent is expanded
            hideExpanders: false, // Hide expanders if all child nodes are hidden by filter
            highlight: true, // Highlight matches by wrapping inside <mark> tags
            leavesOnly: false, // Match end nodes only
            nodata: true, // Display a 'no data' status node if result is empty
            mode: "dimm" // Grayout unmatched nodes (pass "hide" to remove unmatched node instead)
        },
        glyph: {
            preset: "awesome5"
        },
        lazyLoad: function(event, data) {
            const node = data.node;
            data.result = {
                url: '<?php echo route_to("listfiles") ?>',
                data: {
                    path: node.key
                },
                postProcess: function(event, data) {
                    // Lazy load verisini de filtrele (alt klasörler için)
                    data.result = filterNodes(data.result);
                    data.result.sort(function(a, b) {
                        return a.title.localeCompare(b.title, undefined, {
                            sensitivity: 'base'
                        });
                    });
                }
            };
        },
        activate: function(event, data) {
            const node = data.node;
            if (!node.folder) {
                loadFileContent(node.key, node.title);
            }
        },
        init: function(event, data) {
            const rootNode = data.tree.getRootNode();
            rootNode.sortChildren(null, true); // null: varsayılan comparator (alfabetik), true:
            data.tree.visit(function(node) {
                if (node.title === 'app' || node.title === 'modules') {
                    if (node.title === 'modules') {
                        node.setExpanded(true).done(function() {
                            $.each(node.children, function(i, child) {
                                child.setExpanded(true);
                            });
                        });
                    } else {
                        node.setExpanded(true);
                    }
                } else {
                    node.setExpanded(false);
                }
            });
        },
        edit: {
            triggerStart: ["f2"],
            save: function(event, data) {
                // Save data.input.val() or return false to keep editor open
                $.ajax({
                    url: '<?php echo route_to("renameFile") ?>',
                    method: 'POST',
                    data: {
                        path: data.node.key,
                        newName: data.input.val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo lang('Fileeditor.renameSuccess') ?>');
                        } else {
                            alert('<?php echo lang('Fileeditor.renameFailed') ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo lang('Fileeditor.renameFailed') ?>');
                    }
                });
            },
            close: function(event, data) {
                // Editor was removed
                if (data.save) {
                    // Since we started an async request, mark the node as preliminary
                    $(data.node.span).addClass("pending");
                }
            }
        }
    });

    // Context menu for creating files and folders
    $.contextMenu({
        selector: '#fileTree span.fancytree-title',
        items: {
            "createFile": {
                name: "<?php echo lang('Fileeditor.newFile') ?>",
                icon: "file",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    var fileName = prompt("<?php echo lang('Fileeditor.newFileName') ?>");
                    if (fileName) {
                        $.ajax({
                            url: '<?php echo route_to("createFile") ?>',
                            method: 'POST',
                            data: {
                                path: node.key,
                                name: fileName
                            },
                            success: function(response) {
                                if (response.success) {
                                    node.addChildren({
                                        title: fileName,
                                        key: node.key + '/' + fileName,
                                        folder: false
                                    });
                                    alert('<?php echo lang('Fileeditor.fileCreated') ?>');
                                } else {
                                    alert('<?php echo lang('Fileeditor.fileCreateFailed') ?>');
                                }
                            },
                            error: function() {
                                alert('<?php echo lang('Fileeditor.fileCreateFailed') ?>');
                            }
                        });
                    }
                }
            },
            "createFolder": {
                name: "<?php echo lang('Fileeditor.newFolder') ?>",
                icon: "folder",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    var folderName = prompt("Yeni klasör adı:");
                    if (folderName) {
                        $.ajax({
                            url: '<?php echo route_to("createFolder") ?>',
                            method: 'POST',
                            data: {
                                path: node.key,
                                name: folderName
                            },
                            success: function(response) {
                                if (response.success) {
                                    node.addChildren({
                                        title: folderName,
                                        key: node.key + '/' + folderName,
                                        folder: true
                                    });
                                    alert('<?php echo lang('Fileeditor.folderCreated') ?>');
                                } else {
                                    alert('<?php echo lang('Fileeditor.folderCreateFailed') ?>');
                                }
                            },
                            error: function() {
                                alert('<?php echo lang('Fileeditor.folderCreateFailed') ?>');
                            }
                        });
                    }
                }
            },
            "sep1": "---------",
            "rename": {
                name: "<?php echo lang('Fileeditor.rename') ?>",
                icon: "edit",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    node.editStart();
                }
            },
            "delete": {
                name: '<?php echo lang('Fileeditor.delete') ?>',
                icon: "delete",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    if (confirm("<?php echo lang('Fileeditor.confirmDelete') ?>")) {
                        $.ajax({
                            url: '<?php echo route_to("deleteFileOrFolder") ?>',
                            method: 'POST',
                            data: {
                                path: node.key
                            },
                            success: function(response) {
                                if (response.success) {
                                    node.remove();
                                    alert('<?php echo lang('Fileeditor.deleteSuccess') ?>');
                                } else {
                                    alert('<?php echo lang('Fileeditor.deleteFailed') ?>');
                                }
                            },
                            error: function() {
                                alert('<?php echo lang('Fileeditor.deleteFailed') ?>');
                            }
                        });
                    }
                }
            }
        }
    });

    var tree = $.ui.fancytree.getTree("#fileTree");
    $("input[name=search]").on("keyup", function(e) {
        var n,
            tree = $.ui.fancytree.getTree(),
            args = "autoApply autoExpand fuzzy hideExpanders highlight leavesOnly nodata".split(" "),
            opts = {},
            filterFunc = tree.filterNodes,
            match = $(this).val();

        $.each(args, function(i, o) {
            opts[o] = $("#" + o).is(":checked");
        });
        opts.mode = "dimm";

        if (e && e.which === $.ui.keyCode.ESCAPE || $.trim(match) === "") {
            $("button#btnResetSearch").trigger("click");
            return;
        }
        n = filterFunc.call(tree, match, opts);
        $("button#btnResetSearch").attr("disabled", false);
        $("span#matches").text("(" + n + " matches)");

        // Expand all matched nodes and their parents
        tree.visit(function(node) {
            if (node.match) {
                node.makeVisible();
            }
        });
    }).focus();

    $("button#btnResetSearch").click(function(e) {
        $("input[name=search]").val("");
        $("span#matches").text("");
        tree.clearFilter();
    }).attr("disabled", true);

    $("fieldset input:checkbox").change(function(e) {
        var id = $(this).attr("id"),
            flag = $(this).is(":checked");

        // Some options can only be set with general filter options (not method args):
        switch (id) {
            case "counter":
            case "hideExpandedCounter":
                tree.options.filter[id] = flag;
                break;
        }
        tree.clearFilter();
        $("input[name=search]").keyup();
    });

    // Load file content
    const loadFileContent = (path, fileName) => {
        $.ajax({
            url: `<?php echo route_to('readFile') ?>`,
            data: {
                path: path
            },
            dataType: 'json',
            success: function(data) {
                if (data.content) {
                    const currentModel = editor.getModel();
                    if (currentModel) {
                        currentModel.dispose(); // Eski modeli temizle (hafıza yönetimi için)
                    }

                    // Yeni model: Dil undefined bırak, URI ile dosya adını ver (otomatik algılama için)
                    const newModel = monaco.editor.createModel(
                        data.content,
                        undefined, // Dil otomatik algılansın (uzantıya göre)
                        monaco.Uri.file(fileName) // Dosya adından uzantı algılanır
                    );

                    // Editör'e yeni modeli ata
                    editor.setModel(newModel);
                    currentPath = path;
                } else {
                    alert('<?php echo lang('Fileeditor.fileReadFailed') ?>');
                }
            },
            error: function() {
                console.error('<?php echo lang('Fileeditor.fileContentLoadFailed') ?>');
            }
        });
    };
    $('.fancytree-container').addClass('p-2');
    // Save file
    $saveButton.on('click', function() {
        const content = editor.getValue();
        $.ajax({
            url: `<?php echo route_to('saveFile') ?>`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                path: currentPath,
                content: content
            }),
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    alert('<?php echo lang('Fileeditor.fileSaveSuccess') ?>');
                } else {
                    alert('<?php echo lang('Fileeditor.fileSaveFailed') ?>');
                }
            },
            error: function() {
                console.error('<?php echo lang('Fileeditor.fileSaveFailed') ?>');
            }
        });
    });
</script>

<?php echo $this->endSection() ?>
