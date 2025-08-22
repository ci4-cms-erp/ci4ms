<?= $this->extend('Modules\Backend\Views\base') ?>
<?= $this->section('title') ?>
<?= lang($title->pagename) ?>
<?= $this->endSection() ?>
<?= $this->section('head') ?>
<?= link_tag('be-assets/node_modules/jquery.fancytree/dist/skin-bootstrap/ui.fancytree.min.css') ?>
<?= link_tag('be-assets/plugins/jquery-ui/jquery-ui.min.css') ?>
<?= link_tag('be-assets/node_modules/jquery-contextmenu/dist/jquery.contextMenu.min.css') ?>
<style>
    .ui-menu kbd {
        /* Keyboard shortcuts for ui-contextmenu titles */
        float: right;
    }
</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang( $title->pagename) ?></h1>
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
            <h3 class="card-title font-weight-bold"><?= lang( $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
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
                    <button id="saveFile" class="btn btn-primary float-right">Kaydet</button>
                </div>
            </div>
        </div>
</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?= script_tag('be-assets/plugins/jquery-ui/jquery-ui.min.js') ?>
<?= script_tag('be-assets/node_modules/jquery.fancytree/dist/jquery.fancytree.min.js') ?>
<?= script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.edit.js') ?>
<?= script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.filter.js') ?>
<?= script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.dnd.js') ?>
<?= script_tag('be-assets/node_modules/jquery.fancytree/dist/modules/jquery.fancytree.glyph.js') ?>
<?= script_tag('be-assets/node_modules/jquery-contextmenu/dist/jquery.contextMenu.min.js') ?>
<?= script_tag('be-assets/node_modules/jquery-contextmenu/dist/jquery.ui.position.min.js') ?>
<?= script_tag('be-assets/node_modules/monaco-editor/min/vs/loader.js') ?>
<script>
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
            language: "php",
            theme: "vs-dark",
            automaticLayout: true
        });
    });

    const $saveButton = $('#saveFile');

    // Load file list
    $('#fileTree').fancytree({
        source: {
            url: '<?= route_to("listfiles") ?>', // Endpoint to fetch file list
            cache: false // Disable cache for live updates
        },
        extensions: ["edit", "filter", "dnd", "glyph"],
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
                url: '<?= route_to("listfiles") ?>',
                data: {
                    path: node.key
                }
            };
        },
        activate: function(event, data) {
            const node = data.node;
            if (!node.folder) {
                loadFileContent(node.key);
            }
        },
        init: function(event, data) {
            data.tree.visit(function(node) {
                if (node.title === 'app' || node.title === 'modules' || node.title === 'public') {
                    if (node.title === 'modules') {
                        node.setExpanded(true).done(function() {
                            console.log('modules children:', node.children);
                            $.each(node.children, function(i, child) {
                                console.log('child:', child);
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
            beforeEdit: function(event, data) {
                // Return false to prevent edit mode
            },
            edit: function(event, data) {
                // Editor was opened (available as data.input)
            },
            beforeClose: function(event, data) {
                // Return false to prevent cancel/save (data.input is available)
            },
            save: function(event, data) {
                // Save data.input.val() or return false to keep editor open
                $.ajax({
                    url: '<?= route_to("renameFile") ?>',
                    method: 'POST',
                    data: {
                        path: data.node.key,
                        newName: data.input.val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Dosya adı başarıyla değiştirildi.');
                        } else {
                            alert('Dosya adı değiştirilemedi.');
                        }
                    },
                    error: function() {
                        alert('Dosya adı değiştirilemedi.');
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
        },
        dnd: {
            autoExpandMS: 400,
            focusOnClick: true,
            preventVoidMoves: true, // Prevent dropping nodes 'before self', etc.
            preventRecursiveMoves: true, // Prevent dropping nodes on own descendants
            dragStart: function(node, data) {
                return true;
            },
            dragEnter: function(node, data) {
                return true;
            },
            dragDrop: function(node, data) {
                $.ajax({
                    url: '<?= route_to("moveFileOrFolder") ?>',
                    method: 'POST',
                    data: {
                        sourcePath: data.otherNode.key,
                        targetPath: node.key
                    },
                    success: function(response) {
                        if (response.success) {
                            data.otherNode.moveTo(node, data.hitMode);
                        } else {
                            alert('Dosya veya klasör taşınamadı.');
                        }
                    },
                    error: function() {
                        alert('Dosya veya klasör taşınamadı.');
                    }
                });
            }
        }
    });

    // Context menu for creating files and folders
    $.contextMenu({
        selector: '#fileTree span.fancytree-title',
        items: {
            "createFile": {
                name: "Yeni Dosya",
                icon: "file",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    var fileName = prompt("Yeni dosya adı:");
                    if (fileName) {
                        $.ajax({
                            url: '<?= route_to("createFile") ?>',
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
                                    alert('Dosya başarıyla oluşturuldu.');
                                } else {
                                    alert('Dosya oluşturulamadı.');
                                }
                            },
                            error: function() {
                                alert('Dosya oluşturulamadı.');
                            }
                        });
                    }
                }
            },
            "createFolder": {
                name: "Yeni Klasör",
                icon: "folder",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    var folderName = prompt("Yeni klasör adı:");
                    if (folderName) {
                        $.ajax({
                            url: '<?= route_to("createFolder") ?>',
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
                                    alert('Klasör başarıyla oluşturuldu.');
                                } else {
                                    alert('Klasör oluşturulamadı.');
                                }
                            },
                            error: function() {
                                alert('Klasör oluşturulamadı.');
                            }
                        });
                    }
                }
            },
            "sep1": "---------",
            "rename": {
                name: "Yeniden Adlandır",
                icon: "edit",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    node.editStart();
                }
            },
            "delete": {
                name: "Sil",
                icon: "delete",
                callback: function(key, opt) {
                    var node = $.ui.fancytree.getNode(opt.$trigger);
                    if (confirm("Silmek istediğinize emin misiniz?")) {
                        $.ajax({
                            url: '<?= route_to("deleteFileOrFolder") ?>',
                            method: 'POST',
                            data: {
                                path: node.key
                            },
                            success: function(response) {
                                if (response.success) {
                                    node.remove();
                                    alert('Dosya veya klasör başarıyla silindi.');
                                } else {
                                    alert('Dosya veya klasör silinemedi.');
                                }
                            },
                            error: function() {
                                alert('Dosya veya klasör silinemedi.');
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
    const loadFileContent = (path) => {
        $.ajax({
            url: `<?= route_to('readFile') ?>`,
            data: {
                path: path
            },
            dataType: 'json',
            success: function(data) {
                if (data.content) {
                    editor.setValue(data.content);
                    currentPath = path;
                } else {
                    alert('Dosya okunamadı.');
                }
            },
            error: function() {
                console.error('Dosya içeriği yüklenemedi.');
            }
        });
    };
    $('.fancytree-container').addClass('p-2');
    // Save file
    $saveButton.on('click', function() {
        const content = editor.getValue();
        $.ajax({
            url: `<?= route_to('saveFile') ?>`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                path: currentPath,
                content: content
            }),
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    alert('Dosya başarıyla kaydedildi.');
                } else {
                    alert('Dosya kaydedilemedi.');
                }
            },
            error: function() {
                console.error('Dosya kaydedilemedi.');
            }
        });
    });
</script>

<?= $this->endSection() ?>
