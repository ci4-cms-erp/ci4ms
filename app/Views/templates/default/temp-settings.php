<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<style>
    .btn-add-row {
        background: none;
        border: 1px dashed #aaa;
        color: #666;
        border-radius: 4px;
        padding: 5px 14px;
        cursor: pointer;
        font-size: 13px;
        transition: all .15s;
        width: 100%;
        margin-top: 4px;
    }

    .btn-add-row:hover {
        border-color: #555;
        color: #333;
        background: #f9f9f9;
    }

    /* ── Section headers ── */
    .section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        border-left: 4px solid #d278c9;
        padding-left: 12px;
        margin-bottom: 18px;
        margin-top: 4px;
    }

    .section-header i {
        color: #804f7b;
        font-size: 18px;
    }

    .section-header h5 {
        margin: 0;
        font-weight: 700;
        color: #333;
    }

    .section-header small {
        color: #999;
        font-size: 12px;
    }

    /* ── Card tabs ── */
    .settings-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #dee2e6;
        margin-bottom: 20px;
    }

    .settings-tab {
        padding: 10px 20px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        color: #6c757d;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all .15s;
    }

    .settings-tab:hover {
        color: #804f7b;
    }

    .settings-tab.active {
        color: #804f7b;
        border-bottom-color: #d278c9;
    }

    .settings-panel {
        display: none;
    }

    .settings-panel.active {
        display: block;
    }

    /* ── Preview box ── */
    .font-preview {
        margin-top: 8px;
        padding: 10px 14px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        font-size: 18px;
        color: #333;
        transition: font-family .3s;
    }
</style>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Content Header -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="fas fa-palette mr-2" style="color:#804f7b"></i><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('settings') ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-arrow-circle-left"></i> Ayarlara Dön
                    </a>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php echo view('Modules\Auth\Views\_message_block') ?>

        <form action="<?php echo route_to('templateSettings_post') ?>" method="post" id="theme-settings-form">
            <?php echo csrf_field() ?>

            <!-- Tab Navigation -->
            <div class="settings-tabs">
                <div class="settings-tab active" data-tab="widgets">
                    <i class="fas fa-th-large mr-1"></i> Bileşenler
                </div>
                <div class="settings-tab" data-tab="assets">
                    <i class="fas fa-code mr-1"></i> CSS / JS Varlıkları
                </div>
                <div class="settings-tab" data-tab="custom-code">
                    <i class="fas fa-file-code mr-1"></i> Özel Kod
                </div>
                <div class="settings-tab" data-tab="footer">
                    <i class="fas fa-shoe-prints mr-1"></i> Footer
                </div>
                <div class="settings-tab" data-tab="fonts">
                    <i class="fas fa-font mr-1"></i> Yazı Tipi
                </div>
            </div>

            <!-- ── TAB 1: Widgets ── -->
            <div class="settings-panel active" id="tab-widgets">
                <div class="row">
                    <!-- Sidebar -->
                    <div class="col-md-6">
                        <div class="card card-outline card-success shadow-sm">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-columns"></i>
                                    <div>
                                        <h5>Sidebar Bileşenleri</h5>
                                        <small>Blog kenar çubuğunda hangi widget'ların görüneceğini seçin</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="settings[widgets][sidebar][searchWidget]" value="true" id="searchWidget" class="custom-control-input"
                                            <?php echo !empty($settings->templateInfos->widgets['sidebar']['searchWidget']) && (bool)$settings->templateInfos->widgets['sidebar']['searchWidget'] === true ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="searchWidget"><i class="fas fa-search text-muted mr-1"></i> Arama Kutusu</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="settings[widgets][sidebar][categoriesWidget]" value="true" id="categoriesWidget" class="custom-control-input"
                                            <?php echo !empty($settings->templateInfos->widgets['sidebar']['categoriesWidget']) && (bool)$settings->templateInfos->widgets['sidebar']['categoriesWidget'] === true ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="categoriesWidget"><i class="fas fa-list text-muted mr-1"></i> Kategori Listesi</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="settings[widgets][sidebar][archiveWidget]" value="true" id="archiveWidget" class="custom-control-input"
                                            <?php echo !empty($settings->templateInfos->widgets['sidebar']['archiveWidget']) && (bool)$settings->templateInfos->widgets['sidebar']['archiveWidget'] === true ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="archiveWidget"><i class="fas fa-archive text-muted mr-1"></i> Arşiv Listesi</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Genel ayarlar -->
                    <div class="col-md-6">
                        <div class="card card-outline card-primary shadow-sm">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-cogs"></i>
                                    <div>
                                        <h5>Genel Görünüm</h5>
                                        <small>Genel tema görünüm seçenekleri</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="settings[display][breadcrumbs]" id="breadcrumbs" value="true" class="custom-control-input"
                                            <?php echo !empty($settings->templateInfos->display['breadcrumbs']) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="breadcrumbs"><i class="fas fa-sitemap text-muted mr-1"></i> Breadcrumb (Yol İzi)</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="settings[display][backToTop]" id="backToTop" value="true" class="custom-control-input"
                                            <?php echo !empty($settings->templateInfos->display['backToTop']) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="backToTop"><i class="fas fa-arrow-up text-muted mr-1"></i> Üste Dön Butonu</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" name="settings[display][darkModeToggle]" id="darkModeToggle" value="true" class="custom-control-input"
                                            <?php echo !empty($settings->templateInfos->display['darkModeToggle']) ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="darkModeToggle"><i class="fas fa-moon text-muted mr-1"></i> Karanlık Mod Butonu</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB 2: CSS/JS Assets ── -->
            <div class="settings-panel" id="tab-assets">
                <div class="row">
                    <!-- CSS -->
                    <div class="col-md-6">
                        <div class="card card-outline card-info shadow-sm">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-paint-brush"></i>
                                    <div>
                                        <h5>CSS Dosyaları</h5>
                                        <small>Temaya eklenecek stylesheet URL'leri (sıralı yüklenir)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="css-asset-list">
                                    <?php
                                    $cssAssets = $settings->templateInfos->theme_assets['styles'] ?? [
                                        '/templates/default/assets/vendor/modern-business/styles.css',
                                        '/templates/default/assets/ci4ms.css',
                                    ];
                                    foreach ($cssAssets as $url): ?>
                                        <div class="form-group">
                                            <div class="input-group">
                                                <input type="text" class="form-control form-control-sm"
                                                    name="settings[theme_assets][styles][]"
                                                    value="<?php echo esc($url) ?>"
                                                    placeholder="örn: /templates/default/assets/style.css">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-add-row" onclick="addAssetRow('css-asset-list','settings[theme_assets][styles][]','örn: /templates/default/assets/style.css')">
                                    <i class="fas fa-plus"></i> CSS Ekle
                                </button>
                                <div class="alert alert-light mt-3 mb-0 small">
                                    <i class="fas fa-info-circle text-info"></i>
                                    Göreli yollar (<code>/templates/...</code>) veya tam URL (<code>https://cdn.../style.css</code>) kullanabilirsiniz.
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- JS -->
                    <div class="col-md-6">
                        <div class="card card-outline card-warning shadow-sm">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-code"></i>
                                    <div>
                                        <h5>JavaScript Dosyaları</h5>
                                        <small>Temaya eklenecek script URL'leri (sıralı yüklenir)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="js-asset-list">
                                    <?php
                                    $jsAssets = $settings->templateInfos->theme_assets['scripts'] ?? [
                                        '/templates/default/assets/vendor/jquery/jquery.min.js',
                                        '/templates/default/assets/vendor/bootstrap/bootstrap.bundle.min.js',
                                    ];
                                    foreach ($jsAssets as $url): ?>
                                        <div class="form-group">
                                            <div class="input-group">
                                                <input type="text" class="form-control form-control-sm"
                                                    name="settings[theme_assets][scripts][]"
                                                    value="<?php echo esc($url) ?>"
                                                    placeholder="örn: /templates/default/assets/script.js">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-add-row" onclick="addAssetRow('js-asset-list','settings[theme_assets][scripts][]','örn: /templates/default/assets/app.js')">
                                    <i class="fas fa-plus"></i> JS Ekle
                                </button>
                                <div class="alert alert-light mt-3 mb-0 small">
                                    <i class="fas fa-info-circle text-warning"></i>
                                    Bu listede <strong>GrapesJS canvas'ı</strong> da aynı varlıkları yükler — gerçek temayı editörde görürsünüz.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB 3: Özel Kod ── -->
            <div class="settings-panel" id="tab-custom-code">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-outline card-danger shadow-sm">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-file-code"></i>
                                    <div>
                                        <h5>Özel CSS Kodu</h5>
                                        <small>Tüm sayfalara &lt;style&gt; olarak eklenir</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="settings[customCss]" rows="12"
                                    placeholder="/* Özel CSS kodunuz buraya */&#10;body { font-family: 'Roboto', sans-serif; }&#10;.navbar { box-shadow: 0 2px 10px rgba(0,0,0,.1); }"
                                    style="font-family:monospace; font-size:12px;"><?php echo esc($settings->templateInfos->customCss ?? '') ?></textarea>
                                <small class="text-muted mt-1 d-block">Bu kod, base.php'de &lt;style&gt; etiketi içinde render edilir.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-outline card-secondary shadow-sm">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-terminal"></i>
                                    <div>
                                        <h5>Özel JavaScript Kodu</h5>
                                        <small>Tüm sayfalara &lt;script&gt; olarak eklenir</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="settings[customJs]" rows="12"
                                    placeholder="// Özel JS kodunuz buraya&#10;document.addEventListener('DOMContentLoaded', function() {&#10;  console.log('Tema yüklendi!');&#10;});"
                                    style="font-family:monospace; font-size:12px;"><?php echo esc($settings->templateInfos->customJs ?? '') ?></textarea>
                                <small class="text-muted mt-1 d-block">Bu kod, body kapanmadan önce &lt;script&gt; etiketi içinde render edilir.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB 4: Footer ── -->
            <div class="settings-panel" id="tab-footer">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card card-outline card-dark shadow-sm">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-shoe-prints"></i>
                                    <div>
                                        <h5>Footer Ayarları</h5>
                                        <small>Sitenin alt bölümü için ayarlar</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-copyright mr-1"></i> Telif Hakkı Metni</label>
                                    <input type="text" class="form-control"
                                        name="settings[footer][copyright]"
                                        value="<?php echo esc($settings->templateInfos->footer['copyright'] ?? '') ?>"
                                        placeholder="Örn: © 2025 Şirket Adı. Tüm hakları saklıdır.">
                                    <small class="text-muted">Boş bırakırsanız varsayılan site adı kullanılır.</small>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold"><i class="fas fa-link mr-1"></i> Footer Linkleri</label>
                                    <div id="footer-links-list">
                                        <?php
                                        $footerLinks = $settings->templateInfos->footer['links'] ?? [];
                                        foreach ($footerLinks as $link): ?>
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <input type="text" class="form-control form-control-sm"
                                                        style="flex:2"
                                                        name="settings[footer][links][][label]"
                                                        value="<?php echo esc($link['label'] ?? '') ?>"
                                                        placeholder="Etiket (örn: Gizlilik)">
                                                    <input type="text" class="form-control form-control-sm"
                                                        style="flex:3"
                                                        name="settings[footer][links][][url]"
                                                        value="<?php echo esc($link['url'] ?? '') ?>"
                                                        placeholder="URL">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn-add-row" onclick="addFooterLinkRow()">
                                        <i class="fas fa-plus"></i> Link Ekle
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB 5: Fonts ── -->
            <div class="settings-panel" id="tab-fonts">
                <div class="row">
                    <div class="col-md-7">
                        <div class="card card-outline card-purple shadow-sm" style="border-color:#d278c9">
                            <div class="card-header">
                                <div class="section-header">
                                    <i class="fas fa-font"></i>
                                    <div>
                                        <h5>Google Fonts Seçici</h5>
                                        <small>Tema için kullanılacak yazı tipini seçin</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="font-weight-bold">Font Adı (Google Fonts)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="font-name-input"
                                            name="settings[fonts][googleFont]"
                                            value="<?php echo esc($settings->templateInfos->fonts['googleFont'] ?? '') ?>"
                                            placeholder="örn: Roboto, Open Sans, Poppins, Lato">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" id="preview-font-btn">
                                                <i class="fas fa-eye"></i> Önizle
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <a href="https://fonts.google.com" target="_blank">fonts.google.com</a>'dan font adını kopyalayın.
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">Font Ağırlıkları</label>
                                    <input type="text" class="form-control"
                                        name="settings[fonts][weights]"
                                        value="<?php echo esc($settings->templateInfos->fonts['weights'] ?? '400,600,700') ?>"
                                        placeholder="400,600,700">
                                    <small class="text-muted">Virgülle ayrılmış ağırlık değerleri.</small>
                                </div>
                                <div class="font-preview" id="font-preview-box">
                                    Merhaba Dünya! The quick brown fox jumps over the lazy dog. 123456
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card card-outline card-light shadow-sm">
                            <div class="card-header">
                                <h5 class="card-title">Popüler Fontlar</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach (['Roboto', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Raleway', 'Nunito', 'Oswald', 'Playfair Display', 'Inter'] as $f): ?>
                                    <div class="font-option" style="padding:8px 14px; border-bottom:1px solid #f0f0f0; cursor:pointer; display:flex; justify-content:space-between; align-items:center;"
                                        onclick="selectFont('<?php echo $f ?>')">
                                        <span><?php echo $f ?></span>
                                        <small class="text-muted">Seç →</small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save -->
            <div class="row mt-4">
                <div class="col-12">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-save mr-1"></i> Kaydet
                    </button>
                    <a href="<?php echo route_to('settings') ?>" class="btn btn-outline-secondary ml-2">İptal</a>
                </div>
            </div>
        </form>
    </div>
</section>
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag('be-assets/plugins/bs-custom-file-input/bs-custom-file-input.min.js') ?>
<script>
    // ── Tab switching ──
    document.querySelectorAll('.settings-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
        });
    });

    // ── Asset row helpers ──
    function addAssetRow(listId, inputName, placeholder) {
        const list = document.getElementById(listId);
        const row = document.createElement('div');
        row.className = 'form-group';
        row.innerHTML = `
        <div class="input-group">
        <input type="text" class="form-control form-control-sm" name="${inputName}" placeholder="${placeholder}">
        <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button>
        </div>`;
        list.appendChild(row);
        row.querySelector('input').focus();
    }

    function removeRow(btn) {
        btn.closest('.form-group').remove();
    }

    function addFooterLinkRow() {
        const list = document.getElementById('footer-links-list');
        const row = document.createElement('div');
        row.className = 'form-group';
        row.innerHTML = `
        <div class="input-group">
        <input type="text" class="form-control form-control-sm" style="flex:2"
            name="settings[footer][links][][label]" placeholder="Etiket">
        <input type="text" class="form-control form-control-sm" style="flex:3"
            name="settings[footer][links][][url]" placeholder="URL">
        <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button>
        </div>`;
        list.appendChild(row);
    }

    // ── Font preview ──
    function selectFont(name) {
        document.getElementById('font-name-input').value = name;
        loadFontPreview(name);
    }

    function loadFontPreview(fontName) {
        if (!fontName) return;
        const encoded = fontName.replace(/ /g, '+');
        const existingLink = document.getElementById('preview-font-link');
        if (existingLink) existingLink.remove();
        const link = document.createElement('link');
        link.id = 'preview-font-link';
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${encoded}:wght@400;600;700&display=swap`;
        document.head.appendChild(link);
        document.getElementById('font-preview-box').style.fontFamily = `'${fontName}', sans-serif`;
    }

    document.getElementById('preview-font-btn').addEventListener('click', function() {
        loadFontPreview(document.getElementById('font-name-input').value.trim());
    });

    // Load existing font on page load
    const existingFont = '<?php echo esc($settings->templateInfos->fonts['googleFont'] ?? '') ?>';
    if (existingFont) loadFontPreview(existingFont);
</script>
<?php echo $this->endSection() ?>
