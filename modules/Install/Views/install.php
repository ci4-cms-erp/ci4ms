<?= $this->extend('Modules\Install\Views\base') ?>
<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/bs-stepper/css/bs-stepper.min.css") ?>
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
        <form action="<?=route_to('install')?>" method="post">
          <div class="bs-stepper">
            <div class="bs-stepper-header" role="tablist">
              <!-- your steps here -->
              <div class="step" data-target="#super-user-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="super-user-part" id="super-user-part-trigger">
                  <span class="bs-stepper-circle bg-success">1</span>
                  <span class="bs-stepper-label">Super User Information</span>
                </button>
              </div>
              <div class="line"></div>
              <div class="step" data-target="#database-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="database-part" id="database-part-trigger">
                  <span class="bs-stepper-circle bg-success">2</span>
                  <span class="bs-stepper-label">Database Information</span>
                </button>
              </div>
              <div class="line"></div>
              <div class="step" data-target="#site-infos-part">
                <button type="button" class="step-trigger" role="tab" aria-controls="site-infos-part" id="site-infos-part-trigger">
                  <span class="bs-stepper-circle bg-success">2</span>
                  <span class="bs-stepper-label">Site information</span>
                </button>
              </div>
            </div>
            <div class="bs-stepper-content">
              <!-- your steps content here -->
              <div id="super-user-part" class="content" role="tabpanel" aria-labelledby="super-user-part-trigger">
                <div class="form-group"><label for="">Your Name</label>
                  <input type="text" class="form-control" name="name" id="" placeholder="Your Name" required>
                </div>
                <div class="form-group"><label for="">Surname</label>
                  <input type="text" class="form-control" name="surname" id="" placeholder="Surname" required>
                </div>
                <div class="form-group"><label for="">E-mail</label>
                  <input type="email" class="form-control" name="email" id="" placeholder="E-mail" required>
                </div>
                <div class="form-group"><label for="">Username</label>
                  <input type="text" class="form-control" name="username" id="" placeholder="Username" required>
                </div>
                <div class="form-group">
                  <div class="row" id="pwd-container">
                    <div class="col-sm-12">
                      <label for="password">Password <small>(min 8 characters)</small></label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="password" minlength="8" id="password" placeholder="Password" required>
                        <button class="btn btn-outline-success" type="button" onclick="generatePassword()"><i class="fas fa-sync"></i></button>
                      </div>
                    </div>
                    <div class="col-sm-12 col-sm-offset-2 ">
                      <div class="pwstrength_viewport_progress"></div>
                    </div>
                  </div>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.next()">Next</button>
              </div>
              <div id="database-part" class="content" role="tabpanel" aria-labelledby="database-part-trigger">
                <div class="form-group">
                  <label for="">Database Host</label>
                  <input type="text" class="form-control" name="host" id="" placeholder="Database Host" value="localhost" required>
                </div>
                <div class="form-group">
                  <label for="">Database Name</label>
                  <input type="text" class="form-control" name="dbname" id="" placeholder="Database Name" required>
                </div>
                <div class="form-group">
                  <label for="">Database Username</label>
                  <input type="text" class="form-control" name="dbusername" id="" placeholder="Database Password" required>
                </div>
                <div class="form-group">
                  <label for="">Database Password</label>
                  <input type="text" class="form-control" name="dbpassword" id="" placeholder="Database Password" required>
                </div>
                <div class="form-group">
                  <label for="">Database Driver</label>
                  <input type="text" class="form-control" name="dbdriver" id="" value="MySQLi" required>
                </div>
                <div class="form-group">
                  <label for="">Database Prefix</label>
                  <input type="text" class="form-control" name="dbpre" id="" value="ci4ms_" required>
                </div>
                <div class="form-group">
                  <label for="">Database Port</label>
                  <input type="number" class="form-control" name="dbport" id="" value="3306" required>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.previous()">Previous</button>
                <button class="btn btn-success" type="button" onclick="stepper.next()">Next</button>
              </div>
              <div id="site-infos-part" class="content" role="tabpanel" aria-labelledby="site-infos-part-trigger">
                <div class="form-group">
                  <label for="">Site Name</label>
                  <input type="text" name="siteName" id="" class="form-control" placeholder="Your site name here"  required>
                </div>
                <div class="form-group">
                  <label for="">Site URL</label>
                  <input type="url" class="form-control" name="baseUrl" id="" placeholder="https://example.com"  required>
                </div>
                <div class="form-group">
                  <label for="">Site Slogan</label>
                  <input type="text" name="slogan" id="" class="form-control" placeholder="Your slogan here" required>
                </div>
                <button class="btn btn-success" type="button" onclick="stepper.previous()">Previous</button>
                <button type="submit" class="btn btn-success">Submit</button>
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
<?= script_tag("be-assets/plugins/bs-stepper/js/bs-stepper.min.js") ?>
<?= script_tag("be-assets/node_modules/zxcvbn/dist/zxcvbn.js") ?>
<?= script_tag("be-assets/node_modules/pwstrength-bootstrap/dist/pwstrength-bootstrap.min.js") ?>
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
