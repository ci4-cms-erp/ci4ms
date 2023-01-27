<?= $this->extend('Views/templates/default/base') ?>
<?=$this->section('metatags')?>
<?=$seo?>
<?=$this->endSection()?>
<?= $this->section('content') ?>
<?php if($pageInfo->seflink!='/'): ?>
<header class="py-5 bg-light border-bottom mb-4">
    <div class="container">
        <div class="text-center my-5">
            <h1 class="fw-bolder"><?=$pageInfo->title?></h1>
        </div>
    </div>
</header>
<?php endif; ?>
<?= $pageInfo->content?>
<?= $this->endSection() ?>
