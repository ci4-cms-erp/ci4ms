<?php
if (!function_exists('comments')) {
    function comments(array $comments, string $blog_id)
    {
        $returnData = '';
        foreach ($comments as $comment) {
            if ($comment->parent_id == null) {
                $returnData .= '<div class="d-flex mb-4">
<div class="flex-shrink-0"><img class="rounded-circle" src="https://dummyimage.com/50x50/ced4da/6c757d.jpg"/></div>
<div class="ms-3">
<div class="fw-bold">' . $comment->comFullName . '</div>' . $comment->comMessage . '
<div class="w-100"></div>
<div class="btn-group">
<button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#reply' . $comment->id . '" aria-expanded="false" aria-controls="reply' . $comment->id . '">Reply</button>';
                if ((bool)$comment->isThereAnReply === true)
                    $returnData .= '<button class="btn btn-sm btn-link" onclick="replies(' . $comment->id . ')" type="button" data-bs-toggle="collapse"
data-bs-target="#replies' . $comment->id . '" aria-expanded="false" aria-controls="' . $comment->id . '">Replies <i class="bi-caret-down-fill"></i></button>';
                $returnData .= '</div><div class="collapse" id="reply' . $comment->id . '">
<div class="card card-body">
<form class="mb-1 row">
<div class="col-md-6 form-group mb-3">
<input type="text" class="form-control" name="comFullName" placeholder="Full name">
</div>
<div class="col-md-6 form-group mb-3">
<input type="email" class="form-control" name="comEmail" placeholder="E-mail">
</div>
<div class="col-12 form-group mb-3">
<textarea class="form-control" rows="3" name="comMessage" placeholder="Join the discussion and leave a comment!"></textarea>
</div>
<div class="col-6 form-group">
<div class="input-group">
<img src="" class="captcha" alt="captcha">
<input type="text" placeholder="captcha" name="captcha" class="form-control">
<button class="btn btn-secondary" onclick="captchaF()" type="button">New Captcha</button>
</div>
</div>
<div class="col-6 form-group text-end">
<button class="btn btn-primary btn-sm sendComment" type="button" data-blogid="' . $blog_id . '" data-id="' . $comment->id . '">Send</button>
</div>
</form>
</div>
</div>';
                if ((bool)$comment->isThereAnReply === true)
                    $returnData .= '<div class="collapse" id="replies' . $comment->id . '"></div>';
                $returnData .= '</div>
                </div>';
            }
        }
        return $returnData;
    }
}