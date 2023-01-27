<!DOCTYPE html>
<html lang="en" >
<head>
    <meta charset="UTF-8">
    <title>Maintenance page</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
    <link rel="stylesheet" href="<?=site_url('maintenance/style.css') ?>">
</head>
<body>
<!-- partial:index.partial.html -->
<div class="maintenance">
    <div class="maintenance_contain">
        <img src="<?=site_url('maintenance/main-vector.png')?>" alt="maintenance">
        <span class="pp-infobox-title-prefix">WE ARE COMING SOON</span>
        <div class="pp-infobox-title-wrapper">
            <h3 class="pp-infobox-title">The website under maintenance!</h3>
        </div>
        <div class="pp-infobox-description">
            <p>Someone has kidnapped our site. We are negotiation ransom and<br>will resolve this issue in 24/7 hours</p>			</div>
        <span class="title-text pp-primary-title">We are social</span>
        <div class="pp-social-icons pp-social-icons-center pp-responsive-center">
            <?php if(!empty($settings->socialNetwork)):
                foreach($settings->socialNetwork as $sn):?>
	<span class="pp-social-icon">
		<link itemprop="url" href="#">
		<a itemprop="sameAs" href="<?=$sn->link?>" target="_blank" title="<?=$sn->smName?>" aria-label="<?=$sn->smName?>" role="button">
			<i class="fa fa-<?=$sn->smName?>"></i>
		</a>
	</span>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<!-- partial -->

</body>
</html>
