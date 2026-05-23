/* ═══════════════════════════════════════════════════════════
   CI4MS — Shared JS Utilities
   ═══════════════════════════════════════════════════════════ */

/**
 * ── Global CSRF Protection ──
 * Reads the CSRF meta tag rendered by csrf_meta() in base.php
 * and injects the token into every jQuery AJAX request via
 * the X-CSRF-TOKEN header + POST body parameter.
 *
 * After each successful AJAX response CI4 regenerates the
 * token (security.regenerate = true). The new token is sent
 * back via the X-CSRF-TOKEN response header (set by
 * CsrfTokenRefreshFilter). We update the meta tag so the
 * NEXT request always uses the fresh token.
 *
 * This means: Even if views send static tokens via PHP, this code updates the meta tag
 * after every response and ensures subsequent requests use the fresh token.
 */
(function () {
  var csrfMeta = $('meta[name="X-CSRF-TOKEN"]');
  var csrfName = "csrf_token_ci4ms"; // .env security.tokenName — POST body parameter name
  var csrfHeader = "X-CSRF-TOKEN"; // .env security.headerName — header + meta tag name

  function getCsrfHash() {
    return csrfMeta.length ? csrfMeta.attr("content") : "";
  }

  function setCsrfHash(newHash) {
    if (!newHash) return;
    if (csrfMeta.length) {
      csrfMeta.attr("content", newHash);
    }
    // Sayfadaki tüm csrf_field() hidden input'larını da yeniden senkronize et,
    // yoksa klasik (non-AJAX) form submit'ler bayat token ile 403 alır.
    $('input[name="' + csrfName + '"]').val(newHash);
  }

  /**
   * ajaxPrefilter — Runs BEFORE jQuery serializes the data.
   * This ensures CSRF tokens are injected even when object data
   * is sent via $.post(url, {key: val}).
   */
  $.ajaxPrefilter(function (options, originalOptions, jqXHR) {
    if (
      /^https?:\/\//i.test(options.url) &&
      options.url.indexOf(location.origin) !== 0
    ) {
      return;
    }

    // Always add X-CSRF-TOKEN header
    jqXHR.setRequestHeader(csrfHeader, getCsrfHash());

    // Add token to body for POST requests (CI4 reads from both header and body)
    if (options.type && options.type.toUpperCase() === "POST") {
      var hash = getCsrfHash();

      if (typeof options.data === "string") {
        // Data is already serialized string
        // First remove existing (stale) csrf token if present
        options.data = options.data
          .replace(
            new RegExp("(^|&)" + encodeURIComponent(csrfName) + "=[^&]*", "g"),
            "",
          )
          .replace(/^&/, "");
        options.data +=
          (options.data ? "&" : "") +
          encodeURIComponent(csrfName) +
          "=" +
          encodeURIComponent(hash);
      } else if (
        options.data &&
        typeof options.data === "object" &&
        !(options.data instanceof FormData)
      ) {
        // Object data — jQuery hasn't serialized it yet
        options.data[csrfName] = hash;
      } else if (!options.data || options.data === null) {
        // No data — create new string instead of object to prevent jQuery serialization issues
        options.data =
          encodeURIComponent(csrfName) + "=" + encodeURIComponent(hash);
      } else if (options.data instanceof FormData) {
        // FormData — add via append
        options.data.set(csrfName, hash);
      }
    }
  });

  /**
   * ajaxSetup.complete — Runs after every AJAX response.
   * Reads the new token from X-CSRF-TOKEN response header
   * (set by CsrfTokenRefreshFilter) and updates the meta tag.
   */
  $.ajaxSetup({
    complete: function (xhr) {
      // Get new CSRF token from response header
      var newToken = xhr.getResponseHeader(csrfHeader);
      if (newToken) {
        setCsrfHash(newToken);
        return; // if header exists, no need to check body
      }

      // Fallback: Get from JSON response body
      try {
        var json = xhr.responseJSON || JSON.parse(xhr.responseText);
        if (json && json.csrfToken) {
          setCsrfHash(json.csrfToken);
        }
      } catch (e) {
        /* Not JSON, ignore */
      }
    },
  });

  // Global helper — can be used in views
  window.CI4MS_CSRF = {
    name: csrfName,
    getHash: getCsrfHash,
    setHash: setCsrfHash,
  };
})();

// Auto-inject toast container if not present
$(function () {
  if ($("#toast-container").length === 0) {
    $("body").append('<div id="toast-container"></div>');
  }
});

/**
 * showToast — Global notification function.
 * Used by all modules — no need to redefine in views.
 * @param {string} msg   Message to display
 * @param {string} type  'success' | 'error'
 */
function showToast(msg, type) {
  type = type || "success";
  var id = "toast-" + Date.now();
  var icon =
    type === "success"
      ? "fa-check-circle text-success"
      : "fa-exclamation-circle text-danger";
  var html =
    '<div id="' +
    id +
    '" class="m-toast m-toast-' +
    type +
    '"><i class="fas ' +
    icon +
    '"></i> ' +
    msg +
    "</div>";
  $("#toast-container").append(html);
  setTimeout(function () {
    $("#" + id).addClass("show");
  }, 50);
  setTimeout(function () {
    $("#" + id).removeClass("show");
    setTimeout(function () {
      $("#" + id).remove();
    }, 300);
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
  var locale = window.CI4MS_LOCALE || "tr";
  var cfg = {
    url: "/be-assets/plugins/datatables/i18n/" + locale + ".json",
    search: "_INPUT_",
    processing: '<i class="fas fa-spinner fa-spin"></i>',
    paginate: {
      previous: '<i class="fas fa-chevron-left"></i>',
      next: '<i class="fas fa-chevron-right"></i>',
    },
  };
  if (searchPlaceholder) {
    cfg.searchPlaceholder = searchPlaceholder;
  }
  return cfg;
}

var _pageImgFmDiv = null;

function pageImgelfinderDialog() {
  var syncInterval;

  // Daha önce oluşturulmuş dialog varsa yeniden aç, ikinci kez oluşturma
  if (_pageImgFmDiv !== null) {
    try { _pageImgFmDiv.dialogelfinder("open"); } catch (e) {
      _pageImgFmDiv = null;
    }
    if (_pageImgFmDiv !== null) return;
  }

  _pageImgFmDiv = $("<div/>").dialogelfinder({
    url: "/backend/media/elfinderConnection",
    requestType: "post",
    lang: window.CI4MS_LOCALE !== "en" ? window.CI4MS_LOCALE || "tr" : "en",
    width: 1024,
    height: 768,
    workerBaseUrl: "/be-assets/plugins/elFinder/js/worker",
    cssAutoLoad: [
      window.location.origin + "/be-assets/css/ci4ms-elfinder.css",
    ],
    getFileCallback: function (files) {
      $(".pageimg-input").val(files.url.replace(location.origin, ""));
      $(".pageimg").attr("src", files.url).show();
      var img = new Image();
      img.onload = function () {
        $("#pageIMGHeight").val(this.height);
        $("#pageIMGWidth").val(this.width);
      };
      img.src = files.url;
    },
    commandsOptions: {
      getfile: {
        oncomplete: "close",
        folders: false,
        multiple: false,
      },
    },
    soundPath: "/be-assets/plugins/elFinder/sounds",
    handlers: {
      upload: function () {
        $(".elfinder-dialog-error").hide();
      },
      open: function (_event, instance) {
        startSync(instance);
      },
      close: function () {
        stopSync();
      },
    },
  });

  function startSync(instance) {
    stopSync();
    syncInterval = setInterval(function () {
      try { instance.exec("sync"); } catch (e) { stopSync(); }
    }, 1000);
  }

  function stopSync() {
    clearInterval(syncInterval);
  }
}

var _multipleImgFmDivs = {};

function pageMultipleImgelfinderDialog(id) {
  var syncInterval;

  if (_multipleImgFmDivs[id]) {
    try { _multipleImgFmDivs[id].dialogelfinder("open"); } catch (e) {
      _multipleImgFmDivs[id] = null;
    }
    if (_multipleImgFmDivs[id] !== null) return;
  }

  _multipleImgFmDivs[id] = $("<div/>").dialogelfinder({
    url: "/backend/media/elfinderConnection",
    requestType: "post",
    lang: window.CI4MS_LOCALE !== "en" ? window.CI4MS_LOCALE || "tr" : "en",
    width: "80%",
    height: 768,
    cssAutoLoad: [
      window.location.origin + "/be-assets/css/ci4ms-elfinder.css",
    ],
    getFileCallback: function (files) {
      $('[name="imgs[' + id + '][pageimg]"]').val(files.url.replace(location.origin, ""));
      $('[name="imgs[' + id + '][img]"]').attr("src", files.url);
      var img = new Image();
      img.onload = function () {
        $('[name="imgs[' + id + '][pageIMGHeight]"]').val(this.height);
        $('[name="imgs[' + id + '][pageIMGWidth]"]').val(this.width);
      };
      img.src = files.url;
    },
    commandsOptions: {
      getfile: {
        oncomplete: "close",
        folders: false,
      },
    },
    soundPath: "/be-assets/plugins/elFinder/sounds",
    handlers: {
      upload: function () {
        $(".elfinder-dialog-error").hide();
      },
      open: function (_event, instance) {
        startSync(instance);
      },
      close: function () {
        stopSync();
      },
    },
  });

  function startSync(instance) {
    stopSync();
    syncInterval = setInterval(function () {
      try { instance.exec("sync"); } catch (e) { stopSync(); }
    }, 1000);
  }

  function stopSync() {
    clearInterval(syncInterval);
  }
}

function multipleImgSelect(id) {
  pageMultipleImgelfinderDialog(id);
}

$(".pageIMG").click(function () {
  pageImgelfinderDialog(
    $(this).closest(".note-editor").parent().children(".pageimg"),
  );
});

$(".pageimg-input").change(function () {
  $(".pageimg").attr("src", $(this).val());
  const img = new Image();
  img.onload = function () {
    $("#pageIMGHeight").val(this.height);
    $("#pageIMGWidth").val(this.width);
  };
  img.src = $(this).val();
});

function elfinderDialog() {
  var fm = $("<div/>")
    .dialogelfinder({
      url: "/backend/media/elfinderConnection",
      requestType: "post",
      lang: window.CI4MS_LOCALE !== "en" ? window.CI4MS_LOCALE || "tr" : "en",
      width: "100%",
      height: 768,
      destroyOnClose: true,
      cssAutoLoad: [
        window.location.origin + "/be-assets/css/ci4ms-elfinder.css?v=",
      ],
      getFileCallback: function (files, fm) {
        $(".editor").summernote(
          "editor.insertImage",
          files.url.replace("https://" + location.hostname, ""),
        );
      },
      commandsOptions: {
        getfile: {
          oncomplete: "close",
          folders: false,
        },
      },
      soundPath: "/be-assets/plugins/elFinder/sounds",
      sync: 1000,
      handlers: {
        upload: function () {
          $(".elfinder-dialog-error").hide();
        },
      },
    })
    .dialogelfinder("instance");
}

function tags(data) {
  $(".keywords").tagify({
    whitelist: data,
    dropdown: {
      maxItems: 10, // <- mixumum allowed rendered suggestions
      classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
      enabled: 0, // <- show suggestions on focus
      closeOnSelect: false, // <- do not hide the suggestions dropdown once an item has been selected
      position: "text", // place the dropdown near the typed text
      highlightFirst: true,
    },
  });
}

$(document).ready(function () {
  if ($(".editor").length > 0) {
    $(".editor").summernote({
      height: 300,
      toolbar: [
        ["style", ["style"]],
        [
          "style",
          [
            "bold",
            "italic",
            "underline",
            "strikethrough",
            "superscript",
            "subscript",
            "clear",
          ],
        ],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ul", "ol", "paragraph"]],
        ["height", ["height"]],
        ["table", ["table"]],
        ["insert", ["link", "picture", "video", "hr", "readmore"]],
        ["media", ["elfinder"]],
        ["view", ["fullscreen", "codeview"]],
      ],
    });
  }
});
