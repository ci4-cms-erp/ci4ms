<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css");
echo link_tag("be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css");
echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css");
echo $this->endSection();
echo $this->section('content'); ?>
<section class="content pt-3">
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-photo-video mr-2 text-primary"></i> <?php echo lang('Media.media') ?>
            </h3>
        </div>
        <div class="card-body p-0">
            <div id="elfinder"></div>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js");
echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js?v=2.1.70");
$elfinderLocale = service('language')->getLocale();
$elfinderLocaleVariants = [
    'pt' => 'pt_BR',
    // Simplified Chinese (zh_CN) is the more common assumption than Traditional (zh_TW); adjust if needed.
    'zh' => 'zh_CN',
];
if (!is_file(FCPATH . 'be-assets/plugins/elFinder/js/i18n/elfinder.' . $elfinderLocale . '.js')) {
    $elfinderLocale = isset($elfinderLocaleVariants[$elfinderLocale]) && is_file(FCPATH . 'be-assets/plugins/elFinder/js/i18n/elfinder.' . $elfinderLocaleVariants[$elfinderLocale] . '.js')
        ? $elfinderLocaleVariants[$elfinderLocale]
        : 'en';
}
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder." . $elfinderLocale . ".js?v=2.1.70");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js?v=2.1.70"); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    $(document).ready(function() {
        var elfinderMinHeight = 400;
        var elfinderBottomGap = 20;
        var computeElfinderHeight = function() {
            var $el = $('#elfinder');
            var offsetTop = $el.length ? Math.max(0, $el.offset().top - $(window).scrollTop()) : 0;
            return Math.max(elfinderMinHeight, Math.round(window.innerHeight - offsetTop - elfinderBottomGap));
        };
        var elf = $('#elfinder').elfinder({
            cssAutoLoad: [window.location.origin + '/be-assets/css/ci4ms-elfinder.css'],
            baseUrl: '/be-assets/plugins/elFinder/',
            url: '/backend/media/elfinderConnection',
            requestType: 'post',
            height: computeElfinderHeight(),
            lang: '<?php echo esc($elfinderLocale, 'js') ?>',
            workerBaseUrl: "/be-assets/plugins/elFinder/js/worker",
            getFileCallback: function(file, fm) {
                if (typeof top.elfinder_callback === 'function') {
                    top.elfinder_callback(file);
                    if (top.$ && typeof top.$.colorbox === 'function' && typeof top.$.colorbox.close === 'function') {
                        top.$.colorbox.close();
                    }
                } else {
                    fm.exec('quicklook');
                }
            },
            soundPath: '/be-assets/plugins/elFinder/sounds',
            sync: 1000,
            // Toolbar reorg (round 1): elFinder's own default is 14 groups /
            // 38 buttons (elfinder.full.js:12204-12219). We drop only
            // 'netmount' (no network volume driver is installed, the command
            // always fails server-side) and regrouped the remaining 37 into
            // 10 groups: navigation first, view/sort promoted next (was
            // second-to-last), frequent file ops early, rare ops
            // (chmod/hide/empty/selectinvert/archive/extract) + app controls
            // last.
            //
            // Toolbar reorg (round 2): round 1 still split 33 icon buttons
            // 30/3 across two rows at a 1471px viewport (93px toolbar height
            // vs 57px for one row) -- the lone row-2 group was
            // ['preference','help','fullscreen'], 3 buttons on their own
            // 36px-tall row. Regrouped the SAME 37 buttons (0 added, 0
            // removed -- verified with a sorted-array diff against round 1)
            // into 7 groups:
            //   1. navigation           home/back/forward/up/reload
            //   2. view & sort          kept early (2nd group, already won)
            //   3. create & transfer    mkdir/mkfile/upload/download/open/getfile
            //   4. edit history         clipboard (copy/cut/paste/rm/rename/
            //                           duplicate) + undo/redo -- undo/redo
            //                           reverts exactly the ops in this group
            //   5. selection & inspect  selectall/selectnone determine what
            //                           edit/resize/quicklook/info act on
            //   6. advanced/infrequent  chmod/hide/empty/selectinvert/archive/
            //                           extract + preference/help/fullscreen --
            //                           grouped by USAGE FREQUENCY (both halves
            //                           are "touched rarely" controls), not by
            //                           operation type like the groups above
            //   7. search               kept alone & moved LAST: the search
            //                           widget grows 70px->220px on focus
            //                           (elfinder.full.css:5179,5184); any
            //                           earlier position would shove every
            //                           later buttonset right mid-search, so
            //                           trailing lets only empty toolbar space
            //                           absorb the growth
            //
            // Chrome/footprint math (elfinder.full.css read directly):
            // - .elfinder-buttonset margin:1px 4px, padding:0 (:4719-4725);
            //   ci4ms-elfinder.css adds border 1px x2 + padding 4px x2 and
            //   overrides margin-right (currently 11px; see
            //   ci4ms-elfinder.css:61 for the full history: 4px vendor
            //   default -> 15px in the round-2 regroup below -> 12px once
            //   real-browser measurement, next paragraph, showed 15px left
            //   the toolbar too wide for its container -> 11px this round,
            //   confirmed with a real-browser width probe; see below) ->
            //   25px chrome per *group* at the current 11px value,
            //   independent of how many buttons it holds.
            // - .elfinder-button min-width 16px + padding 4px x2 (:4730-4744);
            //   ci4ms-elfinder.css sets margin 0 2px -> 28px per *button*.
            // 10 -> 7 groups (this round) removes 3 buttonsets, saving pure
            // chrome independent of button count, on top of the margin
            // saving below.
            //
            // Measured in a real browser, in the round that set
            // margin-right to 12px (see CHANGELOG.md for that round):
            // - Toolbar container: 1205px. An earlier version of this
            //   comment bracketed the container width without measuring it
            //   directly, then guessed the required width from that
            //   bracket -- both guesses were wrong; the numbers below are
            //   the real, measured ones.
            // - At margin-right:15px the layout needed 1219px (14px over
            //   the container): 2 rows, 25+8 buttons split across them,
            //   83px toolbar height.
            // - At margin-right:12px (previous round) the layout needed
            //   only 1201px -- 4px of headroom inside the 1205px container
            //   -- and all 34 rendered buttons (33 across the 7 buttonsets
            //   + the standalone search button) collapsed onto one row
            //   (every button shares the same top offset, 145px), 57px
            //   toolbar height. Confirmed with a self-shrinking width
            //   probe: 1201px still single-lined, 1200px wrapped back to
            //   two rows. Group separation was unaffected (real gap still
            //   16px), and the narrow-screen case (500-600px container
            //   proxy) was unchanged at 129px / 3 rows. Net saving from
            //   the 15px->12px change alone: 6 buttonsets x 3px = 18px --
            //   6, not 7, because only 6 of the 7 toolbar groups carry the
            //   .elfinder-buttonset margin-right (search sits in its own
            //   box, not a buttonset).
            // - At margin-right:11px (current, this round) the 1195px
            //   required width predicted by the linear model above
            //   (1129 + 6*11 = 1195px) was CONFIRMED with a real-browser
            //   width probe this round: 1195px still renders one row,
            //   1194px wraps to two -- the threshold itself is exact, not
            //   calculated. Toolbar height at one row: 57px, all 34
            //   rendered buttons (33 across the 7 buttonsets + the
            //   standalone search button) on it, group separation 15px
            //   (11px margin + 4px inline-block whitespace). The
            //   container, however, read 1204px this round vs. 1205px in
            //   the round above -- a 1px difference between two
            //   measurements of the same thing, not a real resize -- so
            //   headroom against it is ~9-10px, not a single fixed number:
            //   1205 - 1195 = 10px using the earlier reading, 1204 - 1195
            //   = 9px using this round's.
            //
            // FRAGILE: ~9-10px of headroom is better than the previous
            // 4px, but it is still finite. One more button (~28px) or one
            // more group (~25px chrome at this 11px margin) still
            // overruns it outright, and a font-metric shift (webfont
            // swap, OS font substitution, browser zoom) remains a risk on
            // top of that -- verify again in a real browser before adding
            // anything to `uiOptions.toolbar` below.
            //
            // No button is removed from the config in either round, so
            // nothing here is permanently lost -- everything stays reachable
            // via right-click -> "Toolbar settings" (preferenceInContextmenu,
            // on below).
            uiOptions: {
                toolbar: [
                    ['home', 'back', 'forward', 'up', 'reload'],
                    ['view', 'sort'],
                    ['mkdir', 'mkfile', 'upload', 'download', 'open', 'getfile'],
                    ['copy', 'cut', 'paste', 'rm', 'rename', 'duplicate', 'undo', 'redo'],
                    ['selectall', 'selectnone', 'edit', 'resize', 'quicklook', 'info'],
                    ['chmod', 'hide', 'empty', 'selectinvert', 'archive', 'extract', 'preference', 'help', 'fullscreen'],
                    ['search']
                ],
                toolbarExtra: {
                    // Sane default for a fresh install; has zero effect on an
                    // existing browser profile because elFinder only seeds this
                    // when no 'toolbarhides' value exists yet in localStorage
                    // (elfinder.full.js:21225). undo/redo are deliberately kept
                    // visible. Any hidden button (default-hidden or user-hidden)
                    // can be restored via right-click -> "Toolbar settings".
                    defaultHides: ['home', 'reload', 'chmod', 'hide', 'empty', 'selectinvert', 'getfile'],
                    // 'auto' only renders a fallback preference/gear button when
                    // the toolbar would otherwise be completely empty
                    // (elfinder.full.js:21112: `!self.children().length &&
                    // showPreferenceButton === 'auto'`). 'preference' is still
                    // included in group 6 above (no longer the LAST group after
                    // round 2 -- 'search' is now), so self.children().length is
                    // never 0 and this condition never fires today; it stays a
                    // safety net only if a future edit strips 'preference' from
                    // every group. 'always' was rejected: it renders a second
                    // preference button next to the one already in the group
                    // above unconditionally, which would just duplicate the
                    // control.
                    showPreferenceButton: 'auto'
                }
            },
            handlers: {
                upload: function() {
                    $('.elfinder-dialog-error').hide();
                }
            }
        }).elfinder('instance');

        var elfinderResizeTimer;
        $(window).on('resize', function() {
            clearTimeout(elfinderResizeTimer);
            elfinderResizeTimer = setTimeout(function() {
                elf.resize('auto', computeElfinderHeight());
            }, 150);
        });
    });
</script>
<?php echo $this->endSection() ?>
