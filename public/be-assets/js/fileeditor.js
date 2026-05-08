$("body").addClass("sidebar-collapse");

let currentPath = "";
let editor;

// Monaco Editor Initialization
require.config({
  paths: {
    vs: "/be-assets/plugins/monaco-editor/vs",
  },
});
require(["vs/editor/editor.main"], function () {
  editor = monaco.editor.create($("#editorContainer")[0], {
    value: "",
    language: "php",
    theme: "vs-dark",
    automaticLayout: true,
  });
});

const $saveButton = $("#saveFile");

// Load file list
$("#fileTree").fancytree({
  source: {
    url: "/backend/fileeditor/list",
    // Endpoint to fetch file list
    cache: false,
    // Disable cache for live updates
    postProcess: function (event, data) {
      data.result.sort(function (a, b) {
        return a.title.localeCompare(b.title, undefined, {
          sensitivity: "base",
        });
      });
    },
  },
  extensions: ["edit", "filter", "glyph"],
  quicksearch: true,
  filter: {
    autoApply: true,
    // Re-apply last filter if lazy data is loaded
    autoExpand: true,
    // Expand all branches that contain matches while filtered
    counter: true,
    // Show a badge with number of matching child nodes near parent icons
    fuzzy: false,
    // Match single characters in order, e.g. 'fb' will match 'FooBar'
    hideExpandedCounter: true,
    // Hide counter badge if parent is expanded
    hideExpanders: false,
    // Hide expanders if all child nodes are hidden by filter
    highlight: true,
    // Highlight matches by wrapping inside <mark> tags
    leavesOnly: false,
    // Match end nodes only
    nodata: true,
    // Display a 'no data' status node if result is empty
    mode: "dimm", // Grayout unmatched nodes (pass "hide" to remove unmatched node instead)
  },
  glyph: {
    preset: "awesome5",
  },
  lazyLoad: function (event, data) {
    const node = data.node;
    data.result = {
      url: "/backend/fileeditor/list",
      data: {
        path: node.key,
      },
      postProcess: function (event, data) {
        data.result.sort(function (a, b) {
          return a.title.localeCompare(b.title, undefined, {
            sensitivity: "base",
          });
        });
      },
    };
  },
  activate: function (event, data) {
    const node = data.node;
    if (!node.folder) {
      loadFileContent(node.key, node.title);
    }
  },
  init: function (event, data) {
    const rootNode = data.tree.getRootNode();
    rootNode.sortChildren(null, true); // null: varsayılan comparator (alfabetik), true:
    data.tree.visit(function (node) {
      // Automatically open the folders you specify.
      /* if (node.title === 'app' || node.title === 'modules') {
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
                                } */
      node.setExpanded(false);
    });
  },
  edit: {
    triggerStart: ["f2"],
    save: function (event, data) {
      $.ajax({
        url: "/backend/fileeditor/renameFile",
        method: "POST",
        data: {
          path: data.node.key,
          newName: data.input.val(),
        },
        success: function (response) {
          if (response.success) {
            alert("File renamed successfully");
          } else {
            alert("Could not rename file");
          }
        },
        error: function () {
          alert("Could not rename file");
        },
      });
    },
    close: function (event, data) {
      // Editor was removed
      if (data.save) {
        // Since we started an async request, mark the node as preliminary
        $(data.node.span).addClass("pending");
      }
    },
  },
});

// Context menu for creating files and folders
$.contextMenu({
  selector: "#fileTree span.fancytree-title",
  items: {
    createFile: {
      name: "New File",
      icon: "file",
      callback: function (key, opt) {
        var node = $.ui.fancytree.getNode(opt.$trigger);
        var fileName = prompt("New file name:");
        if (fileName) {
          $.ajax({
            url: "/backend/fileeditor/createFile",
            method: "POST",
            data: {
              path: node.key,
              name: fileName,
            },
            success: function (response) {
              if (response.success) {
                node.addChildren({
                  title: fileName,
                  key: node.key + "/" + fileName,
                  folder: false,
                });
                alert("File created successfully");
              } else {
                alert("File could not be created");
              }
            },
            error: function () {
              alert("File could not be created");
            },
          });
        }
      },
    },
    createFolder: {
      name: "New Folder",
      icon: "folder",
      callback: function (key, opt) {
        var node = $.ui.fancytree.getNode(opt.$trigger);
        var folderName = prompt("New folder name:");
        if (folderName) {
          $.ajax({
            url: "/backend/fileeditor/createFolder",
            method: "POST",
            data: {
              path: node.key,
              name: folderName,
            },
            success: function (response) {
              if (response.success) {
                node.addChildren({
                  title: folderName,
                  key: node.key + "/" + folderName,
                  folder: true,
                });
                alert("Folder created successfully");
              } else {
                alert("Folder could not be created");
              }
            },
            error: function () {
              alert("Folder could not be created");
            },
          });
        }
      },
    },
    sep1: "---------",
    rename: {
      name: "Rename",
      icon: "edit",
      callback: function (key, opt) {
        var node = $.ui.fancytree.getNode(opt.$trigger);
        node.editStart();
      },
    },
    delete: {
      name: "Delete",
      icon: "delete",
      callback: function (key, opt) {
        var node = $.ui.fancytree.getNode(opt.$trigger);
        if (confirm("Confirm Delete")) {
          $.ajax({
            url: "/backend/fileeditor/deleteFileOrFolder",
            method: "POST",
            data: {
              path: node.key,
            },
            success: function (response) {
              if (response.success) {
                node.remove();
                alert("File or folder deleted successfully");
              } else {
                alert("Could not delete file or folder");
              }
            },
            error: function () {
              alert("Could not delete file or folder");
            },
          });
        }
      },
    },
  },
});

var tree = $.ui.fancytree.getTree("#fileTree");
$("input[name=search]")
  .on("keyup", function (e) {
    var n,
      tree = $.ui.fancytree.getTree(),
      args =
        "autoApply autoExpand fuzzy hideExpanders highlight leavesOnly nodata".split(
          " ",
        ),
      opts = {},
      filterFunc = tree.filterNodes,
      match = $(this).val();

    $.each(args, function (i, o) {
      opts[o] = $("#" + o).is(":checked");
    });
    opts.mode = "dimm";

    if ((e && e.which === $.ui.keyCode.ESCAPE) || $.trim(match) === "") {
      $("button#btnResetSearch").trigger("click");
      return;
    }
    n = filterFunc.call(tree, match, opts);
    $("button#btnResetSearch").attr("disabled", false);
    $("span#matches").text("(" + n + " matches)");

    // Expand all matched nodes and their parents
    tree.visit(function (node) {
      if (node.match) {
        node.makeVisible();
      }
    });
  })
  .focus();

$("button#btnResetSearch")
  .click(function (e) {
    $("input[name=search]").val("");
    $("span#matches").text("");
    tree.clearFilter();
  })
  .attr("disabled", true);

$("fieldset input:checkbox").change(function (e) {
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

const getLanguageFromExtension = (fileName) => {
  const ext = fileName.split(".").pop().toLowerCase();
  const map = {
    php: "php",
    js: "javascript",
    css: "css",
    md: "markdown",
    json: "json",
    html: "html",
    txt: "plaintext",
    sql: "sql",
    env: "properties",
  };
  return map[ext] || undefined;
};

// Load file content
const loadFileContent = (path, fileName) => {
  $.ajax({
    url: `/backend/fileeditor/read`,
    data: {
      path: path,
    },
    dataType: "json",
    success: function (data) {
      if (data.content) {
        const currentModel = editor.getModel();
        if (currentModel) {
          currentModel.dispose();
        }

        const language = getLanguageFromExtension(fileName);
        const newModel = monaco.editor.createModel(
          data.content,
          language,
          monaco.Uri.file(fileName),
        );

        editor.setModel(newModel);
        currentPath = path;
      } else {
        alert("Could not read file");
      }
    },
    error: function () {
      console.error("Could not load file content");
    },
  });
};
$(".fancytree-container").addClass("p-2");
// Save file
$saveButton.on("click", function () {
  const content = editor.getValue();
  $.ajax({
    url: `/backend/fileeditor/save`,
    method: "POST",
    contentType: "application/json",
    data: JSON.stringify({
      path: currentPath,
      content: content,
    }),
    dataType: "json",
    success: function (data) {
      if (data.success) {
        alert("File saved successfully");
      } else {
        alert("Could not save file");
      }
    },
    error: function () {
      console.error("Could not save file");
    },
  });
});
