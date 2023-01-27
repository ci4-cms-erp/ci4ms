//multi level menu
(function ($bs) {
    const CLASS_NAME = 'has-child-dropdown-show';
    $bs.Dropdown.prototype.toggle = function (_orginal) {
        return function () {
            document.querySelectorAll('.' + CLASS_NAME).forEach(function (e) {
                e.classList.remove(CLASS_NAME);
            });
            let dd = this._element.closest('.dropdown').parentNode.closest('.dropdown');
            for (; dd && dd !== document; dd = dd.parentNode.closest('.dropdown')) {
                dd.classList.add(CLASS_NAME);
            }
            return _orginal.call(this);
        }
    }($bs.Dropdown.prototype.toggle);

    document.querySelectorAll('.dropdown').forEach(function (dd) {
        dd.addEventListener('hide.bs.dropdown', function (e) {
            if (this.classList.contains(CLASS_NAME)) {
                this.classList.remove(CLASS_NAME);
                e.preventDefault();
            }
            e.stopPropagation(); // do not need pop in multi level mode
        });
    });

    //for hover
    document.querySelectorAll('.dropdown-hover, .dropdown-hover-all .dropdown').forEach(function (dd) {
        dd.addEventListener('mouseenter', function (e) {
            let toggle = e.target.querySelector(':scope>[data-bs-toggle="dropdown"]');
            if (!toggle.classList.contains('show')) {
                $bs.Dropdown.getOrCreateInstance(toggle).toggle();
                dd.classList.add(CLASS_NAME);
                $bs.Dropdown.clearMenus();
            }
        });
        dd.addEventListener('mouseleave', function (e) {
            let toggle = e.target.querySelector(':scope>[data-bs-toggle="dropdown"]');
            if (toggle.classList.contains('show')) {
                $bs.Dropdown.getOrCreateInstance(toggle).toggle();
            }
        });
    });
})(bootstrap);

// create comment ajax
$('.sendComment').on('click', function () {
    var id = $(this).data('id');
    var blogID = $(this).data('blogid');
    var comFullName = $(this).closest("form").find("input[name='comFullName']").val();
    var comEmail = $(this).closest("form").find("input[name='comEmail']").val();
    var comMessage = $(this).closest("form").find("textarea[name='comMessage']").val();
    var captcha = $(this).closest("form").find("input[name='captcha']").val();

    const d = {
        'blog_id': blogID, 'comFullName': comFullName,
        'comEmail': comEmail, 'comMessage': comMessage,
        'captcha':captcha
    };

    if (id > 0) d.commentID = id;
    $.ajax({
        url: "/newComment",
        method: "POST",
        data: d,
        dataType: "json",
        success: function (data) {
            if (data.result === true)
                Swal.fire("You send successfully your comment", "", "success").then(function (isConfirm) {
                    if (isConfirm) location.reload(true);
                })
        },
        statusCode: {
            400: function (data) {
                var str = '';
                $.each(data.responseJSON.messages, function (i, item) {
                    str += item + '<br>';
                });
                Swal.fire({
                    title: "Error 400 !",
                    html: str,
                    icon: "error"
                });
            }
        }
    });
});

// display replies ajax
function replies(commentID) {
    $.ajax({
        url: "/repliesComment",
        method: "POST",
        data: {comID:commentID},
        dataType: "json",
        success: function (data) {
            console.log(data);
            $('#replies'+commentID).html(data.display);
        }
    });
}

//comment Load More
function loadMore(blogID,commentID='') {
    var id='#loadMore';
    if(commentID.length>0) id=id+commentID;
    var skip=$(id).data('skip');
    var d={
        blogID:blogID,skip:skip
    };
    if(commentID.length>0) d.comID=commentID;
    $.ajax({
        url:"/loadMoreComments",
        method: "POST",
        data:d,
        dataType:"json",
        success: function(data){
            $(id).data('skip',skip+$(id).data('defskip'));
            if(data.count==0) $(id).remove();
            $('#comments').append(data.display);
            if(data.count>0) captchaF();
        }
    });
}

function captchaF() {
    $.ajax({
        url:"/commentCaptcha",
        method: "POST",
        success: function(data){
            $('.captcha').attr('src',data.capIMG);
        }
    });
}

captchaF();