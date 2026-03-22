//multi level menu
(function ($bs) {
  const CLASS_NAME = "has-child-dropdown-show";

  // Bootstrap 5 nested dropdown support
  document
    .querySelectorAll(".dropdown-menu a.dropdown-toggle")
    .forEach(function (element) {
      element.addEventListener("click", function (e) {
        if (!this.nextElementSibling.classList.contains("show")) {
          this.closest(".dropdown-menu")
            .querySelectorAll(".show")
            .forEach(function (el) {
              el.classList.remove("show");
            });
        }
        this.nextElementSibling.classList.toggle("show");

        // Parent menu items should stay open
        let parent = this.closest(".dropdown");
        if (parent) {
          parent.classList.add(CLASS_NAME);
        }

        e.stopPropagation();
        e.preventDefault();
      });
    });

  // Reset nested classes when main dropdown hidden
  document.querySelectorAll(".dropdown").forEach(function (dd) {
    dd.addEventListener("hide.bs.dropdown", function (e) {
      this.querySelectorAll(".show").forEach(function (showEl) {
        showEl.classList.remove("show");
      });
      this.classList.remove(CLASS_NAME);
    });
  });

  // Hover support
  document
    .querySelectorAll(".dropdown-hover, .dropdown-hover-all .dropdown")
    .forEach(function (dd) {
      dd.addEventListener("mouseenter", function (e) {
        let toggle = e.target.querySelector(
          ':scope>[data-bs-toggle="dropdown"]',
        );
        if (toggle && !toggle.classList.contains("show")) {
          $bs.Dropdown.getOrCreateInstance(toggle).show();
        }
      });
      dd.addEventListener("mouseleave", function (e) {
        let toggle = e.target.querySelector(
          ':scope>[data-bs-toggle="dropdown"]',
        );
        if (toggle && toggle.classList.contains("show")) {
          $bs.Dropdown.getOrCreateInstance(toggle).hide();
        }
      });
    });
})(bootstrap);

// create comment ajax
$(".sendComment").on("click", function () {
  var id = $(this).data("id");
  var blogID = $(this).data("blogid");
  var comFullName = $(this)
    .closest("form")
    .find("input[name='comFullName']")
    .val();
  var comEmail = $(this).closest("form").find("input[name='comEmail']").val();
  var comMessage = $(this)
    .closest("form")
    .find("textarea[name='comMessage']")
    .val();
  var captcha = $(this).closest("form").find("input[name='captcha']").val();

  const d = {
    blog_id: blogID,
    comFullName: comFullName,
    comEmail: comEmail,
    comMessage: comMessage,
    captcha: captcha,
  };

  if (id > 0) d.commentID = id;
  $.ajax({
    url: "/newComment",
    method: "POST",
    data: d,
    dataType: "json",
    success: function (data) {
      if (data.result === true)
        Swal.fire("You send successfully your comment", "", "success").then(
          function (isConfirm) {
            if (isConfirm) location.reload(true);
          },
        );
    },
    statusCode: {
      400: function (data) {
        var str = "";
        $.each(data.responseJSON.messages, function (i, item) {
          str += item + "<br>";
        });
        Swal.fire({
          title: "Error 400 !",
          html: str,
          icon: "error",
        });
      },
    },
  });
});

// display replies ajax
function replies(commentID) {
  $.ajax({
    url: "/repliesComment",
    method: "POST",
    data: { comID: commentID },
    dataType: "json",
    success: function (data) {
      console.log(data);
      $("#replies" + commentID).html(data.display);
    },
  });
}

//comment Load More
function loadMore(blogID, commentID = "") {
  var id = "#loadMore";
  if (commentID.length > 0) id = id + commentID;
  var skip = $(id).data("skip");
  var d = {
    blogID: blogID,
    skip: skip,
  };
  if (commentID.length > 0) d.comID = commentID;
  $.ajax({
    url: "/loadMoreComments",
    method: "POST",
    data: d,
    dataType: "json",
    success: function (data) {
      $(id).data("skip", skip + $(id).data("defskip"));
      if (data.count == 0) $(id).remove();
      $("#comments").append(data.display);
      if (data.count > 0) captchaF();
    },
  });
}

function captchaF() {
  $.ajax({
    url: "/commentCaptcha",
    method: "POST",
    success: function (data) {
      $(".captcha").attr("src", data.capIMG);
    },
  });
}

captchaF();

$(function () {
  $("#product-search").autocomplete({
    source: function (request, response) {
      $.ajax({
        url: "/forms/searchForm",
        dataType: "json",
        method: "GET",
        data: {
          term: request.term,
        },
        success: function (data) {
          response(
            $.map(data, function (item) {
              return {
                label: item.value,
                value: item.value,
                url: item.url,
              };
            }),
          );
        },
      });
    },
    minLength: 2,
    select: function (event, ui) {
      if (ui.item.url) {
        window.location.href = ui.item.url;
      }
    },
  });

  $("#searchModal").on("shown.bs.modal", function () {
    $("#product-search").focus();
  });
});
