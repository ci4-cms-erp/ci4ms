<?php echo $this->extend('Modules\Install\Views\base') ?>
<?php echo $this->section('head') ?>
<link rel="stylesheet" href="/be-assets/plugins/bs-stepper/css/bs-stepper.min.css">
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<div class="row">
  <div class="col-md-12">
    <div class="card card-success">
      <div class="card-header">
        <h3 class="card-title">Install Ci4ms</h3>
      </div>
      <div class="card-body p-0">
        <?php echo view('Modules\Auth\Views\_message_block') ?>
        <form action="/install" method="post">
          <?php echo csrf_field() ?>
          <div class="bs-stepper">
            <div class="bs-stepper-header" role="tablist">
              <!-- your steps here -->
              <div class="step" data-target="#super-user-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="super-user-part" id="super-user-part-trigger">
                  <span class="bs-stepper-circle bg-success">1</span>
                  <span class="bs-stepper-label"><?php echo lang('Install.superUserInformation') ?></span>
                </button>
              </div>
              <div class="line"></div>
              <div class="step" data-target="#database-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="database-part" id="database-part-trigger">
                  <span class="bs-stepper-circle bg-success">2</span>
                  <span class="bs-stepper-label"><?php echo lang('Install.databaseInformation') ?></span>
                </button>
              </div>
              <div class="line"></div>
              <div class="step" data-target="#site-infos-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="site-infos-part" id="site-infos-part-trigger">
                  <span class="bs-stepper-circle bg-success">2</span>
                  <span class="bs-stepper-label"><?php echo lang('Install.siteInformation') ?></span>
                </button>
              </div>
            </div>
            <div class="bs-stepper-content">
              <!-- your steps content here -->
              <div id="super-user-part" class="content" role="tabpanel" aria-labelledby="super-user-part-trigger">
                <div class="form-group"><label for=""><?php echo lang('Install.yourName') ?></label>
                  <input type="text" class="form-control" name="name" value="<?php echo old('name') ?>" placeholder="Your Name" required>
                </div>
                <div class="form-group"><label for=""><?php echo lang('Install.surname') ?></label>
                  <input type="text" class="form-control" name="surname" value="<?php echo old('surname') ?>" placeholder="Surname" required>
                </div>
                <div class="form-group"><label for=""><?php echo lang('Install.email') ?></label>
                  <input type="email" class="form-control" name="email" value="<?php echo old('email') ?>" placeholder="E-mail" required>
                </div>
                <div class="form-group"><label for=""><?php echo lang('Install.username') ?></label>
                  <input type="text" class="form-control" name="username" value="<?php echo old('username') ?>" placeholder="Username" required>
                </div>
                <div class="form-group">
                  <div class="row" id="pwd-container">
                    <div class="col-sm-12">
                      <label for="password"><?php echo lang('Install.passwordMinLength') ?></label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="password" minlength="8" id="password" value="<?php echo old('password') ?>" placeholder="<?php echo lang('Auth.password') ?>" required>
                        <button class="btn btn-outline-success" type="button" onclick="generatePassword()"><i class="fas fa-sync"></i></button>
                      </div>
                    </div>
                    <div class="col-sm-12 col-sm-offset-2 ">
                      <div class="pwstrength_viewport_progress"></div>
                    </div>
                  </div>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.next()"><?php echo lang('Install.next') ?></button>
              </div>
              <div id="database-part" class="content" role="tabpanel" aria-labelledby="database-part-trigger">
                <div class="form-group">
                  <label for=""><?php echo lang('Install.databaseHost') ?></label>
                  <input type="text" class="form-control" name="host" placeholder="<?php echo lang('Install.databaseHost') ?>" value="<?php echo old('host', 'localhost') ?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.databaseName') ?></label>
                  <input type="text" class="form-control" name="dbname" value="<?php echo old('dbname') ?>" placeholder="<?php echo lang('Install.databaseName') ?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.databaseUsername') ?></label>
                  <input type="text" class="form-control" name="dbusername" value="<?php echo old('dbusername') ?>" placeholder="<?php echo lang('Install.databaseUsername') ?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.databasePassword') ?></label>
                  <input type="text" class="form-control" name="dbpassword" value="<?php echo old('dbpassword') ?>" placeholder="<?php echo lang('Install.databasePassword') ?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.databaseDriver') ?></label>
                  <input type="text" class="form-control" name="dbdriver" value="<?php echo old('dbdriver', 'MySQLi') ?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.databasePrefix') ?></label>
                  <input type="text" class="form-control" name="dbpre" value="<?php echo old('dbpre', 'ci4ms_') ?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.databasePort') ?></label>
                  <input type="number" class="form-control" name="dbport" value="<?php echo old('dbport', '3306') ?>" required>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.previous()"><?php echo lang('Install.previous') ?></button>
                <button class="btn btn-success" type="button" onclick="stepper.next()"><?php echo lang('Install.next') ?></button>
              </div>
              <div id="site-infos-part" class="content" role="tabpanel" aria-labelledby="site-infos-part-trigger">
                <div class="form-group">
                  <label for=""><?php echo lang('Install.siteName') ?></label>
                  <input type="text" name="siteName" value="<?php echo old('siteName') ?>" class="form-control" placeholder="<?php echo lang('Install.siteNamePlaceholder') ?>" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.siteUrl') ?></label>
                  <input type="url" class="form-control" name="baseUrl" value="<?php echo old('baseUrl') ?>" placeholder="https://example.com" required>
                </div>
                <div class="form-group">
                  <label for=""><?php echo lang('Install.siteSlogan') ?></label>
                  <input type="text" name="slogan" value="<?php echo old('slogan') ?>" class="form-control" placeholder="<?php echo lang('Install.siteSloganPlaceholder') ?>" required>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.previous()">Previous</button>
                <button type="submit" class="btn btn-success"><?php echo lang('Install.submit') ?></button>
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
<?php echo $this->endSection() ?>
<?php echo $this->section('javascript') ?>
<script src="/be-assets/plugins/bs-stepper/js/bs-stepper.min.js"></script>
<script src="/be-assets/node_modules/zxcvbn/dist/zxcvbn.js"></script>
<script src="/be-assets/node_modules/pwstrength-bootstrap/dist/pwstrength-bootstrap.min.js"></script>
<script {csp-script-nonce}>
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
<?php echo $this->endSection() ?>
