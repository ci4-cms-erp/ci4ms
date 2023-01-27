<!-- Contact form-->
<div class="bg-light rounded-3 py-5 px-4 px-md-5 mb-5">
    <?= view('templates/default/_message_block') ?>
    <div class="text-center mb-5">
        <div class="feature bg-primary bg-gradient text-white rounded-3 mb-3"><i class="bi bi-envelope"></i></div>
        <h1 class="fw-bolder">Get in touch</h1>
        <p class="lead fw-normal text-muted mb-0">We'd love to hear from you</p>
    </div>
    <div class="row gx-5 justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <form id="contactForm" method="post" action="<?=route_to('contactForm')?>">
                <?= csrf_field() ?>
                <!-- Name input-->
                <div class="form-floating mb-3">
                    <input class="form-control" name="name" type="text" placeholder="Enter your name..." data-sb-validations="required" />
                    <label for="name">Full name</label>
                    <div class="invalid-feedback" data-sb-feedback="name:required">A name is required.</div>
                </div>
                <!-- Email address input-->
                <div class="form-floating mb-3">
                    <input class="form-control" name="email" type="email" placeholder="name@example.com" data-sb-validations="required,email" />
                    <label for="email">Email address</label>
                    <div class="invalid-feedback" data-sb-feedback="email:required">An email is required.</div>
                    <div class="invalid-feedback" data-sb-feedback="email:email">Email is not valid.</div>
                </div>
                <!-- Phone number input-->
                <div class="form-floating mb-3">
                    <input class="form-control" name="phone" type="tel" placeholder="(123) 456-7890" data-sb-validations="required" />
                    <label for="phone">Phone number</label>
                    <div class="invalid-feedback" data-sb-feedback="phone:required">A phone number is required.</div>
                </div>
                <!-- Message input-->
                <div class="form-floating mb-3">
                    <textarea class="form-control" name="message" type="text" placeholder="Enter your message here..." style="height: 10rem" data-sb-validations="required"></textarea>
                    <label for="message">Message</label>
                    <div class="invalid-feedback" data-sb-feedback="message:required">A message is required.</div>
                </div>
                <!-- Submit Button-->
                <div class="d-grid"><button class="btn btn-primary btn-lg" id="submitButton" type="submit">Submit</button></div>
            </form>
        </div>
    </div>
</div>