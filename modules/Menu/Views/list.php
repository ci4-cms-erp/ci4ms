<h4><strong><?php echo lang('Pages.pages') ?></strong></h4>
<?php echo empty($pages) ? '<strong>' . lang('Menu.notFindinMenuPages') . '</strong>' : '' ?>
<form class="list-group" id="addCheckedPages">
    <?php foreach ($pages as $page): ?>
        <div class="list-group-item" id="page-<?php echo $page->id ?>">
            <div class="row d-flex justify-content-between align-items-center">
                <div class="col-xs-8">
                    <label class="ml-3">
                        <input class="form-check-input me-1" type="checkbox" name="pageChecked[]"
                            value="<?php echo $page->id ?>">
                        <?php echo esc($page->title) ?>
                    </label>
                </div>
                <div class="col-xs-4">
                    <button class="btn btn-success addPages" type="button" onclick="addPages('<?php echo $page->id ?>')">
                        <?php echo lang('Backend.add') ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach;
    if (!empty($pages)): ?>
        <div class="list-group-item">
            <button class="btn btn-success float-right" type="button" onclick="addCheckedPages()">
                <?php echo lang('Backend.addSelected') ?>
            </button>
        </div>
    <?php endif; ?>
</form>
<hr>
<h4><strong><?php echo lang('Blog.blogs') ?></strong></h4>
<?php echo empty($blogs) ? '<strong>' . lang('Menu.notFindinMenuBlogs') . '</strong>' : '' ?>
<form class="list-group" id="addCheckedBlog">
    <?php foreach ($blogs as $blog): ?>
        <div class="list-group-item" id="blog-<?php echo $blog->id ?>">
            <div class="row d-flex justify-content-between align-items-center">
                <div class="col-xs-8">
                    <label class="ml-3">
                        <input class="form-check-input me-1" type="checkbox" name="pageChecked[]"
                            value="<?php echo $blog->id ?>">
                        <?php echo esc($blog->title) ?>
                    </label>
                </div>
                <div class="col-xs-4">
                    <button class="btn btn-success" type="button" onclick="addBlog('<?php echo $blog->id ?>')">
                        <?php echo lang('Backend.add') ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach;
    if (!empty($blogs)): ?>
        <div class="list-group-item">
            <button class="btn btn-success float-right" type="button" onclick="addCheckedBlog()">
                <?php echo lang('Backend.addSelected') ?>
            </button>
        </div>
    <?php endif; ?>
</form>
<hr>
<form id="addUrls" method="post" class="form-row mt-2">
    <div class="col-md-5 form-group">
        <input type="text" class="form-control" placeholder="<?php echo lang('Backend.title') ?>" name="URLname">
    </div>
    <div class="col-md-5 form-group">
        <input type="text" class="form-control" placeholder="<?php echo lang('Backend.url') ?>" name="URL">
    </div>
    <div class="col-md-5 form-group">
        <select class="form-control" name="target">
            <option value=""><?php echo lang('Backend.select') ?></option>
            <option value="_blank">_blank</option>
            <option value="_self">_self</option>
            <option value="_parent">_parent</option>
            <option value="_top">_top</option>
        </select>
    </div>
    <div class="col-md-2 form-group">
        <button class="btn btn-success w-100" type="button" onclick="addURL()"><?php echo lang('Backend.add') ?></button>
    </div>
</form>
