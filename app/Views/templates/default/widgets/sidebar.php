<div class="col-md-3">
    <?php if (!empty($settings->templateInfos->widgets->sidebar->searchWidget) && (boolean)$settings->templateInfos->widgets->sidebar->searchWidget === true):
        echo view('templates/default/widgets/searchForm');
    endif;
    if (!empty($settings->templateInfos->widgets->sidebar->categoriesWidget) && (boolean)$settings->templateInfos->widgets->sidebar->categoriesWidget === true):
        echo view('templates/default/widgets/categories');
    endif;
    if (!empty($settings->templateInfos->widgets->sidebar->archiveWidget) && (boolean)$settings->templateInfos->widgets->sidebar->archiveWidget === true):
        echo view('templates/default/widgets/archive');
    endif; ?>
</div>
