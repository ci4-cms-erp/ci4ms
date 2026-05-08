<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/jquery-fancytree/skin-bootstrap/ui.fancytree.min.css");
echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.min.css");
echo link_tag("be-assets/plugins/jquery-contextmenu/jquery.contextMenu.min.css");
echo $this->endSection();
echo $this->section('content'); ?>
<section class="content pt-3">
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="far fa-folder mr-2 text-primary"></i> <?php echo lang($title->pagename) ?>
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="row">
                <div class="col-12">
                    <?php if (config('App')->CSPEnabled): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo lang('Fileeditor.cspWarning') ?>
                        </div>
                    <?php endif; ?>
                </div>
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

                <div class="col-12">
                    <button id="saveFile" class="btn btn-success w-100"><i class="fas fa-save"></i>
                        <?php echo lang('Backend.save') ?></button>
                </div>
            </div>
        </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag('be-assets/plugins/jquery-ui/jquery-ui.min.js');
echo script_tag('be-assets/plugins/jquery-fancytree/jquery.fancytree.min.js');
echo script_tag('be-assets/plugins/jquery-fancytree/modules/jquery.fancytree.edit.js');
echo script_tag('be-assets/plugins/jquery-fancytree/modules/jquery.fancytree.filter.js');
echo script_tag('be-assets/plugins/jquery-fancytree/modules/jquery.fancytree.dnd.js');
echo script_tag('be-assets/plugins/jquery-fancytree/modules/jquery.fancytree.glyph.js');
echo script_tag('be-assets/plugins/jquery-contextmenu/jquery.contextMenu.min.js');
echo script_tag('be-assets/plugins/jquery-contextmenu/jquery.ui.position.min.js');
echo script_tag('be-assets/plugins/monaco-editor/vs/loader.js');
echo script_tag('be-assets/js/fileeditor.js');
echo $this->endSection() ?>
