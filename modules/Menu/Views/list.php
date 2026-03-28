<div id="accordion-menu">
    <!-- Pages Section -->
    <div class="card mb-3 border-0 shadow-none bg-light" style="border-radius:12px">
        <div class="card-header border-0 bg-transparent p-3" id="headingPages">
            <h6 class="mb-0">
                <button class="btn btn-link btn-block text-left font-weight-bold text-dark p-0" data-toggle="collapse" data-target="#collapsePages">
                    <i class="fas fa-file-alt mr-2 text-primary"></i> <?php echo lang('Pages.pages') ?>
                    <i class="fas fa-chevron-down float-right mt-1 small"></i>
                </button>
            </h6>
        </div>
        <div id="collapsePages" class="collapse show" data-parent="#accordion-menu">
            <div class="card-body p-3 pt-0">
                <?php if(empty($pages)): ?>
                    <p class="small text-muted mb-0"><?php echo lang('Menu.notFindinMenuPages') ?></p>
                <?php else: ?>
                    <form id="addCheckedPages">
                        <div class="list-group list-group-flush mb-3">
                            <?php foreach ($pages as $page): ?>
                                <div class="list-group-item bg-transparent px-0 py-2 d-flex align-items-center" id="page-<?php echo $page->id ?>">
                                    <div class="custom-control custom-checkbox mr-3">
                                        <input type="checkbox" class="custom-control-input" id="chk-page-<?php echo $page->id ?>" name="pageChecked[]" value="<?php echo $page->id ?>">
                                        <label class="custom-control-label" for="chk-page-<?php echo $page->id ?>"></label>
                                    </div>
                                    <span class="flex-grow-1 small font-weight-medium"><?php echo esc($page->title) ?></span>
                                    <button class="btn btn-xs btn-outline-success px-2 py-0 border-0" type="button" onclick="addPages('<?php echo $page->id ?>')" title="Ekle"><i class="fas fa-plus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-sm btn-success btn-block" type="button" onclick="addCheckedPages()" style="border-radius:8px">Seçilenleri Ekle</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Blogs Section -->
    <div class="card mb-3 border-0 shadow-none bg-light" style="border-radius:12px">
        <div class="card-header border-0 bg-transparent p-3" id="headingBlogs">
            <h6 class="mb-0">
                <button class="btn btn-link btn-block text-left font-weight-bold text-dark p-0" data-toggle="collapse" data-target="#collapseBlogs">
                    <i class="fas fa-rss mr-2 text-warning"></i> <?php echo lang('Blog.blogs') ?>
                    <i class="fas fa-chevron-down float-right mt-1 small"></i>
                </button>
            </h6>
        </div>
        <div id="collapseBlogs" class="collapse" data-parent="#accordion-menu">
            <div class="card-body p-3 pt-0">
                <?php if(empty($blogs)): ?>
                    <p class="small text-muted mb-0"><?php echo lang('Menu.notFindinMenuBlogs') ?></p>
                <?php else: ?>
                    <form id="addCheckedBlog">
                        <div class="list-group list-group-flush mb-3">
                            <?php foreach ($blogs as $blog): ?>
                                <div class="list-group-item bg-transparent px-0 py-2 d-flex align-items-center" id="blog-<?php echo $blog->id ?>">
                                    <div class="custom-control custom-checkbox mr-3">
                                        <input type="checkbox" class="custom-control-input" id="chk-blog-<?php echo $blog->id ?>" name="pageChecked[]" value="<?php echo $blog->id ?>">
                                        <label class="custom-control-label" for="chk-blog-<?php echo $blog->id ?>"></label>
                                    </div>
                                    <span class="flex-grow-1 small font-weight-medium"><?php echo esc($blog->title) ?></span>
                                    <button class="btn btn-xs btn-outline-success px-2 py-0 border-0" type="button" onclick="addBlog('<?php echo $blog->id ?>')" title="Ekle"><i class="fas fa-plus"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-sm btn-success btn-block" type="button" onclick="addCheckedBlog()" style="border-radius:8px">Seçilenleri Ekle</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Custom URL Section -->
    <div class="card mb-3 border-0 shadow-none bg-light" style="border-radius:12px">
        <div class="card-header border-0 bg-transparent p-3" id="headingURL">
            <h6 class="mb-0">
                <button class="btn btn-link btn-block text-left font-weight-bold text-dark p-0" data-toggle="collapse" data-target="#collapseURL">
                    <i class="fas fa-link mr-2 text-info"></i> Özel Bağlantı
                    <i class="fas fa-chevron-down float-right mt-1 small"></i>
                </button>
            </h6>
        </div>
        <div id="collapseURL" class="collapse" data-parent="#accordion-menu">
            <div class="card-body p-3 pt-0">
                <form id="addUrls" class="small">
                    <div class="form-group mb-2">
                        <label>Başlık</label>
                        <input type="text" class="form-control form-control-sm" name="URLname" placeholder="örn: Ana Sayfa">
                    </div>
                    <div class="form-group mb-2">
                        <label>URL</label>
                        <input type="text" class="form-control form-control-sm" name="URL" placeholder="https://...">
                    </div>
                    <div class="form-group mb-3">
                        <label>Target</label>
                        <select class="form-control form-control-sm" name="target">
                            <option value="_self">_self (Aynı Pencere)</option>
                            <option value="_blank">_blank (Yeni Pencere)</option>
                        </select>
                    </div>
                    <button class="btn btn-sm btn-primary btn-block" type="button" onclick="addURL()" style="border-radius:8px">Ekle</button>
                </form>
            </div>
        </div>
    </div>
</div>
