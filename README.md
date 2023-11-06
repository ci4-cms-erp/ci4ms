[![wakatime](https://wakatime.com/badge/user/beb0631c-83fa-42d1-9a00-7239a892456c/project/3c3c11cb-bd8a-476a-998c-5264e9deedea.svg)](https://wakatime.com/badge/user/beb0631c-83fa-42d1-9a00-7239a892456c/project/3c3c11cb-bd8a-476a-998c-5264e9deedea)

# Login steps with mongodb in Codeigniter 4

## Features

This is meant to be a one-stop shop for 99% of your web-based authentication needs with CI4. It includes the following primary features:

<ul>
<li>Password-based authentication with remember-me functionality for web apps
Flat RBAC per NIST standards, described <a href="https://csrc.nist.gov/Projects/Role-Based-Access-Control">here</a> and <a href="https://www.semanticscholar.org/paper/A-formal-model-for-flat-role-based-access-control-Khayat-Abdallah/aeb1e9676e2d7694f268377fc22bdb510a13fab7?p2df">here</a>.</li>
<li>All views necessary for login, registration and forgotten password flows.</li>
<li>Publish files to the main application via a CLI command for easy customization</li>
<li>Email-based account verification</li>
</ul>

# How to Install ?

1. Let's create the project along with Composer.
```php
composer create-project ci4-cms-erp/ci4ms myproject
```

2. Copy the env file in the folder as .env. Then the section that needs to be updated in the .env file is as follows.
```php
php spark env development
```

```php
...
#--------------------------------------------------------------------
# APP
#--------------------------------------------------------------------
app.baseURL = 'https://ci4ms/'

...
#--------------------------------------------------------------------
# DATABASE
#--------------------------------------------------------------------

database.default.hostname = localhost
database.default.database = test
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.DBPrefix = ci4ms_
# database.default.port = 3306

...
#--------------------------------------------------------------------
# HONEYPOT
#--------------------------------------------------------------------

honeypot.hidden = 'true'
honeypot.label = 'Honey Pot CMS'
honeypot.name = 'honeypot_cms'
honeypot.template = '<label>{label}</label><input type="text" name="{name}" value=""/>'
honeypot.container = '<div style="display:none">{template}</div>'

#--------------------------------------------------------------------
# SECURITY
#--------------------------------------------------------------------

security.csrfProtection = 'session'
security.tokenRandomize = true
security.tokenName = 'csrf_token_ci4ms'
security.headerName = 'X-CSRF-TOKEN'
security.cookieName = 'csrf_cookie_ci4ms'
security.expires = 7200
security.regenerate = true
security.redirect = false
security.samesite = 'Lax'
...
```

3. After making your adjustments in the ENV file, navigate to the folder in the terminal.
```php
cd myproject
```

4. Let's use the codes added to Spark sequentially.
```php
php spark migrate
php spark db:seed Ci4msDefaultsSeeder
php spark create:route
php spark key:generate
```
Once the installation is successfully completed, you will encounter the initial homepage. You can now develop the theme, build modules, and make additions to bring your project to the desired level.