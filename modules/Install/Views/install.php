<?= $this->extend('Modules\Install\Views\base') ?>
<?= $this->section('head') ?>
<link rel="stylesheet" href="/be-assets/plugins/bs-stepper/css/bs-stepper.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
  <div class="col-md-12">
    <div class="card card-success">
      <div class="card-header">
        <h3 class="card-title">Install Ci4ms</h3>
      </div>
      <div class="card-body p-0">
        <?= view('Modules\Auth\Views\_message_block') ?>
        <form action="/install" method="post">
          <div class="bs-stepper">
            <div class="bs-stepper-header" role="tablist">
              <!-- your steps here -->
              <div class="step" data-target="#super-user-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="super-user-part" id="super-user-part-trigger">
                  <span class="bs-stepper-circle bg-success">1</span>
                  <span class="bs-stepper-label"><?=lang('Install.superUserInformation')?></span>
                </button>
              </div>
              <div class="line"></div>
              <div class="step" data-target="#database-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="database-part" id="database-part-trigger">
                  <span class="bs-stepper-circle bg-success">2</span>
                  <span class="bs-stepper-label"><?=lang('Install.databaseInformation')?></span>
                </button>
              </div>
              <div class="line"></div>
              <div class="step" data-target="#site-infos-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="site-infos-part" id="site-infos-part-trigger">
                  <span class="bs-stepper-circle bg-success">2</span>
                  <span class="bs-stepper-label"><?=lang('Install.siteInformation')?></span>
                </button>
              </div>
            </div>
            <div class="bs-stepper-content">
              <!-- your steps content here -->
              <div id="super-user-part" class="content" role="tabpanel" aria-labelledby="super-user-part-trigger">
                <div class="form-group"><label for=""><?=lang('Install.yourName')?></label>
                  <input type="text" class="form-control" name="name" id="" placeholder="Your Name" required>
                </div>
                <div class="form-group"><label for=""><?=lang('Install.surname')?></label>
                  <input type="text" class="form-control" name="surname" id="" placeholder="Surname" required>
                </div>
                <div class="form-group"><label for=""><?=lang('Install.email')?></label>
                  <input type="email" class="form-control" name="email" id="" placeholder="E-mail" required>
                </div>
                <div class="form-group"><label for=""><?=lang('Install.username')?></label>
                  <input type="text" class="form-control" name="username" id="" placeholder="Username" required>
                </div>
                <div class="form-group">
                  <div class="row" id="pwd-container">
                    <div class="col-sm-12">
                      <label for="password"><?=lang('Install.passwordMinLength')?></label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="password" minlength="8" id="password" placeholder="<?=lang('Auth.password')?>" required>
                        <button class="btn btn-outline-success" type="button" onclick="generatePassword()"><i class="fas fa-sync"></i></button>
                      </div>
                    </div>
                    <div class="col-sm-12 col-sm-offset-2 ">
                      <div class="pwstrength_viewport_progress"></div>
                    </div>
                  </div>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.next()"><?=lang('Install.next')?></button>
              </div>
              <div id="database-part" class="content" role="tabpanel" aria-labelledby="database-part-trigger">
                <div class="form-group">
                  <label for=""><?=lang('Install.databaseHost')?></label>
                  <input type="text" class="form-control" name="host" id="" placeholder="<?=lang('Install.databaseHost')?>" value="localhost" required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.databaseName')?></label>
                  <input type="text" class="form-control" name="dbname" id="" placeholder="<?=lang('Install.databaseName')?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.databaseUsername')?></label>
                  <input type="text" class="form-control" name="dbusername" id="" placeholder="<?=lang('Install.databaseUsername')?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.databasePassword')?></label>
                  <input type="text" class="form-control" name="dbpassword" id="" placeholder="<?=lang('Install.databasePassword')?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.databaseDriver')?></label>
                  <input type="text" class="form-control" name="dbdriver" id="" value="MySQLi" required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.databasePrefix')?></label>
                  <input type="text" class="form-control" name="dbpre" id="" value="ci4ms_" required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.databasePort')?></label>
                  <input type="number" class="form-control" name="dbport" id="" value="3306" required>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.previous()"><?=lang('Install.previous')?></button>
                <button class="btn btn-success" type="button" onclick="stepper.next()"><?=lang('Install.next')?></button>
              </div>
              <div id="site-infos-part" class="content" role="tabpanel" aria-labelledby="site-infos-part-trigger">
                <div class="form-group">
                  <label for=""><?=lang('Install.siteName')?></label>
                  <input type="text" name="siteName" id="" class="form-control" placeholder="<?=lang('Install.siteNamePlaceholder')?>"  required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.siteUrl')?></label>
                  <input type="url" class="form-control" name="baseUrl" id="" placeholder="https://example.com"  required>
                </div>
                <div class="form-group">
                  <label for=""><?=lang('Install.siteSlogan')?></label>
                  <input type="text" name="slogan" id="" class="form-control" placeholder="<?=lang('Install.siteSloganPlaceholder')?>" required>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.previous()">Previous</button>
                <button type="submit" class="btn btn-success"><?=lang('Install.submit')?></button>
              </div>
            </div>
          </div>
        </form>
      </div>
      <!-- /.card-body -->
    </div>
    <!-- /.card -->
  </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('javascript') ?>
<script src="/be-assets/plugins/bs-stepper/js/bs-stepper.min.js"></script>
<script src="/be-assets/node_modules/zxcvbn/dist/zxcvbn.js"></script>
<script src="/be-assets/node_modules/pwstrength-bootstrap/dist/pwstrength-bootstrap.min.js"></script>
<script>
  // BS-Stepper Init
  document.addEventListener('DOMContentLoaded', function() {
    window.stepper = new Stepper(document.querySelector('.bs-stepper'))
  });
  jQuery(document).ready(function() {
    "use strict";
    var options = {};
    options.ui = {
      container: "#pwd-container",
      viewports: {
        progress: ".pwstrength_viewport_progress"
      },
      showVerdictsInsideProgressBar: true
    };
    options.common = {
      debug: true
    };
    $('#password').pwstrength(options);
  });

  // Rastgele Şifre Üretimi
  function generatePassword() {
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
    let password = "";
    for (let i = 0; i < 12; i++) {
      password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    $('#password').val(password).trigger('keyup');
  }
</script>
<?= $this->endSection() ?>
